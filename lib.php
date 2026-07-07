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
 * Entry points for the local FilPass integration.
 *
 * This plugin extends Moodle navigation and certificate-related pages so that
 * course managers can configure FilPass integration and receive a small notice
 * when the feature is active for a specific course.
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// /local/filpass/lib.php

defined('MOODLE_INTERNAL') || die();

/**
 * Adds the FilPass course configuration link to course navigation.
 *
 * @param navigation_node $navigation The course navigation object.
 * @param stdClass $course The course being rendered.
 * @param context $context The course context.
 * @return void
 */
function local_filpass_extend_navigation_course($navigation, $course, $context) {
    // Only course managers should be able to change FilPass settings, so the link is
    // gated behind the same capability used for course administration.
    if (has_capability('moodle/course:update', $context)) {
        $url = new moodle_url('/local/filpass/manage_course.php', ['id' => $course->id]);
        $navigation->add(
            get_string('course_settings_title', 'local_filpass'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'filpass_settings'
        );
    }
}

/**
 * Injects a notice into the custom certificate view page when FilPass is enabled.
 *
 * This hook is used to provide a lightweight UI indicator on certificate pages
 * based on whether a course has a mapped FilPass batch.
 *
 * @param global_navigation $navigation The global navigation object.
 * @return void
 */
function local_filpass_extend_navigation(global_navigation $navigation) {
    global $PAGE;

    // This hook is intentionally scoped to the custom certificate view page so the plugin
    // does not affect unrelated Moodle pages or inject UI elements where they are not needed.
    if ($PAGE->pagetype !== 'mod-customcert-view') {
        return;
    }

    $courseid = $PAGE->course->id;
    $batch_id = get_config('local_filpass', 'course_' . $courseid . '_batch_id');

    // The notice is shown only when the course has explicitly enabled the integration.
    // This keeps the UI quiet on pages that are not part of the FilPass workflow.
    $is_enabled = get_config('local_filpass', 'course_' . $courseid . '_enabled');
    if (!$is_enabled) {
        return; // Silent exit—show absolutely nothing on the page layout
    }

    // The notice content changes depending on whether a batch has been mapped. This gives
    // instructors a quick visual cue about whether the course is ready for issuance or needs attention.
    if ($batch_id) {
        $title = 'FilPass Secure Issuance Enabled';
        $text = 'Test Text';
        $bootstrap_class = 'alert-info';
    } else {
        $title = 'FilPass Tracking Warning';
        $text = 'Test Text2';
        $bootstrap_class = 'alert-warning';
    }

    // The message text is escaped before it is embedded into JavaScript so the injected
    // notice remains safe to render inside the browser page context.
    $title_js = addslashes($title);
    $text_js = addslashes($text);

    // The UI update is performed client-side with a small JavaScript snippet so the notice
    // can be injected after the page has rendered without altering the server-side layout logic.
    $js_code = "
        require(['jquery'], function($) {
            $(document).ready(function() {
                // This guards against duplicate notices if the page content is refreshed in place.
                if ($('.local-filpass-view-notice').length) {
                    return;
                }

                // The notice markup is assembled here so the injected alert can be styled consistently.
                var noticeHtml = '<div class=\"local-filpass-view-notice m-b-1\">' +
                    '<div class=\"alert {$bootstrap_class}\" role=\"alert\">' +
                    '<strong>' + '{$title_js}: ' + '</strong>' + '{$text_js}' +
                    '</div>' +
                    '</div>';

                // The notice is placed just above the certificate action button when that button exists.
                var \$targetButton = $('.singlebutton').first();
                if (\$targetButton.length) {
                    \$targetButton.before(noticeHtml);
                } else {
                    // If the certificate action button is not present, the notice is appended to the main content region.
                    $('#region-main').prepend(noticeHtml);
                }
            });
        });
    ";

    $PAGE->requires->js_init_code($js_code);
}