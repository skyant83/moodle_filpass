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
 * FilPass connection-test controls
 *
 * @module     local_filpass/test_connection
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/config',
    'core/notification',
    'core/str'
], function($, Config, Notification, Str) {

    /**
     * Initialize the connection-test button.
     */
    function init() {

        var button = $('#local-filpass-test-button');
        var output = $('#local-filpass-test-output');

        if (!button.length) {
            return;
        }

        button.on('click', function() {

            button.prop('disabled', true);

            Str.get_string('testingconnection', 'local_filpass')
                .done(function(str) {
                    output.val(str);
                });

            $.ajax({
                url: Config.wwwroot + '/local/filpass/test_connection.php',
                type: 'POST',
                data: {
                    sesskey: Config.sesskey
                },
                success: function(data) {

                    if (typeof data === 'object') {
                        output.val(JSON.stringify(data, null, 2));
                    } else {
                        output.val(data);
                    }

                    button.prop('disabled', false);
                },
                error: function(xhr) {

                    output.val(xhr.responseText);

                    button.prop('disabled', false);

                    Notification.exception({
                        message: 'Connection failed'
                    });
                }
            });

        });
    }

    return {
        init: init
    };
});