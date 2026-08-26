// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Controls the API-preset selector embedded in the FilPass settings page.
 *
 * @module     local_filpass/api_presets
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

    /**
     * Initializes preset selection and the save/delete confirmations.
     *
     * @param {Object} data Preset and current-default credential data.
     */
    function init(data) {
        var selector = $('#local-filpass-preset-select');
        var server = $('#id_s_local_filpass_api_server');
        var key = $('#id_s_local_filpass_api_key');
        var secret = $('#id_s_local_filpass_api_secret');
        var setDefault = $('#local-filpass-set-default');
        var presetName = $('#local-filpass-preset-name');
        var saveButton = $('#local-filpass-save-preset');
        var deleteButton = $('#local-filpass-delete-preset');
        var form = $('#adminsettings');
        var presets = data.presets || [];
        var defaultCredentials = data.defaultcredentials || {};

        if (!selector.length || !server.length || !key.length || !secret.length) {
            return;
        }

        /**
         * Returns the saved preset selected in the drop-down.
         *
         * @return {Object|null}
         */
        function selectedPreset() {
            var selectedId = parseInt(selector.val(), 10);

            return presets.find(function(preset) {
                return preset.id === selectedId;
            }) || null;
        }

        /**
         * Updates fields with a preset or with the original site default.
         *
         * @param {Object} credentials Credentials to display.
         */
        function showCredentials(credentials) {
            server.val(credentials.server || '');
            key.val(credentials.key || '');
            secret.val(credentials.secret || '');
        }

        /**
         * Marks the selector as saved only when the visible values match a preset.
         */
        function selectMatchingPreset() {
            var match = presets.find(function(preset) {
                return preset.server === server.val().replace(/\/$/, '')
                    && preset.key === key.val()
                    && preset.secret === secret.val();
            });

            selector.val(match ? match.id : '0');
            setDefault.prop('checked', Boolean(match && match.id === selector.data('default-preset-id')));
        }

        selector.on('change', function() {
            var preset = selectedPreset();

            showCredentials(preset || defaultCredentials);
            setDefault.prop('checked', Boolean(preset && preset.id === selector.data('default-preset-id')));
        });

        server.add(key).add(secret).on('input', selectMatchingPreset);

        saveButton.on('click', function(event) {
            var name = window.prompt(saveButton.data('name-prompt'));

            if (!name || !name.trim()) {
                event.preventDefault();
                return;
            }

            presetName.val(name.trim());
        });

        deleteButton.on('click', function(event) {
            if (!window.confirm(deleteButton.data('confirm-message'))) {
                event.preventDefault();
            }
        });

        form.on('reset', function() {
            window.setTimeout(function() {
                selector.val(selector.data('initial-preset-id'));
                setDefault.prop('checked', selector.data('initial-preset-id') > 0);
                presetName.val('');
            }, 0);
        });
    }

    return {
        init: init
    };
});
