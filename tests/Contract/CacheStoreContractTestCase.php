<?php

declare(strict_types=1);

namespace Astral\Cache\Tests\Contract;

use Astral\Cache\CacheStoreInterface;
use Astral\Cache\Exception\InvalidCacheValueException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Contrat commun ArrayStore / FileStore (sémantique Core\Cache 2.0).
 */
abstract class CacheStoreContractTestCase extends TestCase
{
    abstract protected function createStore(): CacheStoreInterface;

    public function testSetGetRoundTrip(): void
    {
        $store = $this->createStore();
        $store->set('users', ['a', 'b'], 60);

        self::assertTrue($store->has('users'));
        self::assertSame(['a', 'b'], $store->get('users'));
    }

    public function testMissReturnsDefault(): void
    {
        $store = $this->createStore();

        self::assertFalse($store->has('missing'));
        self::assertNull($store->get('missing'));
        self::assertSame('fallback', $store->get('missing', 'fallback'));
    }

    public function testNullValueIsHit(): void
    {
        $store = $this->createStore();
        $store->set('nullable', null, 60);

        self::assertTrue($store->has('nullable'));
        self::assertNull($store->get('nullable', 'default'));
    }

    public function testDeleteRemovesEntry(): void
    {
        $store = $this->createStore();
        $store->set('k', 1, 60);
        $store->delete('k');

        self::assertFalse($store->has('k'));
        self::assertSame('x', $store->get('k', 'x'));
    }

    public function testClearPurgesStore(): void
    {
        $store = $this->createStore();
        $store->set('a', 1, 60);
        $store->set('b', 2, 60);

        $store->clear();

        self::assertFalse($store->has('a'));
        self::assertFalse($store->has('b'));
    }

    public function testTtlMustBePositive(): void
    {
        $store = $this->createStore();

        $this->expectException(InvalidArgumentException::class);
        $store->set('k', 1, 0);
    }

    public function testObjectsAreRejected(): void
    {
        $store = $this->createStore();

        $this->expectException(InvalidCacheValueException::class);
        $store->set('obj', new stdClass(), 60);
    }

    public function testNestedObjectIsRejected(): void
    {
        $store = $this->createStore();

        $this->expectException(InvalidCacheValueException::class);
        $store->set('nested', ['x' => new stdClass()], 60);
    }

    public function testExpiredEntryIsMiss(): void
    {
        $store = $this->createStore();
        $store->set('short', 'v', 1);
        sleep(2);

        self::assertFalse($store->has('short'));
        self::assertNull($store->get('short'));
    }
}
