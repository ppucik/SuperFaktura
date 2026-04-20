<?php

declare(strict_types=1);

namespace SuperFaktura;

use SuperFaktura\Contract\AresClientInterface;
use SuperFaktura\Exception\AresConnectionException;
use SuperFaktura\Exception\AresNotFoundException;
use SuperFaktura\Exception\AresException;

/**
 * HTTP client for communicating with the ARES API.
 * Responsible solely for making HTTP requests (Single Responsibility).
 */
class AresClient implements AresClientInterface
{
    private const BASE_URL = 'https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty';
    private const DEFAULT_TIMEOUT = 10;

    public function __construct(
        private readonly int $timeout = self::DEFAULT_TIMEOUT,
    ) {}

    /**
     * Fetch raw JSON data from ARES for a given IČO.
     *
     * @return array<string, mixed>
     * @throws AresNotFoundException   If the company is not found (HTTP 404)
     * @throws AresConnectionException If the request fails due to network/timeout
     * @throws AresException           For any other unexpected API error
     */
    public function fetchByIco(string $ico): array
    {
        $url = self::BASE_URL . '/' . rawurlencode($ico);

        $context = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => $this->timeout,
                'ignore_errors'   => true,
                'header'          => "Accept: application/json\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new AresConnectionException(
                "Failed to connect to ARES API for IČO: {$ico}. Check your network connection."
            );
        }

        $statusCode = $this->parseStatusCode((array) ($http_response_header));

        return match (true) {
            $statusCode === 404 => throw new AresNotFoundException("Company with IČO '{$ico}' was not found in ARES."),
            $statusCode >= 500  => throw new AresException("ARES API returned a server error (HTTP {$statusCode})."),
            $statusCode !== 200 => throw new AresException("Unexpected ARES API response (HTTP {$statusCode})."),
            default             => $this->decodeJson($response, $ico),
        };
    }

    /**
     * Parse HTTP status code from response headers. 
     * 
     * @param string[] $headers
     * @return int The HTTP status code, or 0 if it cannot be determined
     */
    private function parseStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                return (int) $matches[1];
            }
        }
        return 0;
    }

    /**
     * Decode JSON response and handle errors.
     * 
     * @param string $body The raw JSON response body
     * @param string $ico The IČO for error context
     * @return array<string, mixed>
     * @throws AresException If JSON decoding fails
     */
    private function decodeJson(string $body, string $ico): array
    {
        $data = json_decode($body, associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AresException(
                "Failed to decode ARES API response for IČO '{$ico}': " . json_last_error_msg()
            );
        }

        return $data;
    }
}
