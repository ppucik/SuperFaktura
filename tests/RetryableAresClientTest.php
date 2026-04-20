<?php

declare(strict_types=1);

namespace SuperFaktura\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SuperFaktura\Cache\InMemoryCache;
use SuperFaktura\Cache\NullCache;
use SuperFaktura\Contract\AresClientInterface;
use SuperFaktura\Exception\AresConnectionException;
use SuperFaktura\Exception\AresException;
use SuperFaktura\Exception\AresNotFoundException;
use SuperFaktura\RetryableAresClient;

class RetryableAresClientTest extends TestCase
{
    private const ICO     = '01569651';
    private const PAYLOAD = ['ico' => '01569651', 'obchodniJmeno' => 'Test s.r.o.'];

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Build a RetryableAresClient whose sleep() is a no-op (tests run instantly).
     */
    private function makeClient(
        AresClientInterface $inner,
        ?InMemoryCache $cache   = null,
        ?LoggerInterface $logger = null,
        int $maxRetries          = 3,
    ): RetryableAresClient {
        // Anonymous subclass overrides sleep() so tests don't actually wait
        return new class(
            $inner,
            $cache  ?? new NullCache(),
            $logger ?? $this->createStub(LoggerInterface::class),
            $maxRetries,
            0,          // baseDelayMs = 0 → no actual sleeping even if sleep() is real
        ) extends RetryableAresClient {
            protected function sleep(int $milliseconds): void {}
        };
    }

    private function mockInner(): MockObject&AresClientInterface
    {
        return $this->createMock(AresClientInterface::class);
    }

    // ── Cache tests ────────────────────────────────────────────────────────────

    public function testCacheMissCallsInnerClient(): void
    {
        $inner = $this->mockInner();
        $inner->expects($this->once())
              ->method('fetchByIco')
              ->with(self::ICO)
              ->willReturn(self::PAYLOAD);

        $result = $this->makeClient($inner)->fetchByIco(self::ICO);

        $this->assertSame(self::PAYLOAD, $result);
    }

    public function testCacheHitSkipsInnerClient(): void
    {
        $cache = new InMemoryCache();
        $cache->set('ares_ico_' . self::ICO, self::PAYLOAD);

        $inner = $this->mockInner();
        $inner->expects($this->never())->method('fetchByIco');

        $result = $this->makeClient($inner, $cache)->fetchByIco(self::ICO);

        $this->assertSame(self::PAYLOAD, $result);
    }

    public function testSuccessfulFetchPopulatesCache(): void
    {
        $cache = new InMemoryCache();
        $inner = $this->mockInner();
        $inner->method('fetchByIco')->willReturn(self::PAYLOAD);

        $this->makeClient($inner, $cache)->fetchByIco(self::ICO);

        // second call must be served from cache (inner called only once)
        $inner->expects($this->never())->method('fetchByIco');
        $this->makeClient($inner, $cache)->fetchByIco(self::ICO);

        $this->assertSame(1, $cache->count());
    }

    // ── Retry tests ────────────────────────────────────────────────────────────

    public function testSuccessOnFirstAttemptCallsInnerOnce(): void
    {
        $inner = $this->mockInner();
        $inner->expects($this->once())
              ->method('fetchByIco')
              ->willReturn(self::PAYLOAD);

        $this->makeClient($inner)->fetchByIco(self::ICO);
    }

    public function testRetriesOnConnectionException(): void
    {
        $inner = $this->mockInner();
        $inner->expects($this->exactly(3))
              ->method('fetchByIco')
              ->willReturnOnConsecutiveCalls(
                  $this->throwException(new AresConnectionException('timeout')),
                  $this->throwException(new AresConnectionException('timeout')),
                  self::PAYLOAD,
              );

        $result = $this->makeClient($inner, maxRetries: 3)->fetchByIco(self::ICO);

        $this->assertSame(self::PAYLOAD, $result);
    }

    public function testThrowsAfterAllRetriesExhausted(): void
    {
        $inner = $this->mockInner();
        $inner->method('fetchByIco')
              ->willThrowException(new AresConnectionException('timeout'));

        $this->expectException(AresConnectionException::class);
        $this->expectExceptionMessageMatches('/after 3 attempts/');

        $this->makeClient($inner, maxRetries: 3)->fetchByIco(self::ICO);
    }

    public function testDoesNotRetryOnNotFoundException(): void
    {
        $inner = $this->mockInner();
        $inner->expects($this->once())   // exactly 1 call — no retry
              ->method('fetchByIco')
              ->willThrowException(new AresNotFoundException('not found'));

        $this->expectException(AresNotFoundException::class);

        $this->makeClient($inner)->fetchByIco(self::ICO);
    }

    public function testDoesNotRetryOnGenericAresException(): void
    {
        $inner = $this->mockInner();
        $inner->expects($this->once())
              ->method('fetchByIco')
              ->willThrowException(new AresException('server error'));

        $this->expectException(AresException::class);

        $this->makeClient($inner)->fetchByIco(self::ICO);
    }

    public function testExhaustedExceptionWrapsOriginalAsChain(): void
    {
        $original = new AresConnectionException('original timeout');
        $inner    = $this->mockInner();
        $inner->method('fetchByIco')->willThrowException($original);

        try {
            $this->makeClient($inner, maxRetries: 2)->fetchByIco(self::ICO);
            $this->fail('Expected AresConnectionException');
        } catch (AresConnectionException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }

    // ── Logging tests ──────────────────────────────────────────────────────────

    public function testLogsCacheHit(): void
    {
        $cache = new InMemoryCache();
        $cache->set('ares_ico_' . self::ICO, self::PAYLOAD);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
               ->method('info')
               ->with('ARES cache hit', $this->arrayHasKey('ico'));

        $inner = $this->mockInner();
        $this->makeClient($inner, $cache, $logger)->fetchByIco(self::ICO);
    }

    public function testLogsSuccessfulRequest(): void
    {
        $inner = $this->mockInner();
        $inner->method('fetchByIco')->willReturn(self::PAYLOAD);

        $logger = $this->createMock(LoggerInterface::class);
        // Expects: info('ARES API request', ...) + info('ARES API success', ...)
        $logger->expects($this->exactly(2))->method('info');

        $this->makeClient($inner, logger: $logger)->fetchByIco(self::ICO);
    }

    public function testLogsWarningOnConnectionFailure(): void
    {
        $inner = $this->mockInner();
        $inner->method('fetchByIco')
              ->willThrowException(new AresConnectionException('timeout'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(3))   // 3 retries = 3 warnings
               ->method('warning')
               ->with('ARES connection failed, will retry', $this->arrayHasKey('attempt'));

        $this->expectException(AresConnectionException::class);
        $this->makeClient($inner, logger: $logger, maxRetries: 3)->fetchByIco(self::ICO);
    }

    public function testLogsWarningOnNotFound(): void
    {
        $inner = $this->mockInner();
        $inner->method('fetchByIco')
              ->willThrowException(new AresNotFoundException('not found'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
               ->method('warning')
               ->with('ARES company not found', $this->arrayHasKey('ico'));

        $this->expectException(AresNotFoundException::class);
        $this->makeClient($inner, logger: $logger)->fetchByIco(self::ICO);
    }

    public function testLogsErrorWhenAllRetriesExhausted(): void
    {
        $inner = $this->mockInner();
        $inner->method('fetchByIco')
              ->willThrowException(new AresConnectionException('timeout'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
               ->method('error')
               ->with('ARES all retries exhausted', $this->arrayHasKey('maxRetries'));

        $this->expectException(AresConnectionException::class);
        $this->makeClient($inner, logger: $logger, maxRetries: 3)->fetchByIco(self::ICO);
    }
}
