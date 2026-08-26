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

namespace local_filpass;
defined('MOODLE_INTERNAL') || die();

/**
 * Class admin_setting_text
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_text extends \admin_setting_configtext {
    /**
     * Keeps the native credential field visible while preset actions control saving.
     *
     * @return void
     */
    public function __construct(...$args) {
        parent::__construct(...$args);
        $this->nosave = true;
    }

    public function write_setting($data) {
        $oldvalue = $this->get_setting();
        $result = parent::write_setting($data);
        if ($result === '' && $oldvalue !== $this->get_setting()) {
            $this->trigger_admin_settings_event();
        }
        return $result;
    }

    protected function trigger_admin_settings_event() {
        global $USER;

        try {
            // A direct edit intentionally returns the site to the legacy default connection.
            unset_config('activepresetid', 'local_filpass');
            $event = \local_filpass\event\admin_settings_changed::create([
                'userid' => $USER->id,
                'context' => \context_system::instance(),
            ]);
            $event->trigger();
        } catch (\Exception $e) {
            error_log('FilPass admin settings event warning: ' . $e->getMessage());
        }
    }
}
