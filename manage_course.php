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
 * Course-level management page for the FilPass plugin.
 *
 * Teachers or managers can enable the integration for a course, choose a target
 * FilPass batch, and trigger a debug upload to verify the connection.
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// /local/filpass/manage_course.php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/filpass/edit_form.php');

$courseid = required_param('id', PARAM_INT);

/** @var moodle_database $DB */
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// The page is protected at both the authentication and authorization levels so only
// eligible course managers can change FilPass integration settings for a course.
require_login($course);
$context = context_course::instance($courseid);

/** @var context $context */
require_capability('moodle/course:update', $context);

/** @var moodle_page $PAGE */
$PAGE->set_url(new moodle_url('/local/filpass/manage_course.php', ['id' => $courseid]));
$PAGE->set_title(get_string('course_settings_title', 'local_filpass'));
$PAGE->set_heading($course->fullname);

// The course page retrieves the available FilPass batches up front so the form can present
// a valid selection list without requiring the admin to enter batch IDs manually.
$client = new \local_filpass\api_client();
$batches = $client->get_batches();
// debugging("FilPass course page loaded available batches: " . print_r($batches, true), DEBUG_DEVELOPER);

$course_filpass_config = $DB->get_record(
    'local_filpass_courses',
    ['courseid' => $courseid]
);

if ($course_filpass_config) {
    $current_enabled = (int) $course_filpass_config->enabled;
    $current_batch = $course_filpass_config->batchid;
} else {
    // Temporary fallback for pre-database course settings.
    $current_enabled = get_config('local_filpass', 'course_' . $courseid . '_enabled') ?? 0;
    $current_batch = get_config('local_filpass', 'course_' . $courseid . '_batch_id') ?: '';
}

// The form is pre-populated from the saved course-level configuration so the page reflects
// the current state before a developer or manager makes any changes.
$form_data = new stdClass();
$form_data->id = $courseid;
$form_data->enable_filpass = $current_enabled;
$form_data->filpass_batch_id = $current_batch;

$form = new local_filpass_course_form(null, [
	'batches' => $batches,
	'courseid' => $courseid
]);

$form->set_data($form_data);

// The controller handles two separate workflows: saving the course mapping and running a
// one-off debug upload. Keeping both in the same page makes administration straightforward.
if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
} else if ($data = $form->get_data()) {

	if (!empty($data->debug_send_btn)) {

		// The debug path is intentionally isolated from the normal save flow. It allows a
		// developer to validate the API connection with a sample file without changing the course configuration.
		file_save_draft_area_files(
			$data->test_file_filemanager,
			$context->id,
			'local_filpass',
			'debug_test',
			0,
			['maxbytes' => 10485760, 'maxfiles' => 1, 'accepted_types' => ['.pdf']]
		);

		$fs = get_file_storage();

		// The uploaded debug file is read from Moodle's draft area so the same flow can be reused
		// regardless of whether the file was selected through the form or uploaded previously.
		$files = $fs->get_area_files($context->id, 'local_filpass', 'debug_test', 0, 'id DESC', false);

		if (!empty($files)) {
			$file = reset($files);

			$file_name = $file->get_filename();

			global $CFG;
			$temp_dir = $CFG->tempdir . '/local_filpass';

			if (!file_exists($temp_dir)) {
				@mkdir($temp_dir, 0777, true);
			}

			$temp_path = $temp_dir . '/' . $file_name;

			file_put_contents($temp_path, $file->get_content());

			// These debug statements are useful when validating the form payload and the temporary
			// file path during manual API testing.
			// debugging('FilPass debug upload submission data: ' . print_r($data, true), DEBUG_DEVELOPER);
			// debugging('FilPass debug upload temporary file prepared at ' . $temp_path . ' with filename ' . $file_name . '.', DEBUG_DEVELOPER);

			$result = $client->upload_bulk_data(
				$data->filpass_batch_id ?? 'test_batch',
				$data->first_name ?? 'John (Default)',
				$data->last_name ?? 'Doe',
				$data->email ?? 'test-rewardee@filpass.ph',
				$temp_path,
				$file_name
			);

			@unlink($temp_path);

			// debugging('FilPass debug upload API response: ' . print_r($result, true), DEBUG_DEVELOPER);

			if ($result->status === 'success') {
				redirect(
					new moodle_url('/local/filpass/manage_course.php', ['id' => $courseid]),
					get_string('debug_success_msg', 'local_filpass'),
					\core\output\notification::NOTIFY_SUCCESS
				);
			} else {
				\core\notification::error(get_string('debug_failed_msg', 'local_filpass'));
				redirect(
					new moodle_url('/local/filpass/manage_course.php', ['id' => $courseid]),
				);
			}
		} else {
			\core\notification::error("No file was found in the draft area. Please re-upload.");
			redirect(
				new moodle_url('/local/filpass/manage_course.php', ['id' => $courseid]),
			);
		}

	} else {
		// The normal save path persists the course settings so the observer can use them later
		// whenever a certificate is issued for this course.
		// debugging('FilPass course settings form submission data: ' . print_r($data, true), DEBUG_DEVELOPER);

		$enabled = !empty($data->enable_filpass) ? 1 : 0;
		$batchid = $enabled ? ($data->filpass_batch_id ?? '') : '';
		$now = time();

		$existing = $DB->get_record(
			'local_filpass_courses',
			['courseid' => $courseid]
		);

		global $USER;
		if ($existing) {
			$existing->enabled = $enabled;
			$existing->batchid = $batchid;
			$existing->timemodified = $now;
			$existing->usermodified = $USER->id;

			$DB->update_record('local_filpass_courses', $existing);
		} else {
			$DB->insert_record('local_filpass_courses', (object) [
				'courseid' => $courseid,
				'enabled' => $enabled,
				'batchid' => $batchid,
				'timecreated' => $now,
				'timemodified' => $now,
				'usermodified' => $USER->id,
			]);
		}

		// Temporary compatibility with old config-based code.
		// You can remove this later once everything reads from local_filpass_courses.
		set_config('course_' . $courseid . '_enabled', $enabled, 'local_filpass');
		set_config('course_' . $courseid . '_batch_id', $batchid, 'local_filpass');

		try {
			/** @var stdClass $USER */
			$course_settings_event = \local_filpass\event\course_settings_changed::create([
				'userid' => $USER->id,
				'context' => $context,
				'courseid' => $courseid,
			]);
			$course_settings_event->trigger();
		} catch (\Exception $e) {
			error_log('FilPass course settings change event warning: ' . $e->getMessage());
		}



		redirect(
			new moodle_url('/local/filpass/manage_course.php', ['id' => $courseid]),
			get_string('settings_saved', 'local_filpass'),
			\core\output\notification::NOTIFY_SUCCESS
		);
	}
}

/** @var core_renderer $OUTPUT */
echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();