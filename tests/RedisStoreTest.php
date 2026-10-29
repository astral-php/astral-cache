<?php

declare(strict_types=1);

namespace Astral\Cache\Tests;

use Astral\Cache\CacheStoreInterface;
use Astral\Cache\Store\RedisStore;
use Astral\Cache\Tests\Contract\CacheStoreContractTestCase;
use Redis;
use Throwable;

final class RedisStoreTest extends CacheStoreContractTestCase
{
    private static string $prefix;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$prefix = 'astral:cache:test:' . bin2hex(random_bytes(4)) . ':';
    }

    protected function setUp(): void
    {
        if (!class_exists(Redis::class)) {
            self::markTestSkipped('ext-redis non disponible');
        }

        try {
            $probe = RedisStore::connect([
                'host'   => '127.0.0.1',
                'port'   => 6379,
                'prefix' => self::$prefix,
            ]);
            $probe->clear();
        } catch (Throwable $e) {
            self::markTestSkipped('Redis indisponible : ' . $e->getMessage());
        }

        parent::setUp();
    }

    protected function createStore(): CacheStoreInterface
    {
        return RedisStore::connect([
            'host'   => '127.0.0.1',
            'port'   => 6379,
            'prefix' => self::$prefix,
        ]);
    }

    public function testClearDoesNotFlushOtherPrefixes(): void
    {
        $otherPrefix = 'astral:cache:other:' . bin2hex(random_bytes(4)) . ':';
        $other       = RedisStore::connect(['prefix' => $otherPrefix]);
        $store       = $this->createStore();

        $other->set('keep', 'yes', 60);
        $store->set('drop', 'no', 60);
        $store->clear();

        self::assertTrue($other->has('keep'));
        self::assertFalse($store->has('drop'));

        $other->clear();
    }
}
