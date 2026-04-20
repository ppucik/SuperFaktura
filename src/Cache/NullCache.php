<?php

declare(strict_types=1);

namespace SuperFaktura\Cache;

use SuperFaktura\Contract\CacheInterface;

/**
 * No-op cache implementation — caching is effectively disabled.
 * Used as the default so the library works out-of-the-box without configuration.
 *
 * Follows the Null Object Pattern: callers never need to check "is cache enabled?".
 */
final class NullCache implements CacheInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function set(string $key, array $data, int $ttl = 3600): void
    {
        // intentionally empty
    }

    public function delete(string $key): void
    {
        // intentionally empty
    }

    public function clear(): void
    {
        // intentionally empty
    }
}
