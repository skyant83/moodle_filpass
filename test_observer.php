<?php
// /local/filpass/test_observer.php
define('CLI_SCRIPT', true); // FORCE ENVIRONMENT TO OPERATE IN PURE CLI MODE

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php'); // Include Moodle's core CLI layout helper library
require_once($CFG->dirroot . '/local/filpass/classes/api_client.php');
require_once($CFG->dirroot . '/local/filpass/classes/observer.php');

// Verify execution is coming from a terminal shell, not a web browser URL request
cli_heading('FILPASS OBSERVER BACKGROUND PIPELINE DEBUGGER');

global $DB;

// This script acts as a lightweight CLI harness for testing the observer outside the browser.
// It builds the same event context Moodle would use so the observer logic can be exercised directly.
$courseid = 51; // Target testing course ID
cli_writeln("Target Course ID: {$courseid}");

$batch_id = get_config('local_filpass', 'course_' . $courseid . '_batch_id');
if (!$batch_id) {
    cli_error("ERROR: Course ID {$courseid} does not have a saved FilPass Batch ID mapping.");
}
cli_writeln("Mapped Batch ID: {$batch_id}");

// Locate a valid certificate issue reference entry
$issue = $DB->get_record_sql("
    SELECT ci.*, c.course
    FROM {customcert_issues} ci
    JOIN {customcert} c ON c.id = ci.customcertid
    WHERE c.course = ?
    LIMIT 1",
    [$courseid]
);

if (!$issue) {
    cli_error("ERROR: No student certificates found in DB for this course. Complete the activity as a student first.");
}

cli_writeln("Found Target Student User ID: {$issue->userid} | Issue ID: {$issue->id}");
cli_writeln("Simulating background event hook initialization...");
cli_writeln("----------------------------------------------------------------------");

$eventparams = [
    'contextid' => context_course::instance($courseid)->id,
    'objectid'  => $issue->id,
    'userid'    => $issue->userid,
    'courseid'  => $courseid,
    'other'     => ['customcertid' => $issue->customcertid]
];

$event = \mod_customcert\event\issue_created::create($eventparams);
$event->trigger();