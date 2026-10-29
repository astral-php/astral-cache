<?php

declare(strict_types=1);

namespace Astral\Cache\Store;

use Astral\Cache\CacheStoreInterface;
use Astral\Cache\Support\ValueGuard;
use Core\Cache;

/**
 * Adaptateur : enveloppe Core\Cache (fichier historique) derrière CacheStoreInterface.
 *
 * Permet de migrer progressivement vers astral-cache sans changer le backend fichier core.
 */
final class CoreCacheStore implements CacheStoreInterface
{
    public function __construct(
        private readonly Cache $cache,
    ) {}

    public static function forDirectory(string $cacheDir): self
    {
        return new self(new Cache($cacheDir));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($key, $default);
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        ValueGuard::assertPositiveTtl($ttl);
        ValueGuard::assertStorable($value);
        $this->cache->set($key, $value, $ttl);
    }

    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }

    public function delete(string $key): void
    {
        $this->cache->forget($key);
    }

    public function clear(): int
    {
        return $this->cache->flush();
    }
}
