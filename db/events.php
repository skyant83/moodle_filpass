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
 * Declares the Moodle event observer used to process certificate issuance events.
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// /local/filpass/db/events.php

defined('MOODLE_INTERNAL') || die();

// Moodle registers the plugin callback here so certificate issuance events are handled
// automatically whenever the custom certificate module raises an issue_created event.
$observers = array(
    array(
        'eventname'   => '\mod_customcert\event\issue_created',
        'callback'    => '\local_filpass\observer::certificate_generated',
        'priority'    => 500,
    )
);