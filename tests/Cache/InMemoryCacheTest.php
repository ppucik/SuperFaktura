<?php

declare(strict_types=1);

namespace SuperFaktura\Tests\Cache;

use SuperFaktura\Cache\InMemoryCache;
use PHPUnit\Framework\TestCase;

class InMemoryCacheTest extends TestCase
{
    private ?InMemoryCache $cache = null;

    protected function setUp(): void
    {
        $this->cache = new InMemoryCache();
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        $this->assertNull($this->cache->get('nonexistent'));
    }

    public function testSetAndGetRoundtrip(): void
    {
        $data = ['ico' => '01569651', 'name' => 'Test s.r.o.'];
        $this->cache->set('ares_ico_01569651', $data);

        $this->assertSame($data, $this->cache->get('ares_ico_01569651'));
    }

    public function testDeleteRemovesKey(): void
    {
        $this->cache->set('ares_ico_01569651', ['ico' => '01569651']);
        $this->cache->delete('ares_ico_01569651');

        $this->assertNull($this->cache->get('ares_ico_01569651'));
    }

    public function testClearRemovesAllKeys(): void
    {
        $this->cache->set('ares_ico_11111111', ['ico' => '11111111']);
        $this->cache->set('ares_ico_22222222', ['ico' => '22222222']);
        $this->cache->clear();

        $this->assertNull($this->cache->get('ares_ico_11111111'));
        $this->assertNull($this->cache->get('ares_ico_22222222'));
        $this->assertSame(0, $this->cache->count());
    }

    public function testExpiredEntryReturnsNull(): void
    {
        // TTL = 1 second, then we manually expire by manipulating time
        // We use TTL=0 to simulate immediate expiry (expires_at = time() + 0)
        $this->cache->set('ares_ico_01569651', ['ico' => '01569651'], ttl: 0);

        // sleep 1s to cross the TTL boundary
        sleep(1);

        $this->assertNull($this->cache->get('ares_ico_01569651'));
    }

    public function testCountReturnsOnlyValidEntries(): void
    {
        $this->cache->set('key_a', ['a' => 1], ttl: 3600);
        $this->cache->set('key_b', ['b' => 2], ttl: 3600);

        $this->assertSame(2, $this->cache->count());
    }

    public function testNullCacheAlwaysReturnsNull(): void
    {
        $null = new \SuperFaktura\Cache\NullCache();
        $null->set('key', ['data' => 'value']);

        $this->assertNull($null->get('key'));
    }
}
