<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_filpass\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared certificate upload service for FilPass.
 *
 * Used by the customcert observer and the retry scheduled task.
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_service {

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    /**
     * Uploads a customcert issue to FilPass and records the result.
     *
     * @param int $issueid
     * @param string $source
     * @return bool
     */
    public static function upload_issue(int $issueid, string $source = 'observer'): bool {
        $lockfactory = \core\lock\lock_config::get_lock_factory('local_filpass');
        $lock = $lockfactory->get_lock('upload_issue_' . $issueid, 0);

        if (!$lock) {
            debugging(
                'FilPass upload skipped because issue is already being processed: ' . $issueid,
                DEBUG_DEVELOPER
            );

            return false;
        }

        try {
            return self::upload_issue_locked($issueid, $source);
        } finally {
            $lock->release();
        }
    }

    /**
     * Performs the actual upload once the issue lock has been acquired.
     *
     * @param int $issueid
     * @param string $source
     * @return bool
     */
    private static function upload_issue_locked(int $issueid, string $source): bool {
        global $DB, $CFG;

        $now = time();

        $issue = $DB->get_record(
            'customcert_issues',
            ['id' => $issueid],
            '*',
            MUST_EXIST
        );

        $customcert = $DB->get_record(
            'customcert',
            ['id' => $issue->customcertid],
            '*',
            MUST_EXIST
        );

        $courseid = (int) $customcert->course;
        $userid = (int) $issue->userid;

        $courseconfig = $DB->get_record(
            'local_filpass_courses',
            ['courseid' => $courseid]
        );

        if (!$courseconfig || empty($courseconfig->enabled) || empty($courseconfig->batchid)) {
            debugging(
                "FilPass upload skipped. Course {$courseid} is not enabled or has no batch ID.",
                DEBUG_DEVELOPER
            );

            return false;
        }

        $user = $DB->get_record(
            'user',
            ['id' => $userid],
            'id, firstname, lastname, email',
            MUST_EXIST
        );

        $uploadrecord = self::get_or_create_upload_record(
            $issue,
            $customcert,
            $courseconfig,
            $user,
            $source
        );

        if ($uploadrecord->status === self::STATUS_SUCCESS) {
            return true;
        }

        if ($uploadrecord->status === self::STATUS_SKIPPED) {
            return true;
        }

        $context = \context_course::instance($courseid);

        /** @var \context $context */
        // Do not upload certificates issued to admins/managers/teachers who can view all certificates.
        if (has_capability('mod/customcert:viewallcertificates', $context, $userid)) {
            self::mark_skipped(
                $uploadrecord,
                'Skipped FilPass upload because the certificate belongs to a user with mod/customcert:viewallcertificates.'
            );

            debugging(
                "FilPass upload skipped for issue {$issueid}. User {$userid} can view all certificates.",
                DEBUG_DEVELOPER
            );

            return false;
        }

        $uploadrecord->status = self::STATUS_PENDING;
        $uploadrecord->source = clean_param($source, PARAM_ALPHANUMEXT);
        $uploadrecord->attempts = (int) $uploadrecord->attempts + 1;
        $uploadrecord->lastattempt = $now;
        $uploadrecord->timemodified = $now;

        $DB->update_record('local_filpass_uploads', $uploadrecord);

        self::trigger_begin_event($issue, $userid, $courseid, $context);

        $temppath = null;

        try {
            $template = $DB->get_record(
                'customcert_templates',
                ['id' => $customcert->templateid],
                '*',
                MUST_EXIST
            );

            $pagebuilder = new \mod_customcert\template($template);

            $filename = clean_filename(
                $customcert->name . '_' . $user->firstname . '_' . $user->lastname . '.pdf'
            );

            $pdfbinarydata = $pagebuilder->generate_pdf(false, $userid, true);

            if (empty($pdfbinarydata)) {
                self::mark_failed(
                    $uploadrecord,
                    'Customcert template engine returned an empty PDF stream.',
                    null
                );

                self::trigger_failure_event($issue, $userid, $courseid, $context);

                return false;
            }

            $tempdir = $CFG->tempdir . '/local_filpass';

            if (!file_exists($tempdir)) {
                @mkdir($tempdir, 0777, true);
            }

            $temppath = $tempdir . '/' . uniqid('filpass_', true) . '_' . $filename;

            file_put_contents($temppath, $pdfbinarydata);

            $client = new \local_filpass\api_client();

            $response = $client->upload_bulk_data(
                $courseconfig->batchid,
                $user->firstname,
                $user->lastname,
                $user->email,
                $temppath,
                $filename
            );

            $uploadsucceeded = is_object($response)
                && isset($response->status)
                && $response->status === 'success';

            if ($uploadsucceeded) {
                self::mark_success($uploadrecord, $filename, $response);
                self::trigger_success_event($issue, $userid, $courseid, $context);

                return true;
            }

            self::mark_failed(
                $uploadrecord,
                'FilPass API did not return a success status.',
                $response
            );

            self::trigger_failure_event($issue, $userid, $courseid, $context);

            return false;

        } catch (\Throwable $exception) {
            self::mark_failed(
                $uploadrecord,
                $exception->getMessage(),
                null
            );

            self::trigger_failure_event($issue, $userid, $courseid, $context);

            debugging(
                'FilPass upload failed for issue ' . $issueid . ': ' . $exception->getMessage(),
                DEBUG_DEVELOPER
            );

            return false;

        } finally {
            if ($temppath && file_exists($temppath)) {
                @unlink($temppath);
            }
        }
    }

    /**
     * Gets or creates the upload tracking record.
     *
     * @param object $issue
     * @param object $customcert
     * @param object $courseconfig
     * @param object $user
     * @param string $source
     * @return object
     */
    private static function get_or_create_upload_record(
        object $issue,
        object $customcert,
        object $courseconfig,
        object $user,
        string $source
    ): object {
        global $DB;

        $existing = $DB->get_record(
            'local_filpass_uploads',
            ['issueid' => $issue->id]
        );

        if ($existing) {
            return $existing;
        }

        $now = time();

        $record = (object) [
            'issueid' => $issue->id,
            'courseid' => $customcert->course,
            'userid' => $issue->userid,
            'customcertid' => $customcert->id,
            'batchid' => $courseconfig->batchid,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'filename' => '',
            'status' => self::STATUS_PENDING,
            'source' => clean_param($source, PARAM_ALPHANUMEXT),
            'attempts' => 0,
            'lastattempt' => 0,
            'nextretry' => 0,
            'timeuploaded' => 0,
            'lastresponse' => '',
            'lasterror' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        try {
            $record->id = $DB->insert_record('local_filpass_uploads', $record);
        } catch (\dml_write_exception $exception) {
            // Handles a rare race where another process creates the same issue row first.
            $existing = $DB->get_record(
                'local_filpass_uploads',
                ['issueid' => $issue->id],
                '*',
                IGNORE_MISSING
            );

            if ($existing) {
                return $existing;
            }

            throw $exception;
        }

        return $record;
    }

    /**
     * Marks the upload as successful.
     *
     * @param object $record
     * @param string $filename
     * @param mixed $response
     * @return void
     */
    private static function mark_success(object $record, string $filename, $response): void {
        global $DB;

        $now = time();

        $record->filename = $filename;
        $record->status = self::STATUS_SUCCESS;
        $record->timeuploaded = $now;
        $record->nextretry = 0;
        $record->lastresponse = self::encode_response($response);
        $record->lasterror = '';
        $record->timemodified = $now;

        $DB->update_record('local_filpass_uploads', $record);
    }

    /**
     * Marks the upload as failed and schedules a future retry.
     *
     * @param object $record
     * @param string $error
     * @param mixed $response
     * @return void
     */
    private static function mark_failed(object $record, string $error, $response): void {
        global $DB;

        $now = time();
        $attempts = max(1, (int) $record->attempts);

        // 5, 10, 20, 40, then 60 minutes.
        $delayminutes = min(60, 5 * (2 ** min($attempts - 1, 4)));

        $record->status = self::STATUS_FAILED;
        $record->nextretry = $now + ($delayminutes * 60);
        $record->lasterror = $error;
        $record->lastresponse = self::encode_response($response);
        $record->timemodified = $now;

        $DB->update_record('local_filpass_uploads', $record);
    }

    /**
     * Marks an upload as intentionally skipped.
     *
     * @param object $record
     * @param string $reason
     * @return void
     */
    private static function mark_skipped(object $record, string $reason): void {
        global $DB;

        $now = time();

        $record->status = self::STATUS_SKIPPED;
        $record->nextretry = 0;
        $record->lasterror = $reason;
        $record->timemodified = $now;

        $DB->update_record('local_filpass_uploads', $record);
    }

    /**
     * Encodes API responses safely for DB storage.
     *
     * @param mixed $response
     * @return string
     */
    private static function encode_response($response): string {
        if ($response === null || $response === false) {
            return '';
        }

        $encoded = json_encode($response, JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '' : $encoded;
    }

    /**
     * Triggers begin upload event.
     *
     * @param object $issue
     * @param int $userid
     * @param int $courseid
     * @param \context $context
     * @return void
     */
    private static function trigger_begin_event(
        object $issue,
        int $userid,
        int $courseid,
        \context $context
    ): void {
        try {
            $event = \local_filpass\event\begin_filpass_upload::create([
                'objectid' => $issue->id,
                'userid' => $userid,
                'context' => $context,
                'courseid' => $courseid,
            ]);

            $event->trigger();
        } catch (\Throwable $exception) {
            debugging('FilPass begin event warning: ' . $exception->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Triggers successful upload event.
     *
     * @param object $issue
     * @param int $userid
     * @param int $courseid
     * @param \context $context
     * @return void
     */
    private static function trigger_success_event(
        object $issue,
        int $userid,
        int $courseid,
        \context $context
    ): void {
        try {
            $event = \local_filpass\event\uploaded_to_filpass::create([
                'objectid' => $issue->id,
                'userid' => $userid,
                'context' => $context,
                'courseid' => $courseid,
            ]);

            $event->trigger();
        } catch (\Throwable $exception) {
            debugging('FilPass success event warning: ' . $exception->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Triggers failed upload event.
     *
     * @param object $issue
     * @param int $userid
     * @param int $courseid
     * @param \context $context
     * @return void
     */
    private static function trigger_failure_event(
        object $issue,
        int $userid,
        int $courseid,
        \context $context
    ): void {
        try {
            $event = \local_filpass\event\upload_failed_filpass::create([
                'objectid' => $issue->id,
                'userid' => $userid,
                'context' => $context,
                'courseid' => $courseid,
            ]);

            $event->trigger();
        } catch (\Throwable $exception) {
            debugging('FilPass failure event warning: ' . $exception->getMessage(), DEBUG_DEVELOPER);
        }
    }
}