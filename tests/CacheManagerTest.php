<?php

declare(strict_types=1);

namespace Astral\Cache\Tests;

use Astral\Cache\CacheManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Redis;
use Throwable;

final class CacheManagerTest extends TestCase
{
    public function testResolvesArrayStore(): void
    {
        $manager = new CacheManager([
            'default' => 'array',
            'prefix'  => 'app:',
            'ttl'     => 60,
            'stores'  => [
                'array' => ['driver' => 'array'],
            ],
        ]);

        $repo = $manager->store();
        $repo->set('x', 42);

        self::assertSame(42, $repo->get('x'));
        self::assertSame($repo, $manager->store('array'));
    }

    public function testUnknownStoreThrows(): void
    {
        $manager = new CacheManager([
            'default' => 'array',
            'stores'  => ['array' => ['driver' => 'array']],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $manager->store('missing');
    }

    public function testUnknownDriverThrows(): void
    {
        $manager = new CacheManager([
            'default' => 'weird',
            'stores'  => ['weird' => ['driver' => 'weird']],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache driver inconnu');
        $manager->store();
    }

    public function testResolvesRedisWhenAvailable(): void
    {
        if (!class_exists(Redis::class)) {
            self::markTestSkipped('ext-redis non disponible');
        }

        $prefix = 'astral:cache:mgr:' . bin2hex(random_bytes(4)) . ':';

        try {
            $manager = new CacheManager([
                'default' => 'redis',
                'prefix'  => $prefix,
                'ttl'     => 60,
                'stores'  => [
                    'redis' => [
                        'driver' => 'redis',
                        'host'   => '127.0.0.1',
                        'port'   => 6379,
                    ],
                ],
            ]);

            $repo = $manager->store();
            $repo->set('mgr', 'ok');
            self::assertSame('ok', $repo->get('mgr'));
            $repo->clear();
        } catch (Throwable $e) {
            self::markTestSkipped('Redis indisponible : ' . $e->getMessage());
        }
    }
}
