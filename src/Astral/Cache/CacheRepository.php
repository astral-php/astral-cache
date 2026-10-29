<?php

declare(strict_types=1);

namespace Astral\Cache;

/**
 * API applicative au-dessus d'un CacheStoreInterface.
 *
 * remember() est best-effort : pas de verrou stampede (aligné Core\Cache 2.0).
 */
final class CacheRepository
{
    public function __construct(
        private readonly CacheStoreInterface $store,
        private readonly int $defaultTtl = 3600,
    ) {}

    public function store(): CacheStoreInterface
    {
        return $this->store;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store->get($key, $default);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->store->set($key, $value, $ttl ?? $this->defaultTtl);
    }

    public function has(string $key): bool
    {
        return $this->store->has($key);
    }

    public function delete(string $key): void
    {
        $this->store->delete($key);
    }

    /** Alias historique Core\Cache::forget(). */
    public function forget(string $key): void
    {
        $this->delete($key);
    }

    public function clear(): int
    {
        return $this->store->clear();
    }

    /** Alias historique Core\Cache::flush(). */
    public function flush(): int
    {
        return $this->clear();
    }

    /**
     * Get-or-compute. Best-effort (pas de lock get→callback→set).
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }
}
