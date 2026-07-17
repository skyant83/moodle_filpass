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

use core\check\performance\debugging;

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
    if (has_capability('moodle/course:visibility', $context)) {
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
    /** @var moodle_page $PAGE */
    global $PAGE, $USER;

    // This hook is intentionally scoped to the custom certificate view page so the plugin
    // does not affect unrelated Moodle pages or inject UI elements where they are not needed.
    if ($PAGE->pagetype !== 'mod-customcert-view') {
        return;
    }

    $courseid = $PAGE->course->id;
    $first_name = $USER->firstname;

    $context = context_course::instance($courseid);

    /** @var context $context */
    if (has_capability('mod/customcert:addinstance', $context)) {
        $first_name = "[User's Firstname will appear here]";
    }

    $first_name = addslashes($first_name);

    // The notice is shown only when the course has explicitly enabled the integration.
    // This keeps the UI quiet on pages that are not part of the FilPass workflow.
    $is_enabled = get_config('local_filpass', 'course_' . $courseid . '_enabled');
    if (!$is_enabled) {
        return; // Silent exit—show absolutely nothing on the page layout
    }

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
                var noticeHtml = ' \
                    <div class=\"local-filpass-view-notice style=\"background-color: #e1f5fe; border-left: 4px solid #0288d1; padding: 15px; margin-bottom: 20px; border-radius: 4px;\"> \
                        <h3 style=\"color: #01579b; margin-top: 0;\">📥 Important Notice: Your Certificate Delivery</h3> \
                        <p>Upon generating your certificate, <strong>two separate emails</strong> will be dispatched to your registered inbox to ensure both immediate access and long-term verification security:</p> \
                        <ul style=\"margin-bottom: 15px; padding-left: 20px;\"> \
                            <li style=\"margin-bottom: 8px;\"> \
                                <strong>Email 1: Secure Verification Copy (From FilPass)</strong><br> \
                                This contains a cryptographic, tamper-proof copy of your certificate securely and automatically synchronized with their tracking systems. It includes an official verification link that potential employers or institutions can use to instantly authenticate your credentials. This email will also provide a secure activation link allowing you to create a free FilPass account to access, manage, and share your digital badges at any time from their portal. Please be aware that only this version of the certificate is verifiable through FilPass. \
                                The email you receive will appear with the subject line: \"<strong>UpskillNowPH has released your Tamperproof Verifiable Credential, " . $first_name . "</strong>\"\
                            </li> \
                            <li> \
                                <strong>Email 2: Base Reference Copy (From UpSkillNowPH)</strong><br> \
                                This contains your direct, standard certificate document sent from our local platform. Please note that this file is intended for personal reference and printing only, and <em>cannot</em> be verified through the external FilPass cryptographic security systems. \
                            </li> \
                        </ul> \
                        <p style=\"font-size: 13px; color: #555555; margin-bottom: 0; font-style: italic;\"><strong>Tip:</strong> If you do not see both messages within a few minutes of generation, please check your Spam or Junk Mail folders to ensure the institutional delivery was not flagged by your email provider. Please contact the site admin if the issue persists.</p> \
                    </div> \
                ';

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