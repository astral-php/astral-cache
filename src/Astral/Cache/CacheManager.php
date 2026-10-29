<?php

declare(strict_types=1);

namespace Astral\Cache;

use Astral\Cache\Store\ArrayStore;
use Astral\Cache\Store\CoreCacheStore;
use Astral\Cache\Store\FileStore;
use Astral\Cache\Store\MemcacheStore;
use Astral\Cache\Store\RedisStore;
use InvalidArgumentException;

/**
 * Résout un CacheRepository depuis la configuration.
 *
 * @phpstan-type StoreConfig array{
 *     driver?: string,
 *     path?: string,
 *     prefix?: string,
 *     host?: string,
 *     port?: int,
 *     password?: string|null,
 *     database?: int,
 *     timeout?: float,
 *     persistent_id?: string
 * }
 * @phpstan-type CacheConfig array{
 *     default?: string,
 *     prefix?: string,
 *     ttl?: int,
 *     stores?: array<string, StoreConfig>
 * }
 */
final class CacheManager
{
    /** @var array<string, CacheRepository> */
    private array $resolved = [];

    /**
     * @param CacheConfig $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    public function store(?string $name = null): CacheRepository
    {
        $name ??= (string) ($this->config['default'] ?? 'file');

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        return $this->resolved[$name] = $this->resolve($name);
    }

    private function resolve(string $name): CacheRepository
    {
        $stores = $this->config['stores'] ?? [];

        if (!isset($stores[$name]) || !is_array($stores[$name])) {
            throw new InvalidArgumentException("Cache store \"{$name}\" is not defined.");
        }

        /** @var StoreConfig $storeConfig */
        $storeConfig = $stores[$name];
        $driver      = strtolower((string) ($storeConfig['driver'] ?? $name));
        $prefix      = (string) ($storeConfig['prefix'] ?? $this->config['prefix'] ?? '');
        $ttl         = (int) ($this->config['ttl'] ?? 3600);

        $store = match ($driver) {
            'array' => new ArrayStore($prefix),
            'file'  => new FileStore(
                (string) ($storeConfig['path'] ?? $this->defaultFilePath()),
                $prefix,
            ),
            'core' => CoreCacheStore::forDirectory(
                (string) ($storeConfig['path'] ?? $this->defaultFilePath())
            ),
            'redis' => RedisStore::connect([
                'host'      => $storeConfig['host'] ?? $_ENV['REDIS_HOST'] ?? '127.0.0.1',
                'port'      => $storeConfig['port'] ?? $_ENV['REDIS_PORT'] ?? 6379,
                'password'  => $storeConfig['password'] ?? $_ENV['REDIS_PASSWORD'] ?? '',
                'database'  => $storeConfig['database'] ?? $_ENV['REDIS_DB'] ?? 0,
                'timeout'   => $storeConfig['timeout'] ?? 2.0,
                'prefix'    => $prefix !== '' ? $prefix : 'astral:cache:',
            ]),
            'memcache', 'memcached' => MemcacheStore::connect([
                'host'          => $storeConfig['host'] ?? $_ENV['MEMCACHE_HOST'] ?? '127.0.0.1',
                'port'          => $storeConfig['port'] ?? $_ENV['MEMCACHE_PORT'] ?? 11211,
                'prefix'        => $prefix !== '' ? $prefix : 'astral:cache:',
                'persistent_id' => $storeConfig['persistent_id'] ?? '',
            ]),
            default => throw new InvalidArgumentException(
                "Cache driver inconnu : {$driver} (array | file | core | redis | memcache)."
            ),
        };

        return new CacheRepository($store, $ttl);
    }

    private function defaultFilePath(): string
    {
        if (defined('BASE_PATH')) {
            return BASE_PATH . '/storage/cache';
        }

        return getcwd() . '/storage/cache';
    }
}
