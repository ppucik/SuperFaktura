<?php

declare(strict_types=1);

namespace SuperFaktura;

use SuperFaktura\Contract\AresClientInterface;
use SuperFaktura\Exception\AresConnectionException;
use SuperFaktura\Exception\AresNotFoundException;
use SuperFaktura\Exception\AresException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * HTTP client for communicating with the ARES API.
 * Uses Symfony HttpClient — supports HTTP/2, granular timeouts, proper SSL handling.
 * Responsible solely for making HTTP requests (Single Responsibility).
 */
class AresClient implements AresClientInterface
{
    private const BASE_URL = 'https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty';
    private const DEFAULT_TIMEOUT = 10;

    private readonly HttpClientInterface $http;

    public function __construct(
        private readonly int      $timeout    = self::DEFAULT_TIMEOUT,
        ?HttpClientInterface      $httpClient = null,
    ) {
        $this->http = $httpClient ?? HttpClient::create([
            'timeout'     => $this->timeout,
            'headers'     => ['Accept' => 'application/json'],
            'verify_peer' => true,
            'verify_host' => true,
        ]);
    }

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

        try {
            $response   = $this->http->request('GET', $url);
            $statusCode = $response->getStatusCode();
            $body       = $response->getContent(throw: false);

        } catch (TransportExceptionInterface $e) {
            throw new AresConnectionException(
                "Failed to connect to ARES API for IČO '{$ico}'. Check your network connection.",
                previous: $e,
            );
        }

        return match (true) {
            $statusCode === 404 => throw new AresNotFoundException(
                "Company with IČO '{$ico}' was not found in ARES."
            ),
            $statusCode >= 500  => throw new AresException(
                "ARES API returned a server error (HTTP {$statusCode})."
            ),
            $statusCode !== 200 => throw new AresException(
                "Unexpected ARES API response (HTTP {$statusCode})."
            ),
            default             => $this->decodeJson($body, $ico),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $body, string $ico): array
    {
        /** @var array<string, mixed>|null $data */
        $data = json_decode($body, associative: true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new AresException(
                "Failed to decode ARES API response for IČO '{$ico}': " . json_last_error_msg()
            );
        }

        return $data;
    }
}
