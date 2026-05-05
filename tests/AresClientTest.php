<?php

declare(strict_types=1);

namespace SuperFaktura\Tests;

use PHPUnit\Framework\TestCase;
use SuperFaktura\AresClient;
use SuperFaktura\Exception\AresConnectionException;
use SuperFaktura\Exception\AresException;
use SuperFaktura\Exception\AresNotFoundException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AresClientTest extends TestCase
{
    private const ICO = '45274649';

    private function makeClient(MockResponse $response): AresClient
    {
        return new AresClient(
            timeout:    10,
            httpClient: new MockHttpClient($response),
        );
    }

    // ── Happy path ─────────────────────────────────────────────────────────────

    public function testFetchByIcoReturnsDecodedArray(): void
    {
        $payload = json_encode([
            'ico' => self::ICO, 
            'obchodniJmeno' => 'ČEZ, a. s.'
        ], JSON_THROW_ON_ERROR);

        $client = $this->makeClient(new MockResponse($payload, ['http_code' => 200]));
        $result = $client->fetchByIco(self::ICO);

        $this->assertSame(self::ICO, $result['ico']);
        $this->assertSame('ČEZ, a. s.', $result['obchodniJmeno']);
    }

    public function testRequestSentToCorrectUrl(): void
    {
        $payload = json_encode(['ico' => self::ICO], JSON_THROW_ON_ERROR);        
        $capturedUrl = '';

        $callback = function (string $method, string $url, array $options) use ($payload, &$capturedUrl): MockResponse {
            $capturedUrl = $url; 
            return new MockResponse($payload, ['http_code' => 200]);
        };

        $mock   = new MockHttpClient($callback);
        $client = new AresClient(timeout: 10, httpClient: $mock);
        $client->fetchByIco(self::ICO);

        $this->assertSame(1, $mock->getRequestsCount());
        $this->assertNotEmpty($capturedUrl, 'URL nebola zachytená.');
        $this->assertStringContainsString(self::ICO, $capturedUrl);
    }  

    // ── HTTP error codes ───────────────────────────────────────────────────────

    public function testHttp404ThrowsAresNotFoundException(): void
    {
        $client = $this->makeClient(new MockResponse('', ['http_code' => 404]));

        $this->expectException(AresNotFoundException::class);
        $this->expectExceptionMessageMatches("/" . self::ICO . "/");

        $client->fetchByIco(self::ICO);
    }

    public function testHttp500ThrowsAresException(): void
    {
        $client = $this->makeClient(new MockResponse('', ['http_code' => 500]));

        $this->expectException(AresException::class);
        $this->expectExceptionMessageMatches('/server error/');

        $client->fetchByIco(self::ICO);
    }

    public function testUnexpectedHttpCodeThrowsAresException(): void
    {
        $client = $this->makeClient(new MockResponse('', ['http_code' => 302]));

        $this->expectException(AresException::class);
        $this->expectExceptionMessageMatches('/Unexpected/');

        $client->fetchByIco(self::ICO);
    }

    // ── Transport / connection errors ──────────────────────────────────────────

    public function testTransportExceptionThrowsAresConnectionException(): void
    {
        $mock   = new MockHttpClient(new MockResponse('', ['error' => 'Connection timed out']));
        $client = new AresClient(timeout: 10, httpClient: $mock);

        $this->expectException(AresConnectionException::class);
        $this->expectExceptionMessageMatches('/Failed to connect/');

        $client->fetchByIco(self::ICO);
    }

    public function testConnectionExceptionChainsPreviousException(): void
    {
        $mock   = new MockHttpClient(new MockResponse('', ['error' => 'Connection timed out']));
        $client = new AresClient(timeout: 10, httpClient: $mock);

        try {
            $client->fetchByIco(self::ICO);
            $this->fail('Expected AresConnectionException');
        } catch (AresConnectionException $e) {
            $this->assertNotNull($e->getPrevious());
        }
    }

    // ── Invalid JSON ───────────────────────────────────────────────────────────

    public function testInvalidJsonThrowsAresException(): void
    {
        $client = $this->makeClient(new MockResponse('not-json', ['http_code' => 200]));

        $this->expectException(AresException::class);
        $this->expectExceptionMessageMatches('/Failed to decode/');

        $client->fetchByIco(self::ICO);
    }
}
