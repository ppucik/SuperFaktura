<?php

declare(strict_types=1);

namespace SuperFaktura\Cache;

use SuperFaktura\Contract\CacheInterface;

/**
 * Simple in-process cache stored in a PHP array.
 * Data survives only for the duration of a single PHP request/process.
 *
 * Ideal for:
 *   - batch lookups where the same IČO appears multiple times
 *   - CLI scripts
 *   - unit/integration tests
 *
 * For production persistence use a Redis or Memcached adapter instead.
 */
final class InMemoryCache implements CacheInterface
{
    /** @var array<string, array{data: array, expires_at: int}> */
    private array $store = [];

    public function get(string $key): ?array
    {
        if (!isset($this->store[$key])) {
            return null;
        }

        if (time() > $this->store[$key]['expires_at']) {
            unset($this->store[$key]);
            return null;
        }

        return $this->store[$key]['data'];
    }

    public function set(string $key, array $data, int $ttl = 3600): void
    {
        $this->store[$key] = [
            'data'       => $data,
            'expires_at' => time() + $ttl,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }

    public function clear(): void
    {
        $this->store = [];
    }

    /**
     * Returns the number of currently valid (non-expired) entries.
     * Useful for debugging and assertions in tests.
     */
    public function count(): int
    {
        $now = time();
        return count(array_filter(
            $this->store,
            static fn(array $entry): bool => $entry['expires_at'] > $now,
        ));
    }
}
