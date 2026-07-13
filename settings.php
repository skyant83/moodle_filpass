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
 * Site-level configuration for the FilPass plugin.
 *
 * This page stores the base API endpoint and the credentials used to authenticate
 * requests against the remote FilPass service.
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// /local/filpass/settings.php

defined('MOODLE_INTERNAL') || die();

/** @var bool $hassiteconfig */
if ($hassiteconfig) {
    $settings = new admin_settingpage('local_filpass_settings', get_string('pluginname', 'local_filpass'));

    // API Server URL
    $settings->add(new \local_filpass\admin_setting_text(
        'local_filpass/api_server',
        get_string('api_server', 'local_filpass'),
        get_string('api_server_desc', 'local_filpass'),
        'https://demo-api.internal.filpass.ph',
        PARAM_URL
    ));

    // API Key
    $settings->add(new \local_filpass\admin_setting_password(
        'local_filpass/api_key',
        get_string('api_key', 'local_filpass'),
        get_string('api_key_desc', 'local_filpass'),
        ''
    ));

    // API Secret
    $settings->add(new \local_filpass\admin_setting_password(
        'local_filpass/api_secret',
        get_string('api_secret', 'local_filpass'),
        get_string('api_secret_desc', 'local_filpass'),
        ''
    ));

    $test_connection_html =
    html_writer::start_div('mt-3', [
        'id' => 'local-filpass-connection-test',
    ]) .

    html_writer::tag(
        'button',
        get_string('testconnection', 'local_filpass'),
        [
            'type' => 'button',
            'id' => 'local-filpass-test-button',
            'class' => 'btn btn-secondary',
        ]
    ) .

    html_writer::start_div('mt-3') .

    html_writer::tag(
        'label',
        get_string('connectionresponse', 'local_filpass'),
        [
            'for' => 'local-filpass-test-output',
        ]
    ) .

    html_writer::tag(
        'textarea',
        '',
        [
            'id' => 'local-filpass-test-output',
            'class' => 'form-control',
            'rows' => '10',
            'readonly' => 'readonly',
            'aria-live' => 'polite',
        ]
    ) .

    html_writer::end_div() .
    html_writer::end_div();

    $settings->add(new admin_setting_heading(
        'local_filpass/testconnectionsection',
        get_string('testconnection', 'local_filpass'),
        $test_connection_html
    ));

    /** @var moodle_page $PAGE */
    $PAGE->requires->js_call_amd('local_filpass/test_connection', 'init');

    /** @var admin_root $ADMIN */
    $ADMIN->add('localplugins', $settings);
}