# FilPass Moodle Plugin

## Overview
This local Moodle plugin connects the custom certificate workflow to the FilPass platform. Its purpose is to allow a certificate issued in Moodle to be prepared as a PDF and submitted to a configured FilPass batch for downstream issuance and tracking.

## What the plugin does

- Adds a course-level settings page where course managers can enable or disable the integration.
- Stores the FilPass API endpoint and authentication credentials in Moodle site administration.
- Allows each course to be mapped to a specific FilPass batch.
- Stores course-level FilPass enablement and batch mappings in dedicated plugin database records.
- Hooks into the custom certificate issue event so certificate issuance can trigger an external upload automatically.
- Generates the certificate PDF from the active custom certificate template and sends it to FilPass with recipient data.
- Tracks each certificate upload attempt in the database, including recipient details, status, attempt count, last response, and last error.
- Retries missing, pending, or failed certificate uploads through a scheduled task that runs every five minutes.
- Skips certificates issued to privileged users who can view all custom certificates, preventing admin/manager certificates from being uploaded.
- Listens for custom certificate deletion events and removes the corresponding FilPass upload tracking record.
- Emits dedicated Moodle events for upload start, successful upload, failed upload, admin settings changes, and course settings changes.
- Provides a debug interface on the course management page for manual upload testing.

## Main workflow

1. An administrator configures the FilPass API connection in site settings.
2. A course manager opens the FilPass course settings page and enables the integration for that course.
3. The manager selects the target FilPass batch to associate with the course.
4. The course-level enablement and batch mapping are saved to the `local_filpass_courses` table.
5. When a student receives a certificate, Moodle raises the custom certificate issue event.
6. The observer delegates the upload to the shared upload service.
7. The upload service checks whether the course is enabled and whether a batch mapping exists.
8. The upload service creates or updates a `local_filpass_uploads` record for the custom certificate issue.
9. If the recipient has certificate-management privileges, the upload is marked as skipped and is not sent to FilPass.
10. The certificate PDF is generated from the active template and written to a temporary file.
11. The plugin sends the certificate and recipient metadata to FilPass through the API client.
12. The upload record is marked as successful or failed based on the API response.
13. Failed uploads are scheduled for retry with a retry delay.
14. A scheduled task runs every five minutes and retries missing, pending, or failed uploads for enabled courses.
15. If a custom certificate issue is deleted in Moodle, the plugin listens for the deletion event and removes the matching upload tracking record.
16. Saving course-level or site-level settings also emits dedicated change events for auditability.

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
	│   ├── <a href="classes/service/">service/</a>
	│   │   └── <a href="classes/service/upload_service.php">upload_service.php</a>
	│   ├── <a href="classes/task/">task/</a>
	│   │   └── <a href="classes/task/retry_pending_uploads.php">retry_pending_uploads.php</a>
	│   ├── <a href="classes/admin_setting_password.php">admin_setting_password.php</a>
	│   ├── <a href="classes/admin_setting_text.php">admin_setting_text.php</a>
	│   ├── <a href="classes/api_client.php">api_client.php</a>
	│   └── <a href="classes/observer.php">observer.php</a>
	├── <a href="db/">db/</a>
	│   ├── <a href="db/events.php">events.php</a>
	│   ├── <a href="db/install.xml">install.xml</a>
	│   ├── <a href="db/tasks.php">task.php</a>
	│   └── <a href="db/upgrade.php">upgrade.php</a>
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
- [classes/service/upload_service.php](classes/service/upload_service.php) centralizes the certificate upload workflow used by both the event observer and the retry scheduled task. It creates upload records, generates PDFs, sends data to FilPass, records success or failure, schedules retries, skips privileged certificate recipients, and triggers upload lifecycle events.
- [classes/task/retry_pending_uploads.php](classes/task/retry_pending_uploads.php) defines the scheduled task that checks issued certificates in FilPass-enabled courses and retries uploads that are missing, pending, or failed.
- [classes/admin_setting_password.php](classes/admin_setting_password.php) defines the custom admin password setting used to store sensitive FilPass credentials while supporting change detection for settings events.
- [classes/admin_setting_text.php](classes/admin_setting_text.php) defines the custom admin text setting used for FilPass configuration values while supporting change detection for settings events.
- [classes/api_client.php](classes/api_client.php) contains the HTTP wrapper for typed authentication, token handling, batch lookup, and submission of certificate data and PDF files to FilPass.
- [classes/observer.php](classes/observer.php) listens for custom certificate issue creation and deletion events. Created issues are passed to the shared upload service, while deleted issues remove the matching FilPass upload tracking record.
- [db/events.php](db/events.php) registers the custom certificate issue-created and issue-deleted observers with Moodle.
- [db/install.xml](db/install.xml) defines the plugin database schema for fresh installs, including course-level FilPass configuration records and certificate upload tracking records.
- [db/tasks.php](db/tasks.php) registers the retry task so Moodle can run pending FilPass upload checks every five minutes.
- [db/upgrade.php](db/upgrade.php) creates the new database tables for existing installations and migrates older course-level plugin configuration values into the new course configuration table.
- [lang/en/local_filpass.php](lang/en/local_filpass.php) contains the English language strings used throughout the plugin, including labels, settings descriptions, event names, and connection-test messages.
- [edit_form.php](edit_form.php) defines the Moodle form used to configure the integration and the debug test fields.
- [lib.php](lib.php) adds the course configuration link in navigation and injects a small notice on the certificate view page when FilPass is active for the course.
- [manage_course.php](manage_course.php) handles the course-level administration UI, including saving course enablement and batch mappings to the plugin database table while retaining temporary compatibility with the older plugin-config keys.
- [settings.php](settings.php) stores the FilPass base URL, API key, and API secret in site-wide configuration and emits a settings-change event when those values are saved. Includes a section to test login credentials.
- [styles.css](styles.css) contains plugin-specific styling for the FilPass course settings, debug interface, notices, and admin UI elements.
- [test_connection.php](test_connection.php) provides the protected AJAX endpoint used by the site administration connection-test button. It validates the Moodle session, calls the FilPass login flow, and returns a safe JSON response with the token redacted.
- [test_observer.php](test_observer.php) provides a manual test entry point for exercising the observer/upload workflow during development or debugging.
- [version.php](version.php) declares the plugin version, required Moodle version, component name, maturity, and release metadata used by Moodle during installation and upgrades.

## Upload tracking and retry behavior

The plugin maintains two dedicated database tables for the FilPass workflow.

`local_filpass_courses` stores course-level FilPass configuration:

- Course ID
- Whether FilPass is enabled for the course
- The selected FilPass batch ID
- Created and modified timestamps
- The user who last modified the configuration

`local_filpass_uploads` stores per-certificate upload tracking:

- Custom certificate issue ID
- Course ID
- User ID
- Custom certificate ID
- FilPass batch ID
- Recipient name and email snapshot
- Generated filename
- Upload status
- Upload source
- Attempt count
- Last attempt time
- Next retry time
- Successful upload time
- Last API response
- Last error message

Upload records can move through the following statuses:

- `pending`: the upload is waiting to be sent or retried.
- `success`: the certificate details were successfully sent to FilPass.
- `failed`: the upload attempt failed and may be retried later.
- `skipped`: the upload was intentionally skipped, such as when the certificate belongs to a privileged user who can view all certificates.

The retry task runs every five minutes and processes issued certificates in FilPass-enabled courses when no successful upload record exists. It retries missing, pending, and failed records, but it does not retry successful or skipped records.

## Notes for developers
- This plugin depends on the custom certificate module in Moodle and expects certificate issue events to be available.
- The integration is designed around batch-based issuance and requires a valid FilPass batch ID to be configured for the course.
- The plugin includes a manual debug path that can be used to test the API connection and upload workflow from the course settings page without changing the main live configuration.
- The upload lifecycle is now observable through Moodle events, which makes the workflow easier to audit and troubleshoot.
- Debugging output is intentionally kept available but trimmed down to reduce log noise. The relevant logging calls are still present in the code and can be re-enabled by uncommenting them when more detailed diagnostics are needed.
- Course-level FilPass enablement is now stored in `local_filpass_courses`. The older `course_{id}_enabled` and `course_{id}_batch_id` plugin configuration keys are retained temporarily for compatibility.
- Certificate upload state is now tracked in `local_filpass_uploads`, with one row per custom certificate issue.
- The observer and retry task share the same upload service so normal uploads and retry uploads follow the same code path.
- The retry task depends on Moodle cron. A five-minute task schedule will only be effective if Moodle cron runs frequently enough.
- Certificates belonging to users with `mod/customcert:viewallcertificates` are marked as skipped to avoid uploading admin, manager, or teacher certificates to FilPass.
- Deleting a custom certificate issue in Moodle removes the corresponding FilPass upload tracking record.

## Scheduled task

The plugin registers the following Moodle scheduled task:

```bash
\local_filpass\task\retry_pending_uploads
```

The task is configured to run every five minutes. It checks issued custom certificates in courses where FilPass is enabled and a batch ID is configured. For each matching certificate issue, it retries the upload only when the upload record is missing, pending, or failed.

The task processes a limited batch of records per run to avoid overloading cron. Failed uploads are retried with a backoff delay controlled by the upload service.

## Debugging and log noise
The plugin includes several debugging statements that help trace the login flow, batch retrieval, payload construction, and upload response. These were intentionally reduced in normal use to keep Moodle logs cleaner, but they remain available for future troubleshooting.

If a developer needs deeper visibility into the flow, they can temporarily uncomment the debugging calls in:
- [classes/api_client.php](classes/api_client.php)
- [manage_course.php](manage_course.php)

The retry flow can be inspected through the `local_filpass_uploads` table. Useful fields include `status`, `attempts`, `lastattempt`, `nextretry`, `timeuploaded`, `lastresponse`, and `lasterror`. These fields make it easier to distinguish between certificates that have been uploaded, skipped, failed, or are waiting for retry.

## Event reference

The plugin observes and emits Moodle events during normal operation.

Observed custom certificate events:

- Certificate issued: handled when `mod_customcert` raises an issue-created event. The plugin passes the certificate issue to the shared upload service.
- Certificate deleted: handled when `mod_customcert` raises an issue-deleted event. The plugin removes the corresponding FilPass upload tracking record.

Plugin events emitted by the upload workflow:

- Begin upload: raised immediately before the plugin attempts to submit certificate data to FilPass.
- Upload success: raised when the API response indicates a successful submission.
- Upload failure: raised when the upload fails or when no usable PDF could be generated.
- Admin settings changed: raised when site-level FilPass settings are saved.
- Course settings changed: raised when a course’s FilPass settings are saved.