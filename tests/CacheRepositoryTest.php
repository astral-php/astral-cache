<?php

declare(strict_types=1);

namespace Astral\Cache\Tests;

use Astral\Cache\CacheRepository;
use Astral\Cache\Store\ArrayStore;
use PHPUnit\Framework\TestCase;

final class CacheRepositoryTest extends TestCase
{
    public function testRememberComputesOnce(): void
    {
        $repo  = new CacheRepository(new ArrayStore());
        $calls = 0;

        $first = $repo->remember('k', 60, function () use (&$calls): string {
            $calls++;

            return 'computed';
        });

        $second = $repo->remember('k', 60, function () use (&$calls): string {
            $calls++;

            return 'again';
        });

        self::assertSame('computed', $first);
        self::assertSame('computed', $second);
        self::assertSame(1, $calls);
    }

    public function testForgetAlias(): void
    {
        $repo = new CacheRepository(new ArrayStore());
        $repo->set('k', 1, 60);
        $repo->forget('k');

        self::assertFalse($repo->has('k'));
    }

    public function testFlushAlias(): void
    {
        $repo = new CacheRepository(new ArrayStore());
        $repo->set('a', 1, 60);
        $repo->set('b', 2, 60);

        self::assertSame(2, $repo->flush());
        self::assertFalse($repo->has('a'));
    }

    public function testDefaultTtlUsedWhenOmitted(): void
    {
        $repo = new CacheRepository(new ArrayStore(), 120);
        $repo->set('k', 'v');

        self::assertTrue($repo->has('k'));
        self::assertSame('v', $repo->get('k'));
    }
}
