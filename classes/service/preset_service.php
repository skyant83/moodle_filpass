<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Manages saved FilPass API credential presets.
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_filpass\service;

defined('MOODLE_INTERNAL') || die();

class preset_service {
    /**
     * Returns all presets without their credentials.
     *
     * @return array
     */
    public static function get_presets(): array {
        global $DB;

        return $DB->get_records(
            'local_filpass_api_presets',
            null,
            'name ASC',
            'id, name, apiserver, timecreated, timemodified, usermodified'
        );
    }

    /**
     * Returns a preset's decrypted credentials for use in an API request.
     *
     * @param int $presetid
     * @return object|null
     */
    public static function get_credentials(int $presetid): ?object {
        global $DB;

        if ($presetid <= 0) {
            return null;
        }

        $preset = $DB->get_record('local_filpass_api_presets', ['id' => $presetid], '*', IGNORE_MISSING);

        if (!$preset) {
            return null;
        }

        return (object) [
            'server' => rtrim($preset->apiserver, '/'),
            'key' => \core\encryption::decrypt($preset->apikey),
            'secret' => \core\encryption::decrypt($preset->apisecret),
        ];
    }

    /**
     * Indicates whether a preset exists.
     *
     * @param int $presetid
     * @return bool
     */
    public static function preset_exists(int $presetid): bool {
        global $DB;

        return $presetid > 0 && $DB->record_exists('local_filpass_api_presets', ['id' => $presetid]);
    }

    /**
     * Returns select-menu options for course-level preset overrides.
     *
     * @return array
     */
    public static function get_preset_options(): array {
        $options = [0 => get_string('usesitedefaultpreset', 'local_filpass')];

        foreach (self::get_presets() as $preset) {
            $options[$preset->id] = $preset->name;
        }

        return $options;
    }

    /**
     * Creates or updates a saved preset without exposing existing credentials.
     *
     * @param object $data
     * @return int The preset ID.
     */
    public static function save_preset(object $data): int {
        global $DB, $USER;

        $presetid = !empty($data->presetid) ? (int) $data->presetid : 0;
        $existing = $presetid
            ? $DB->get_record('local_filpass_api_presets', ['id' => $presetid], '*', MUST_EXIST)
            : null;
        $name = trim(clean_param($data->name, PARAM_TEXT));

        $server = rtrim(clean_param($data->api_server, PARAM_URL), '/');

        if ($name === '') {
            throw new \moodle_exception('presetnamerequired', 'local_filpass');
        }

        if ($server === '') {
            throw new \moodle_exception('presetserverrequired', 'local_filpass');
        }

        if ($DB->record_exists_select(
            'local_filpass_api_presets',
            'name = :name AND id <> :id',
            ['name' => $name, 'id' => $presetid]
        )) {
            throw new \moodle_exception('presetnameexists', 'local_filpass');
        }

        $apikey = trim((string) ($data->api_key ?? ''));
        $apisecret = trim((string) ($data->api_secret ?? ''));

        if (!$existing && ($apikey === '' || $apisecret === '')) {
            throw new \moodle_exception('presetcredentialsrequired', 'local_filpass');
        }

        if (($apikey !== '' || $apisecret !== '') && !\core\encryption::key_exists()) {
            \core\encryption::create_key();
        }

        $record = $existing ?: (object) [
            'timecreated' => time(),
        ];
        $record->name = $name;
        $record->apiserver = $server;
        $record->apikey = $apikey === '' && $existing
            ? $existing->apikey
            : \core\encryption::encrypt($apikey);
        $record->apisecret = $apisecret === '' && $existing
            ? $existing->apisecret
            : \core\encryption::encrypt($apisecret);
        $record->timemodified = time();
        $record->usermodified = $USER->id;

        if ($existing) {
            $DB->update_record('local_filpass_api_presets', $record);
            return $existing->id;
        }

        return (int) $DB->insert_record('local_filpass_api_presets', $record);
    }

    /**
     * Activates a preset as the site-wide default connection.
     *
     * @param int $presetid
     * @return bool
     */
    public static function activate_preset(int $presetid): bool {
        $credentials = self::get_credentials($presetid);

        if (!$credentials) {
            return false;
        }

        set_config('activepresetid', $presetid, 'local_filpass');
        self::trigger_admin_settings_event();

        return true;
    }

    /**
     * Indicates whether a preset can be removed without breaking active configuration.
     *
     * @param int $presetid
     * @return bool
     */
    public static function can_delete_preset(int $presetid): bool {
        global $DB;

        return $presetid !== (int) get_config('local_filpass', 'activepresetid')
            && !$DB->record_exists('local_filpass_courses', ['presetid' => $presetid]);
    }

    /**
     * Deletes an unused, inactive preset.
     *
     * @param int $presetid
     * @return bool
     */
    public static function delete_preset(int $presetid): bool {
        global $DB;

        if (!self::can_delete_preset($presetid)) {
            return false;
        }

        return $DB->delete_records('local_filpass_api_presets', ['id' => $presetid]);
    }

    /**
     * Emits the existing audit event for a preset change or activation.
     *
     * @return void
     */
    public static function trigger_admin_settings_event(): void {
        global $USER;

        try {
            $event = \local_filpass\event\admin_settings_changed::create([
                'userid' => $USER->id,
                'context' => \context_system::instance(),
            ]);
            $event->trigger();
        } catch (\Throwable $exception) {
            debugging('FilPass preset settings event warning: ' . $exception->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
