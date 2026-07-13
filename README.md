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
<pre>
local/
└── filpass/
	├── <a href="amd/">amd/</a>
	│   ├── <a href="amd/build/">build/</a>
	│   │   ├── <a href="amd/build/test_connection.min.js">test_connection.min.js</a>
	│   │   └── <a href="amd/build/test_connection.min.js.map">test_connection.min.js.map</a>
	│   └── <a href="amd/src/">src/</a>
	│       └── <a href="amd/src/test_connection.js">test_connection.js</a>
	├── <a href="classes/">classes/</a>
	│   ├── <a href="classes/api/">api/</a>
	│   │   └── <a href="classes/api/response/">response/</a>
	│   │       └── <a href="classes/api/response/login_response_data.php">login_response_data.php</a>
	│   ├── <a href="classes/event/">event/</a>
	│   │   ├── <a href="classes/event/admin_settings_changed.php">admin_settings_changed.php</a>
	│   │   ├── <a href="classes/event/begin_filpass_upload.php">begin_filpass_upload.php</a>
	│   │   ├── <a href="classes/event/course_settings_changed.php">course_settings_changed.php</a>
	│   │   ├── <a href="classes/event/upload_failed_filpass.php">upload_failed_filpass.php</a>
	│   │   └── <a href="classes/event/uploaded_to_filpass.php">uploaded_to_filpass.php</a>
	│   ├── <a href="classes/admin_setting_password.php">admin_setting_password.php</a>
	│   ├── <a href="classes/admin_setting_text.php">admin_setting_text.php</a>
	│   ├── <a href="classes/api_client.php">api_client.php</a>
	│   └── <a href="classes/observer.php">observer.php</a>
	├── <a href="db/">db/</a>
	│   └── <a href="db/events.php">events.php</a>
	├── <a href="lang/">lang/</a>
	│   └── <a href="lang/en/">en/</a>
	│       └── <a href="lang/en/local_filpass.php">local_filpass.php</a>
	├── <a href="edit_form.php">edit_form.php</a>
	├── <a href="lib.php">lib.php</a>
	├── <a href="manage_course.php">manage_course.php</a>
	├── <a href="settings.php">settings.php</a>
	├── <a href="styles.css">styles.css</a>
	├── <a href="test_connection.php">test_connection.php</a>
	├── <a href="test_observer.php">test_observer.php</a>
	└── <a href="version.php">version.php</a>
</pre>

- [amd/build/test_connection.min.js](amd/build/test_connection.min.js) is the compiled AMD JavaScript loaded by Moodle in production for the FilPass connection-test UI.
- [amd/build/test_connection.min.js.map](amd/build/test_connection.min.js.map) is the source map for the compiled connection-test JavaScript, useful when debugging the minified AMD file in the browser.
- [amd/src/test_connection.js](amd/src/test_connection.js) defines the admin-page JavaScript used by the FilPass connection-test button. It sends the AJAX request to Moodle, displays the returned JSON in the read-only response textarea, and handles connection errors.
- [classes/api/response/login_response_data.php](classes/api/response/login_response_data.php) defines the typed data object for the FilPass login response, including the returned authentication token.
- [classes/event/begin_filpass_upload.php](classes/event/begin_filpass_upload.php) defines the event raised before an upload attempt begins.
- [classes/event/uploaded_to_filpass.php](classes/event/uploaded_to_filpass.php) defines the event raised when an upload succeeds.
- [classes/event/upload_failed_filpass.php](classes/event/upload_failed_filpass.php) defines the event raised when an upload fails.
- [classes/event/admin_settings_changed.php](classes/event/admin_settings_changed.php) defines the event raised when site-level settings are updated.
- [classes/event/course_settings_changed.php](classes/event/course_settings_changed.php) defines the event raised when course-level settings are updated.
- [classes/admin_setting_password.php](classes/admin_setting_password.php) defines the custom admin password setting used to store sensitive FilPass credentials while supporting change detection for settings events.
- [classes/admin_setting_text.php](classes/admin_setting_text.php) defines the custom admin text setting used for FilPass configuration values while supporting change detection for settings events.
- [classes/api_client.php](classes/api_client.php) contains the HTTP wrapper for authentication, batch lookup, and submission of certificate data to FilPass.
- [classes/observer.php](classes/observer.php) listens for the certificate issue event and coordinates the PDF generation and upload workflow.
- [db/events.php](db/events.php) registers the observer with Moodle so it runs automatically when certificates are issued.
- [lang/en/local_filpass.php](lang/en/local_filpass.php) contains the English language strings used throughout the plugin, including labels, settings descriptions, event names, and connection-test messages.
- [edit_form.php](edit_form.php) defines the Moodle form used to configure the integration and the debug test fields.
- [lib.php](lib.php) adds the course configuration link in navigation and injects a small notice on the certificate view page when FilPass is active for the course.
- [manage_course.php](manage_course.php) handles the course-level administration UI, including saving the course mapping and sending a manual debug upload.
- [settings.php](settings.php) stores the FilPass base URL, API key, and API secret in site-wide configuration and emits a settings-change event when those values are saved. Includes a section to test login credentials.
- [styles.css](styles.css) contains plugin-specific styling for the FilPass course settings, debug interface, notices, and admin UI elements.
- [test_connection.php](test_connection.php) provides the protected AJAX endpoint used by the site administration connection-test button. It validates the Moodle session, calls the FilPass login flow, and returns a safe JSON response with the token redacted.
- [test_observer.php](test_observer.php) provides a manual test entry point for exercising the observer/upload workflow during development or debugging.
- [version.php](version.php) declares the plugin version, required Moodle version, component name, maturity, and release metadata used by Moodle during installation and upgrades.

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
**