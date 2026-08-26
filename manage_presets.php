<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Handles API-preset actions from the FilPass site settings page.
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());
require_sesskey();

$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_filpass_settings']);
$action = required_param('local_filpass_presetaction', PARAM_ALPHA);
$presetid = optional_param('local_filpass_presetid', 0, PARAM_INT);
$setasdefault = optional_param('local_filpass_setasdefault', 0, PARAM_BOOL);
$server = optional_param('s_local_filpass_api_server', '', PARAM_URL);
$apikey = optional_param('s_local_filpass_api_key', '', PARAM_RAW_TRIMMED);
$apisecret = optional_param('s_local_filpass_api_secret', '', PARAM_RAW_TRIMMED);

try {
    switch ($action) {
        case 'apply':
            \local_filpass\service\preset_service::apply_default_credentials($server, $apikey, $apisecret);
            $message = get_string('presetapplied', 'local_filpass');
            break;

        case 'save':
            $presetname = optional_param('local_filpass_presetname', '', PARAM_TEXT);
            $newpresetid = \local_filpass\service\preset_service::save_preset((object) [
                'name' => $presetname,
                'api_server' => $server,
                'api_key' => $apikey,
                'api_secret' => $apisecret,
            ]);

            if ($setasdefault) {
                \local_filpass\service\preset_service::activate_preset($newpresetid);
            } else {
                \local_filpass\service\preset_service::trigger_admin_settings_event();
            }

            $message = get_string('presetsaved', 'local_filpass');
            break;

        case 'delete':
            if ($presetid > 0 && !\local_filpass\service\preset_service::delete_preset($presetid)) {
                throw new moodle_exception('presetcannotdelete', 'local_filpass');
            }

            \local_filpass\service\preset_service::clear_default_credentials();
            $message = $presetid > 0
                ? get_string('presetdeleted', 'local_filpass')
                : get_string('defaultcleared', 'local_filpass');
            break;

        default:
            throw new moodle_exception('invalidrequest');
    }

    redirect($settingsurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
} catch (\Throwable $exception) {
    redirect($settingsurl, $exception->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
}
