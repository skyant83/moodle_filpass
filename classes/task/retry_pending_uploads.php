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

namespace local_filpass\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Retries issued certificates that have not been successfully sent to FilPass
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class retry_pending_uploads extends \core\task\scheduled_task {

    /**
     * Returns the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_retry_pending_uploads', 'local_filpass');
    }

    /**
     * Executes the retry task.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $now = time();
        $maxattempts = 10;
        $batchlimit = 25;

        $sql = "
            SELECT ci.id AS issueid
              FROM {customcert_issues} ci
              JOIN {customcert} cc
                   ON cc.id = ci.customcertid
              JOIN {local_filpass_courses} fc
                   ON fc.courseid = cc.course
                  AND fc.enabled = 1
                  AND fc.batchid <> ''
         LEFT JOIN {local_filpass_uploads} fu
                   ON fu.issueid = ci.id
             WHERE (
                    fu.id IS NULL
                    OR fu.status IN (:pendingstatus, :failedstatus)
                   )
               AND (
                    fu.id IS NULL
                    OR fu.nextretry = 0
                    OR fu.nextretry <= :now
                   )
               AND (
                    fu.id IS NULL
                    OR fu.attempts < :maxattempts
                   )
          ORDER BY ci.timecreated ASC
        ";

        $params = [
            'pendingstatus' => \local_filpass\service\upload_service::STATUS_PENDING,
            'failedstatus' => \local_filpass\service\upload_service::STATUS_FAILED,
            'now' => $now,
            'maxattempts' => $maxattempts,
        ];

        $records = $DB->get_records_sql($sql, $params, 0, $batchlimit);

        foreach ($records as $record) {
            try {
                \local_filpass\service\upload_service::upload_issue(
                    (int) $record->issueid,
                    'scheduled_task'
                );
            } catch (\Throwable $exception) {
                mtrace('FilPass retry failed for issue ' . $record->issueid . ': ' . $exception->getMessage());

                debugging(
                    'FilPass retry failed for issue ' . $record->issueid . ': ' . $exception->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }
    }
}