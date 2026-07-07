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
 * Moodle form used to configure FilPass integration for a single course.
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

// /local/filpass/edit_form.php

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class local_filpass_course_form extends moodleform {
    /**
     * Defines the form controls used to enable FilPass, select a batch, and run debug uploads.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $batches = $this->_customdata['batches'];

        $courseid = $this->_customdata['courseid'];
        $mform->addElement('hidden', 'id', $courseid);
        $mform->setType('id', PARAM_INT);

        // The form is split into a configuration section and a diagnostic section. The
        // diagnostic section remains hidden until the integration is enabled to reduce clutter.
        $mform->addElement('header', 'config_header', get_string('batch_configuration', 'local_filpass'));

        $mform->addElement('advcheckbox', 'enable_filpass', 'Enable FilPass Integration', '', null, [0, 1]);
        $mform->setType('enable_filpass', PARAM_INT);
        $mform->setDefault('enable_filpass', 0);

        $options = ['' => get_string('select_or_create', 'local_filpass')];
        foreach ($batches as $batch) {
            $options[$batch->batchNumber] = $batch->batchName . " (ID: " . $batch->batchNumber . " )";
        }

        $mform->addElement('select', 'filpass_batch_id', get_string('mapped_batch', 'local_filpass'), $options);
        $mform->setType('filpass_batch_id', PARAM_ALPHANUMEXT);
        $mform->hideIf('filpass_batch_id', 'enable_filpass', 'eq', 0);

        // The diagnostics section is kept separate from the main configuration fields so the
        // manual test workflow does not distract from the core batch mapping behavior.
        $mform->addElement('header', 'debug_header', get_string('debugging_suite', 'local_filpass'));
        $mform->hideIf('debug_header', 'enable_filpass', 'eq', 0);

        $mform->addElement('html', '<h4>' . get_string('debug_form_fields', 'local_filpass') . '</h4>');

        $mform->addElement('text', 'first_name', get_string('debug_first_name','local_filpass'));
        $mform->setType('first_name', PARAM_ALPHANUMEXT);
        $mform->hideIf('first_name', 'enable_filpass', 'eq', 0);

        $mform->addElement('text','last_name', get_string('debug_last_name','local_filpass'));
        $mform->setType('last_name', PARAM_ALPHANUMEXT);
        $mform->hideIf('last_name', 'enable_filpass', 'eq', 0);

        $mform->addElement('text','email', get_string('debug_email','local_filpass'));
        $mform->setType('email', PARAM_EMAIL);
        $mform->hideIf('email', 'enable_filpass', 'eq', 0);

        // The file upload field is intentionally limited to a single file because this form is
        // used for a manual smoke test rather than a full document management workflow.
        $filemanager_options = [
            'maxbytes' => 10485760, // 10MB
            'accepted_types' => ['.pdf', '.png', '.jpg', '.jpeg'],
            'maxfiles' => 1, // Only allow 1 verification test document at a time
        ];

        // The upload options are stored on the form object so they can be reused later when the
        // draft area is prepared in data_preprocessing.
        $this->_customdata['filemanager_options'] = $filemanager_options;

        $mform->addElement('filemanager', 'test_file_filemanager', get_string('upload_test_doc', 'local_filpass'), null, $filemanager_options);
        $mform->hideIf('test_file_filemanager', 'enable_filpass', 'eq', 0);

        // The temporary storage path is shown to developers so they can verify where the
        // uploaded debug file is staged before the API request is made.
        $log_location = '/var/moodledata/temp/local_filpass';
        $mform->addElement('static', 'log_info', get_string('temp_location', 'local_filpass'), "<code>" . $log_location . "</code>");
        $mform->hideIf('log_info', 'enable_filpass', 'eq', 0);

        $mform->addElement('submit', 'debug_send_btn', get_string('trigger_debug_send', 'local_filpass'));
        $mform->hideIf('debug_send_btn', 'enable_filpass', 'eq', 0);

        // The standard submit controls are added last so the form stays consistent with
        // Moodle's default action button layout.
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Prepares the file manager draft area for the debug upload field.
     *
     * @param array $default_values The default values passed into the form.
     * @return void
     */
    public function data_preprocessing(&$default_values) {
        if ($this->is_submitted()) {
            return;
        }

        $courseid = $this->_customdata['courseid'];
        $context = context_course::instance($courseid);
        $filemanager_options = $this->_customdata['filemanager_options'];

        // Draft area preparation is used here so the uploaded file can be handled through
        // Moodle's file API before it is copied into a temporary path for the debug upload.
        $draftitemid = file_get_submitted_draft_itemid('test_file_filemanager');
        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            'local_filpass',
            'debug_test',
            0,
            $filemanager_options
        );

        // Map the draft ID back into the form field data structure
        $default_values['test_file_filemanager'] = $draftitemid;
    }
}