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
 * FilPass connection-test endpoint.
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

/**
 * Outputs a JSON response using the FilPass response schema.
 *
 * Schema:
 * {
 *     message: string,
 *     status: "success"|"error",
 *     code: 200|400|401,
 *     data: {
 *         token: string
 *     }
 * }
 *
 * @param string $message
 * @param string $status
 * @param int $code
 * @param string $token
 * @return void
 */
function local_filpass_output_connection_response(
    string $message,
    string $status,
    int $code,
    string $token = ''
): void {
    echo json_encode([
        'message' => $message,
        'status' => $status,
        'code' => $code,
        'data' => [
            'token' => $token,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    exit;
}

try {
    $client = new \local_filpass\api_client();

    // This should return a validated typed response object.
    $response = $client->login();

    if ($response->is_success()) {
        local_filpass_output_connection_response(
            $response->get_message(),
            'success',
            200,
            '[REDACTED]'
        );
    }

    $code = $response->get_code();

    if (!in_array($code, [400, 401], true)) {
        $code = 400;
    }

    local_filpass_output_connection_response(
        $response->get_message(),
        'error',
        $code,
        ''
    );

} catch (\invalid_parameter_exception $exception) {
    debugging(
        'Invalid FilPass login response: ' . $exception->getMessage(),
        DEBUG_DEVELOPER
    );

    local_filpass_output_connection_response(
        'FilPass returned an invalid response: ' . $exception->getMessage(),
        'error',
        400,
        ''
    );

} catch (\moodle_exception $exception) {
    debugging(
        'FilPass connection test failed: ' . $exception->getMessage(),
        DEBUG_DEVELOPER
    );

    local_filpass_output_connection_response(
        $exception->getMessage(),
        'error',
        400,
        ''
    );

} catch (\Throwable $exception) {
    debugging(
        'Unexpected FilPass connection test error: ' . $exception->getMessage(),
        DEBUG_DEVELOPER
    );

    local_filpass_output_connection_response(
        'Unexpected FilPass connection test error. ' . $exception->getMessage() . ' ' . $exception->getTraceAsString(),
        'error',
        400,
        ''
    );
}