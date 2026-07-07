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
 * Event raised when a certificate upload to FilPass fails.
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_filpass\event;

defined('MOODLE_INTERNAL') || die();

class upload_failed_filpass extends \core\event\base {

    /**
     * Initialises the event metadata for Moodle event logging.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'customcert_issues';
    }

    /**
     * Returns a human-readable event name.
     *
     * @return string The event name.
     */
    public static function get_name() {
        return 'FilPass upload failed';
    }

    /**
     * Builds a descriptive message for the event log.
     *
     * @return string The event description.
     */
    public function get_description() {
        return "The user with id '{$this->userid}' was issued a certificate (Issue ID: '{$this->objectid}') but the upload to FilPass did not complete successfully.";
    }

    /**
     * Returns the URL associated with the certificate-related context.
     *
     * @return \moodle_url The view URL for the certificate activity.
     */
    public function get_url() {
        return new \moodle_url('/mod/customcert/view.php', ['id' => $this->contextinstanceid]);
    }
}
