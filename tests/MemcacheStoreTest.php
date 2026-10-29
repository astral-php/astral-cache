<?php

declare(strict_types=1);

namespace Astral\Cache\Tests;

use Astral\Cache\CacheStoreInterface;
use Astral\Cache\Store\MemcacheStore;
use Astral\Cache\Tests\Contract\CacheStoreContractTestCase;
use Memcache;
use Memcached;
use Throwable;

final class MemcacheStoreTest extends CacheStoreContractTestCase
{
    private static string $prefix;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$prefix = 'astral:cache:test:' . bin2hex(random_bytes(4)) . ':';
    }

    protected function setUp(): void
    {
        if (!class_exists(Memcache::class) && !class_exists(Memcached::class)) {
            self::markTestSkipped('ext-memcache / ext-memcached non disponible');
        }

        try {
            MemcacheStore::connect([
                'host'   => '127.0.0.1',
                'port'   => 11211,
                'prefix' => self::$prefix,
            ]);
        } catch (Throwable $e) {
            self::markTestSkipped('Memcache indisponible : ' . $e->getMessage());
        }

        parent::setUp();
    }

    protected function createStore(): CacheStoreInterface
    {
        return MemcacheStore::connect([
            'host'   => '127.0.0.1',
            'port'   => 11211,
            'prefix' => self::$prefix,
        ]);
    }

    public function testClearUsesGenerationNotServerFlush(): void
    {
        $other = MemcacheStore::connect([
            'prefix' => 'astral:cache:other:' . bin2hex(random_bytes(4)) . ':',
        ]);
        $store = $this->createStore();

        $other->set('keep', 'yes', 60);
        $store->set('drop', 'no', 60);
        $store->clear();

        self::assertTrue($other->has('keep'));
        self::assertFalse($store->has('drop'));
    }
}
