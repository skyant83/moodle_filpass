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

/**
 * Observes certificate issuance events and forwards them to FilPass.
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// /local/filpass/classes/observer.php
namespace local_filpass;

defined('MOODLE_INTERNAL') || die();

class observer {
    /**
     * Handles a custom certificate issue_created event.
     *
     * The method validates that FilPass is enabled for the course, generates the
     * certificate PDF, and submits the document to the mapped FilPass batch.
     *
     * @param \mod_customcert\event\issue_created $event The certificate issue event.
     * @return array|string A structured result array or an early-exit diagnostic string.
     */
    public static function certificate_generated(\mod_customcert\event\issue_created $event) {
        global $DB, $CFG;

        $eventdata = $event->get_data();
        $userid = $eventdata['userid'];
        $courseid = $eventdata['courseid'];

        // The observer exits early when the integration is disabled for the course. This avoids
        // generating PDFs or calling the external API for courses that are not configured.
        $is_enabled = get_config('local_filpass', 'course_' . $courseid . '_enabled');
        if (!$is_enabled) {
            return "EXIT_EARLY: FilPass integration is explicitly disabled for Course ID {$courseid}.";
        }
        // The observer stops here when the course is not configured for issuance.
        $batch_id = get_config('local_filpass', 'course_' . $courseid . '_batch_id');
        if (!$batch_id) {
            // A missing batch mapping means the course is not ready for issuance even if the
            // integration switch is turned on, so the observer stops before making an API call.
            return "EXIT_EARLY: No batch ID mapped for Course ID {$courseid} inside the database.";
        }

        // 1. Fetch Student Profile Core Elements
        $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email', MUST_EXIST);



        // 2. Fetch the corresponding customcert activity instance record
        $issue_record = $DB->get_record('customcert_issues', ['id' => $eventdata['objectid']], '*', MUST_EXIST);
        $customcert = $DB->get_record('customcert', ['id' => $issue_record->customcertid], '*', MUST_EXIST);

        $context = $event->get_context();
        if (has_capability('mod/customcert:viewallcertificates', $context, $userid)) {
            // Administrative users are skipped so the integration does not forward certificate
            // data for users who already have broader certificate management privileges.
            return 'EXIT_EARLY: Skipped synchronization. User ID {$userid} has administrative/management capabilities.';
        }

        // A Moodle event is raised before the upload attempt so there is still an audit trail
        // even if the FilPass transfer fails later in the process.
        try {
            $start_event = \local_filpass\event\begin_filpass_upload::create([
                'objectid' => $issue_record->id,
                'userid' => $userid,
                'context' => $context,
                'courseid' => $courseid,
            ]);

            $start_event->trigger();
        } catch (\Exception $e) {
            error_log('FilPass Log Generation Warning: ' . $e->getMessage());
        }

        // The certificate PDF is generated from the active custom certificate template at runtime.
        // This ensures the uploaded file reflects the latest template configuration instead of a stale artifact.
        $template = $DB->get_record('customcert_templates', ['id' => $customcert->templateid], '*', MUST_EXIST);
        $pagebuilder = new \mod_customcert\template($template);

        $file_name = clean_filename($customcert->name . '_' . $user->firstname . '_' . $user->lastname . '.pdf');

        $pdf_binary_data = $pagebuilder->generate_pdf(false, $userid, true);

        if (empty($pdf_binary_data)) {
            try {
                $failure_event = \local_filpass\event\upload_failed_filpass::create([
                    'objectid' => $issue_record->id,
                    'userid' => $userid,
                    'context' => $context,
                    'courseid' => $courseid,
                ]);

                $failure_event->trigger();
            } catch (\Exception $e) {
                error_log('FilPass upload failure event warning: ' . $e->getMessage());
            }

            return "EXIT_EARLY: Customcert template engine failed to compile a binary PDF stream.";
        }

        // The generated PDF is written to a temporary file because the FilPass API expects a
        // real file on disk rather than an in-memory binary stream.
        $temp_dir = $CFG->tempdir . '/local_filpass';
        if (!file_exists($temp_dir)) {
            @mkdir($temp_dir, 0777, true);
        }
        $local_temp_file = $temp_dir . '/' . $file_name;

        file_put_contents($local_temp_file, $pdf_binary_data);

        // The prepared certificate file is handed to the FilPass API client once the PDF has
        // been generated and written to a temporary location on disk.
        $client = new \local_filpass\api_client();
        $response = $client->upload_bulk_data(
            $batch_id,
            $user->firstname,
            $user->lastname,
            $user->email,
            $local_temp_file,
            $file_name
        );

        @unlink($local_temp_file);

        $upload_succeeded = is_object($response) && isset($response->status) && $response->status === 'success';

        try {
            if ($upload_succeeded) {
                $success_event = \local_filpass\event\uploaded_to_filpass::create([
                    'objectid' => $issue_record->id,
                    'userid' => $userid,
                    'context' => $context,
                    'courseid' => $courseid,
                ]);
                $success_event->trigger();
            } else {
                $failure_event = \local_filpass\event\upload_failed_filpass::create([
                    'objectid' => $issue_record->id,
                    'userid' => $userid,
                    'context' => $context,
                    'courseid' => $courseid,
                ]);
                $failure_event->trigger();
            }
        } catch (\Exception $e) {
            error_log('FilPass upload event warning: ' . $e->getMessage());
        }

        return [
            'checkpoint' => 'REACHED_API_UPLOAD_END',
            'api_raw_response' => $response
        ];
    }
}