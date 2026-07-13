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

namespace local_filpass\api\response;

/**
 * Typed data portion of a FilPass login response.
 *
 * @package    local_filpass
 * @copyright  2026 Enrique Badiola <enrique.badiola83@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class login_response_data {
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';

    public const CODE_SUCCESS = 200;
    public const CODE_BAD_REQUEST = 400;
    public const CODE_UNAUTHORIZED = 401;

    private string $message;

    private string $status;

    private int $code;

    private ?string $token;

    /**
     * Constructor.
     *
     * @param string $message
     * @param string $status
     * @param int $code
     * @param string|null $token
     */
    private function __construct(
        string $message,
        string $status,
        int $code,
        ?string $token
    ) {
        $this->message = $message;
        $this->status = $status;
        $this->code = $code;
        $this->token = $token;
    }

    /**
     * Creates a typed response from a JSON string.
     *
     * @param string $json
     * @return self
     * @throws \invalid_parameter_exception
     */
    public static function from_json(string $json): self {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \invalid_parameter_exception(
                'Invalid JSON response: ' . $exception->getMessage()
            );
        }

        if (!is_array($decoded)) {
            throw new \invalid_parameter_exception(
                'The API response must be a JSON object.'
            );
        }

        $message = $decoded['message'] ?? null;
        $status = $decoded['status'] ?? null;
        $code = $decoded['code'] ?? null;
        $data = $decoded['data'] ?? null;

        if (!is_string($message)) {
            throw new \invalid_parameter_exception(
                'The response message must be a string.'
            );
        }

        $allowedstatuses = [
            self::STATUS_SUCCESS,
            self::STATUS_ERROR,
        ];

        if (!is_string($status) || !in_array($status, $allowedstatuses, true)) {
            throw new \invalid_parameter_exception(
                'The response status must be either success or error.'
            );
        }

        $allowedcodes = [
            self::CODE_SUCCESS,
            self::CODE_BAD_REQUEST,
            self::CODE_UNAUTHORIZED,
        ];

        if (!is_int($code) || !in_array($code, $allowedcodes, true)) {
            throw new \invalid_parameter_exception(
                'The response code must be 200, 400, or 401.'
            );
        }

        $token = null;

        if ($data !== null) {
            if (!is_array($data)) {
                throw new \invalid_parameter_exception(
                    'The response data must be an object.'
                );
            }

            if (array_key_exists('token', $data)) {
                if (!is_string($data['token'])) {
                    throw new \invalid_parameter_exception(
                        'The response token must be a string.'
                    );
                }

                $token = $data['token'];
            }
        }

        if ($status === self::STATUS_SUCCESS) {
            if ($code !== self::CODE_SUCCESS) {
                throw new \invalid_parameter_exception(
                    'A successful response must use code 200.'
                );
            }

            if ($token === null || $token === '') {
                throw new \invalid_parameter_exception(
                    'A successful response must contain a token.'
                );
            }
        }

        if ($status === self::STATUS_ERROR && $code === self::CODE_SUCCESS) {
            throw new \invalid_parameter_exception(
                'An error response cannot use code 200.'
            );
        }

        return new self(
            $message,
            $status,
            $code,
            $token
        );
    }

    public function get_message(): string {
        return $this->message;
    }

    public function get_status(): string {
        return $this->status;
    }

    public function get_code(): int {
        return $this->code;
    }

    public function get_token(): ?string {
        return $this->token;
    }

    public function is_success(): bool {
        return $this->status === self::STATUS_SUCCESS &&
            $this->code === self::CODE_SUCCESS &&
            $this->token !== null &&
            $this->token !== '';
    }
}