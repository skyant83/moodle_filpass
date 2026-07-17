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
     * Handles custom certificate issue_created events.
     *
     * @param \mod_customcert\event\issue_created $event The certificate issue event.
     * @return bool A structured result array or an early-exit diagnostic string.
     */
    public static function certificate_generated(\mod_customcert\event\issue_created $event): bool {
        $eventdata = $event->get_data();

        if (empty($eventdata['objectid'])) {
            debugging(
                'FilPass observer skipped because event objectid is missing.',
                DEBUG_DEVELOPER
            );

            return false;
        }

        return \local_filpass\service\upload_service::upload_issue(
            (int) $eventdata['objectid'],
            'observer'
        );
    }


    /**
     * Handles custom certificate issue_deleted events.
     *
     * When an admin deletes a certificate issue from mod_customcert, remove the
     * corresponding FilPass upload tracking row so the retry task will not treat
     * the deleted certificate as something that still needs to be synchronized.
     *
     * @param \mod_customcert\event\issue_deleted $event
     * @return bool
     */
    public static function certificate_deleted(\mod_customcert\event\issue_deleted $event): bool {
        global $DB;

        $eventdata = $event->get_data();

        if (empty($eventdata['objectid'])) {
            debugging(
                'FilPass delete observer skipped because event objectid is missing.',
                DEBUG_DEVELOPER
            );

            return false;
        }

        $issueid = (int) $eventdata['objectid'];

        $existing = $DB->get_record(
            'local_filpass_uploads',
            ['issueid' => $issueid]
        );

        if (!$existing) {
            debugging(
                'FilPass delete observer found no upload record for deleted issue ' . $issueid,
                DEBUG_DEVELOPER
            );

            return false;
        }

        $DB->delete_records(
            'local_filpass_uploads',
            ['issueid' => $issueid]
        );

        debugging(
            'FilPass upload record deleted for customcert issue ' . $issueid,
            DEBUG_DEVELOPER
        );

        return true;
    }
}