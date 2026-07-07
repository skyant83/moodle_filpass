# FilPass Moodle Plugin

## Overview
This local Moodle plugin connects the custom certificate workflow to the FilPass platform. Its purpose is to allow a certificate issued in Moodle to be prepared as a PDF and submitted to a configured FilPass batch for downstream issuance and tracking.

## What the plugin does
- Adds a course-level settings page where course managers can enable or disable the integration.
- Stores the FilPass API endpoint and authentication credentials in Moodle site administration.
- Allows each course to be mapped to a specific FilPass batch.
- Hooks into the custom certificate issue event so certificate issuance can trigger an external upload automatically.
- Generates the certificate PDF from the active custom certificate template and sends it to FilPass with recipient data.
- Emits dedicated Moodle events for upload start, successful upload, failed upload, admin settings changes, and course settings changes.
- Provides a debug interface on the course management page for manual upload testing.

## Main workflow
1. An administrator configures the FilPass API connection in site settings.
2. A course manager opens the FilPass course settings page and enables the integration for that course.
3. The manager selects the target FilPass batch to associate with the course.
4. When a student receives a certificate, Moodle raises the custom certificate issue event.
5. The observer checks whether the course is enabled and whether a batch mapping exists.
6. The plugin retrieves the certificate recipient details and the relevant custom certificate data.
7. The certificate PDF is generated from the active template and written to a temporary file.
8. The plugin sends the certificate and recipient metadata to FilPass through the API client.
9. The plugin emits a start event before the attempt, then either a success or failure event based on the API response.
10. Saving course-level or site-level settings also emits dedicated change events for auditability.

## Architecture at a glance
- [lib.php](lib.php) adds the course configuration link in navigation and injects a small notice on the certificate view page when FilPass is active for the course.
- [settings.php](settings.php) stores the FilPass base URL, API key, and API secret in site-wide configuration and emits a settings-change event when those values are saved.
- [manage_course.php](manage_course.php) handles the course-level administration UI, including saving the course mapping and sending a manual debug upload.
- [edit_form.php](edit_form.php) defines the Moodle form used to configure the integration and the debug test fields.
- [classes/api_client.php](classes/api_client.php) contains the HTTP wrapper for authentication, batch lookup, and submission of certificate data to FilPass.
- [classes/observer.php](classes/observer.php) listens for the certificate issue event and coordinates the PDF generation and upload workflow.
- [classes/event/begin_filpass_upload.php](classes/event/begin_filpass_upload.php) defines the event raised before an upload attempt begins.
- [classes/event/uploaded_to_filpass.php](classes/event/uploaded_to_filpass.php) defines the event raised when an upload succeeds.
- [classes/event/upload_failed_filpass.php](classes/event/upload_failed_filpass.php) defines the event raised when an upload fails.
- [classes/event/admin_settings_changed.php](classes/event/admin_settings_changed.php) defines the event raised when site-level settings are updated.
- [classes/event/course_settings_changed.php](classes/event/course_settings_changed.php) defines the event raised when course-level settings are updated.
- [db/events.php](db/events.php) registers the observer with Moodle so it runs automatically when certificates are issued.

## Notes for developers
- This plugin depends on the custom certificate module in Moodle and expects certificate issue events to be available.
- The integration is designed around batch-based issuance and requires a valid FilPass batch ID to be configured for the course.
- The plugin includes a manual debug path that can be used to test the API connection and upload workflow from the course settings page without changing the main live configuration.
- The upload lifecycle is now observable through Moodle events, which makes the workflow easier to audit and troubleshoot.
- Debugging output is intentionally kept available but trimmed down to reduce log noise. The relevant logging calls are still present in the code and can be re-enabled by uncommenting them when more detailed diagnostics are needed.

## Debugging and log noise
The plugin includes several debugging statements that help trace the login flow, batch retrieval, payload construction, and upload response. These were intentionally reduced in normal use to keep Moodle logs cleaner, but they remain available for future troubleshooting.

If a developer needs deeper visibility into the flow, they can temporarily uncomment the debugging calls in:
- [classes/api_client.php](classes/api_client.php)
- [manage_course.php](manage_course.php)

## Event reference
The plugin now emits the following Moodle events during normal operation:
- Begin upload: raised immediately before the observer attempts to submit certificate data to FilPass.
- Upload success: raised when the API response indicates a successful submission.
- Upload failure: raised when the upload fails or when no usable PDF could be generated.
- Admin settings changed: raised when site-level FilPass settings are saved.
- Course settings changed: raised when a course’s FilPass settings are saved.
