<?php

declare(strict_types=1);

namespace Astral\Cache;

use Core\Container;
use Core\ServiceProviderInterface;

/**
 * Provider optionnel — enregistre CacheManager + CacheRepository (store par défaut).
 *
 * .env : CACHE_DRIVER=file|array|core|redis|memcache
 */
final class CacheServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, array $appConfig, array $dbConfig): void
    {
        $container->singleton(CacheManager::class, function () use ($appConfig): CacheManager {
            $fromApp = is_array($appConfig['cache'] ?? null) ? $appConfig['cache'] : [];

            return new CacheManager($this->buildConfig($fromApp));
        });

        $container->singleton(
            CacheRepository::class,
            fn (Container $c): CacheRepository => $c->make(CacheManager::class)->store()
        );

        $container->singleton(
            CacheStoreInterface::class,
            fn (Container $c): CacheStoreInterface => $c->make(CacheRepository::class)->store()
        );
    }

    /**
     * @param array<string, mixed> $fromApp
     * @return array{
     *     default: string,
     *     prefix: string,
     *     ttl: int,
     *     stores: array<string, array<string, mixed>>
     * }
     */
    private function buildConfig(array $fromApp): array
    {
        $default = (string) ($fromApp['default'] ?? $_ENV['CACHE_DRIVER'] ?? 'file');
        $prefix  = (string) ($fromApp['prefix'] ?? $_ENV['CACHE_PREFIX'] ?? 'astral:');
        $ttl     = (int) ($fromApp['ttl'] ?? $_ENV['CACHE_TTL'] ?? 3600);

        $stores = is_array($fromApp['stores'] ?? null) ? $fromApp['stores'] : [];

        $filePath = $_ENV['CACHE_PATH'] ?? (
            defined('BASE_PATH')
                ? BASE_PATH . '/storage/cache'
                : getcwd() . '/storage/cache'
        );

        $stores += [
            'array' => ['driver' => 'array'],
            'file'  => [
                'driver' => 'file',
                'path'   => $filePath,
            ],
            'core' => [
                'driver' => 'core',
                'path'   => $filePath,
            ],
            'redis' => [
                'driver' => 'redis',
                'host'   => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
                'port'   => (int) ($_ENV['REDIS_PORT'] ?? 6379),
                'password' => $_ENV['REDIS_PASSWORD'] ?? '',
                'database' => (int) ($_ENV['REDIS_DB'] ?? 0),
            ],
            'memcache' => [
                'driver' => 'memcache',
                'host'   => $_ENV['MEMCACHE_HOST'] ?? '127.0.0.1',
                'port'   => (int) ($_ENV['MEMCACHE_PORT'] ?? 11211),
            ],
        ];

        return [
            'default' => $default,
            'prefix'  => $prefix,
            'ttl'     => $ttl,
            'stores'  => $stores,
        ];
    }
}
