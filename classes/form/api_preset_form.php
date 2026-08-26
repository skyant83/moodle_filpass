<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Form for creating and editing saved FilPass API presets.
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_filpass\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class api_preset_form extends \moodleform {
    /**
     * Defines the preset fields.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $isnew = !empty($this->_customdata['isnew']);

        $mform->addElement('hidden', 'presetid');
        $mform->setType('presetid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('presetname', 'local_filpass'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'api_server', get_string('api_server', 'local_filpass'));
        $mform->setType('api_server', PARAM_URL);
        $mform->addRule('api_server', null, 'required', null, 'client');

        $mform->addElement('passwordunmask', 'api_key', get_string('api_key', 'local_filpass'));
        $mform->setType('api_key', PARAM_RAW_TRIMMED);
        $mform->addElement('passwordunmask', 'api_secret', get_string('api_secret', 'local_filpass'));
        $mform->setType('api_secret', PARAM_RAW_TRIMMED);

        if ($isnew) {
            $mform->addRule('api_key', null, 'required', null, 'client');
            $mform->addRule('api_secret', null, 'required', null, 'client');
        } else {
            $mform->addElement('static', 'credentialnotice', '', get_string('presetcredentialnotice', 'local_filpass'));
        }

        $this->add_action_buttons(true, get_string('savepreset', 'local_filpass'));
    }

    /**
     * Performs server-side validation for new credentials.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (!empty($this->_customdata['isnew'])) {
            if (trim((string) ($data['api_key'] ?? '')) === '') {
                $errors['api_key'] = get_string('required');
            }
            if (trim((string) ($data['api_secret'] ?? '')) === '') {
                $errors['api_secret'] = get_string('required');
            }
        }

        return $errors;
    }
}
