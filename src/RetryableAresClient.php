<?php

declare(strict_types=1);

namespace SuperFaktura;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SuperFaktura\Contract\AresClientInterface;
use SuperFaktura\Contract\CacheInterface;
use SuperFaktura\Cache\NullCache;
use SuperFaktura\Exception\AresConnectionException;
use SuperFaktura\Exception\AresException;
use SuperFaktura\Exception\AresNotFoundException;

/**
 * Decorator around AresClientInterface that adds:
 *   1. In-memory / pluggable cache (avoids redundant HTTP calls)
 *   2. Automatic retry with exponential backoff (handles transient failures)
 *   3. PSR-3 structured logging (info, warning, error)
 *
 * Design: Decorator Pattern — wraps any AresClientInterface without changing it.
 * The inner client stays simple and focused (Single Responsibility).
 */
class RetryableAresClient implements AresClientInterface
{
    private const MAX_RETRIES    = 3;
    private const BASE_DELAY_MS  = 200;  // initial delay in milliseconds
    private const CACHE_KEY_PREFIX = 'ares_ico_';

    public function __construct(
        private readonly AresClientInterface $inner,
        private readonly CacheInterface      $cache  = new NullCache(),
        private readonly LoggerInterface     $logger = new NullLogger(),
        private readonly int                 $maxRetries   = self::MAX_RETRIES,
        private readonly int                 $baseDelayMs  = self::BASE_DELAY_MS,
    ) {}

    /**
     * Fetch company data with cache + retry + logging.
     *
     * Cache hit  → return immediately, no HTTP call.
     * Cache miss → attempt HTTP call up to $maxRetries times.
     *              Only AresConnectionException triggers a retry.
     *              AresNotFoundException is permanent — no retry.
     */
    public function fetchByIco(string $ico): array
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $ico;

        // ── 1. Cache lookup ────────────────────────────────────────────────────
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $this->logger->info('ARES cache hit', ['ico' => $ico]);
            return $cached;
        }

        // ── 2. HTTP fetch with retry ───────────────────────────────────────────
        $attempt   = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            try {
                $this->logger->info('ARES API request', [
                    'ico'     => $ico,
                    'attempt' => $attempt,
                ]);

                $data = $this->inner->fetchByIco($ico);

                $this->cache->set($cacheKey, $data);

                $this->logger->info('ARES API success', [
                    'ico'     => $ico,
                    'attempt' => $attempt,
                ]);

                return $data;
            } catch (AresNotFoundException $e) {
                // 404 is permanent — retrying will not help
                $this->logger->warning('ARES company not found', [
                    'ico'   => $ico,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            } catch (AresConnectionException $e) {
                // transient failure — worth retrying
                $lastError = $e;
                $delayMs   = $this->exponentialDelay($attempt);

                $this->logger->warning('ARES connection failed, will retry', [
                    'ico'        => $ico,
                    'attempt'    => $attempt,
                    'maxRetries' => $this->maxRetries,
                    'delayMs'    => $delayMs,
                    'error'      => $e->getMessage(),
                ]);

                if ($attempt < $this->maxRetries) {
                    $this->sleep($delayMs);
                }
            } catch (AresException $e) {
                // unexpected API error — log and rethrow immediately
                $this->logger->error('ARES API error', [
                    'ico'   => $ico,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        // all retries exhausted
        $this->logger->error('ARES all retries exhausted', [
            'ico'        => $ico,
            'maxRetries' => $this->maxRetries,
            'error'      => $lastError?->getMessage(),
        ]);

        throw new AresConnectionException(
            "ARES API unreachable for IČO '{$ico}' after {$this->maxRetries} attempts. " .
                "Last error: " . ($lastError?->getMessage() ?? 'unknown'),
            previous: $lastError,
        );
    }

    /**
     * Exponential backoff: 200ms, 400ms, 800ms, ...
     * Capped at 5 seconds to avoid hanging indefinitely.
     */
    private function exponentialDelay(int $attempt): int
    {
        return min(
            $this->baseDelayMs * (2 ** ($attempt - 1)),
            5_000,
        );
    }

    /**
     * Extracted for testability — tests can override via subclass or mock.
     */
    protected function sleep(int $milliseconds): void
    {
        usleep($milliseconds * 1_000);
    }
}
