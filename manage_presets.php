<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Site administration page for saved FilPass API presets.
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

admin_externalpage_setup('local_filpass_presets');

$editid = optional_param('editid', 0, PARAM_INT);
$activateid = optional_param('activateid', 0, PARAM_INT);
$deleteid = optional_param('deleteid', 0, PARAM_INT);
$pageurl = new moodle_url('/local/filpass/manage_presets.php');

if ($activateid || $deleteid) {
    require_sesskey();

    if ($activateid) {
        $activated = \local_filpass\service\preset_service::activate_preset($activateid);
        redirect(
            $pageurl,
            get_string($activated ? 'presetactivated' : 'presetnotfound', 'local_filpass'),
            null,
            $activated ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR
        );
    }

    $deleted = \local_filpass\service\preset_service::delete_preset($deleteid);
    redirect(
        $pageurl,
        get_string($deleted ? 'presetdeleted' : 'presetcannotdelete', 'local_filpass'),
        null,
        $deleted ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
    );
}

$preset = null;
if ($editid) {
    $preset = $DB->get_record('local_filpass_api_presets', ['id' => $editid], '*', MUST_EXIST);
}

$form = new \local_filpass\form\api_preset_form(null, ['isnew' => !$preset]);

if ($form->is_cancelled()) {
    redirect($pageurl);
}

if ($data = $form->get_data()) {
    try {
        \local_filpass\service\preset_service::save_preset($data);
        \local_filpass\service\preset_service::trigger_admin_settings_event();
        redirect($pageurl, get_string('presetsaved', 'local_filpass'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (\Throwable $exception) {
        \core\notification::error($exception->getMessage());
    }
}

if ($preset) {
    $form->set_data((object) [
        'presetid' => $preset->id,
        'name' => $preset->name,
        'api_server' => $preset->apiserver,
    ]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managepresets', 'local_filpass'));
echo html_writer::tag('p', get_string('managepresetsdesc', 'local_filpass'));

$form->display();

$presets = \local_filpass\service\preset_service::get_presets();
$activepresetid = (int) get_config('local_filpass', 'activepresetid');

echo $OUTPUT->heading(get_string('savedpresets', 'local_filpass'), 3);

if (!$presets) {
    echo $OUTPUT->notification(get_string('nopresets', 'local_filpass'), \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-filpass-presets';
    $table->head = [
        get_string('presetname', 'local_filpass'),
        get_string('api_server', 'local_filpass'),
        get_string('presetstatus', 'local_filpass'),
        get_string('actions', 'local_filpass'),
    ];

    foreach ($presets as $savedpreset) {
        $actions = [];

        if ((int) $savedpreset->id !== $activepresetid) {
            $activateurl = new moodle_url($pageurl, [
                'activateid' => $savedpreset->id,
                'sesskey' => sesskey(),
            ]);
            $activatebutton = new single_button(
                $activateurl,
                get_string('activatepreset', 'local_filpass'),
                'post'
            );
            $activatebutton->add_confirm_action(get_string('activatepresetconfirm', 'local_filpass', $savedpreset->name));
            $actions[] = $OUTPUT->render($activatebutton);
        }

        $editurl = new moodle_url($pageurl, ['editid' => $savedpreset->id]);
        $actions[] = $OUTPUT->single_button($editurl, get_string('editpreset', 'local_filpass'), 'get');

        if (\local_filpass\service\preset_service::can_delete_preset((int) $savedpreset->id)) {
            $deleteurl = new moodle_url($pageurl, [
                'deleteid' => $savedpreset->id,
                'sesskey' => sesskey(),
            ]);
            $deletebutton = new single_button(
                $deleteurl,
                get_string('deletepreset', 'local_filpass'),
                'post'
            );
            $deletebutton->add_confirm_action(get_string('deletepresetconfirm', 'local_filpass', $savedpreset->name));
            $actions[] = $OUTPUT->render($deletebutton);
        }

        $table->data[] = [
            s($savedpreset->name),
            s($savedpreset->apiserver),
            (int) $savedpreset->id === $activepresetid
                ? get_string('activepreset', 'local_filpass')
                : get_string('inactivepreset', 'local_filpass'),
            implode('', $actions),
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
