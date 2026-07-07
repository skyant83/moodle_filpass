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
 * Thin wrapper around the FilPass HTTP API.
 *
 * This class is responsible for authenticating with the issuing authority,
 * retrieving available batches, and submitting certificate payloads and PDF
 * files to the FilPass bulk issuance endpoint.
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// /local/filpass/classes/api_client.php
namespace local_filpass;

defined('MOODLE_INTERNAL') || die();

class api_client {
    private $server;
    private $key;
    private $secret;
    private $token = null;

	private $post_header;

    public function __construct() {
        $this->server = rtrim(get_config('local_filpass', 'api_server'), '/');
        $this->key    = get_config('local_filpass', 'api_key');
        $this->secret = get_config('local_filpass', 'api_secret');
    }

	/**
	 * Sets the request headers used for authenticated API calls.
	 *
	 * @param string $token Optional Bearer token to include in the Authorization header.
	 * @return array An empty array for compatibility with the surrounding request flow.
	 */
	public function setPostHeader($token = '') {
		$this->post_header = [
			'accept: application/json',
			'authorization: Bearer ' . $token
		];
		return [];
	}

    /**
     * Authenticates against the FilPass issuing authority and caches a Bearer token.
     *
     * @return string|false The Bearer token on success, otherwise false.
     */
    public function login() {
        // Authentication is performed first because every later request depends on the
        // bearer token returned by the issuing authority endpoint.
        $curl = new \curl();
        $url = $this->server . '/v1/issuing-authority/login';

		$headers = [
			'accept: application/json',
			'x-api-key: ' . $this->key,
			'x-api-secret: ' . $this->secret
		];

		$curl->setHeader($headers);

        $response = $curl->post($url, '');

		$errno = $curl->get_errno();
		// debugging("FilPass login request completed with curl error code {$errno}.", DEBUG_DEVELOPER);
		if ($errno != 0) {
			// debugging("FilPass login request failed. Curl error: {$curl->error} (code {$errno}). Headers: " . print_r($headers, true), DEBUG_DEVELOPER);
			// debugging("FilPass login failed. Raw response: " . $response, DEBUG_DEVELOPER);
			// debugging("FilPass API Request Headers: " . print_r($headers, true), DEBUG_DEVELOPER);
		} else {
			$response_data = json_decode($response);
			// debugging("FilPass login response payload: " . print_r($response_data, true), DEBUG_DEVELOPER);
			// debugging("FilPass login returned bearer token: " . ($response_data->data->token ?? '[empty]'), DEBUG_DEVELOPER);
			if ($response_data->data->token !== null) {
				// The bearer token is cached once the login response contains a usable token value.
				// debugging("FilPass login token cached successfully.", DEBUG_DEVELOPER);
				$this->setPostHeader($response_data->data->token);
				$this->token = $response_data->data->token;
				return $this->token;
			}
		}

        return false;
    }

    /**
     * Retrieves the list of available FilPass batches from the API.
     *
     * @param int $page The page number to request.
     * @param int $limit The number of records to request per page.
     * @return array The batch documents returned by the API.
     */
    public function get_batches($page = 1, $limit = 200) {
        // The batch list is fetched only after a valid session token exists.
        if (!$this->token && !$this->login()) return [];
		// debugging("FilPass batch list request succeeded for page {$page} with limit {$limit}.", DEBUG_DEVELOPER);

        $curl = new \curl();
        $url = $this->server . "/batch/list?page={$page}&limit={$limit}&archived=false";

		$curl->setHeader($this->post_header);
        $response = $curl->get($url);


		$raw_data = json_decode($response);
		if ($raw_data && $raw_data->status !== 'error') {
			return $raw_data->data->documents ?? [];
		}
		return [];
    }

    /**
     * Sends certificate metadata and an uploaded document to FilPass for bulk issuance.
     *
     * @param string $batch_id The target FilPass batch identifier.
     * @param string $firstname Recipient first name.
     * @param string $lastname Recipient last name.
     * @param string $email Recipient email address.
     * @param string $file_path Path to the temporary PDF file.
     * @param string $file_name Name to assign to the uploaded file.
     * @return object|false Decoded API response object on success, false on authentication failure.
     */
    public function upload_bulk_data($batch_id, $firstname, $lastname, $email, $file_path, $file_name) {
        // The upload method assumes the session token is already available, because the
        // certificate submission request must be authenticated against the same FilPass account.
        if (!$this->token && !$this->login()) return false;

        $curl = new \curl();
        $url = $this->server . '/batch/create-bulk-issuance-data';

		$curl->setHeader($this->post_header);

        // The payload is sent as a single-item array because the API expects one record per
        // recipient while still allowing the document file to be attached in the same request.
        $parsedDataArray = [
			[
				'uploadedFileLink' => $file_name,
				'email' => $email,
				'firstName'=> $firstname,
				'lastName'=> $lastname,
				'issuedOn' => date('Y M d'),
			]
		];


		$params = [
			'batchNumber' => $batch_id,
			'parsedData' => json_encode($parsedDataArray),
			'uploadedDocs' => curl_file_create($file_path, mime_content_type($file_path), $file_name),
		];

		// The request details are logged here so a failing upload can be traced back to the
		// payload structure and file path used for the submission.
		// debugging('FilPass upload payload prepared: ' . print_r($params, true), DEBUG_DEVELOPER);
		// debugging('FilPass upload temporary file path: ' . $file_path, DEBUG_DEVELOPER);

        $response = $curl->post($url, $params);
        return json_decode($response);
    }
}