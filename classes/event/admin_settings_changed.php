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
 * Event raised when the site-level FilPass settings are changed.
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_filpass\event;

defined('MOODLE_INTERNAL') || die();

class admin_settings_changed extends \core\event\base {

    /**
     * Initialises the event metadata for Moodle event logging.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'config';
    }

    /**
     * Returns a human-readable event name.
     *
     * @return string The event name.
     */
    public static function get_name() {
        return 'FilPass admin settings changed';
    }

    /**
     * Builds a descriptive message for the event log.
     *
     * @return string The event description.
     */
    public function get_description() {
        return "The site-level FilPass settings were updated by user id '{$this->userid}'.";
    }

    /**
     * Returns the URL associated with the relevant administration page.
     *
     * @return \moodle_url The settings URL.
     */
    public function get_url() {
        return new \moodle_url('/admin/settings.php', ['section' => 'local_filpass_settings']);
    }
}
