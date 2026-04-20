<?php

declare(strict_types=1);

namespace SuperFaktura\Contract;

/**
 * Contract for caching raw ARES API responses.
 * Implementations decide the storage backend (in-memory, Redis, file, ...).
 */
interface CacheInterface
{
    /**
     * Retrieve cached data by key.
     * Returns null if key does not exist or has expired.
     */
    public function get(string $key): ?array;

    /**
     * Store data under a key for a given TTL (seconds).
     */
    public function set(string $key, array $data, int $ttl = 3600): void;

    /**
     * Remove a specific key from the cache.
     */
    public function delete(string $key): void;

    /**
     * Wipe the entire cache.
     */
    public function clear(): void;
}
