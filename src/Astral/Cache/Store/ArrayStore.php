<?php

declare(strict_types=1);

namespace Astral\Cache\Store;

use Astral\Cache\CacheStoreInterface;
use Astral\Cache\Support\ValueGuard;

/**
 * Cache mémoire processus — tests et usages éphémères.
 *
 * @internal storage : array<string, array{expires_at: int, value: mixed}>
 */
final class ArrayStore implements CacheStoreInterface
{
    /** @var array<string, array{expires_at: int, value: mixed}> */
    private array $items = [];

    public function __construct(
        private readonly string $prefix = '',
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->readValidEntry($key);

        if ($entry === null) {
            return $default;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        ValueGuard::assertPositiveTtl($ttl);
        ValueGuard::assertStorable($value);

        $this->items[$this->prefixed($key)] = [
            'expires_at' => time() + $ttl,
            'value'      => $value,
        ];
    }

    public function has(string $key): bool
    {
        return $this->readValidEntry($key) !== null;
    }

    public function delete(string $key): void
    {
        unset($this->items[$this->prefixed($key)]);
    }

    public function clear(): int
    {
        $count = count($this->items);
        $this->items = [];

        return $count;
    }

    /**
     * @return array{expires_at: int, value: mixed}|null
     */
    private function readValidEntry(string $key): ?array
    {
        $id = $this->prefixed($key);

        if (!isset($this->items[$id])) {
            return null;
        }

        $entry = $this->items[$id];

        if ($entry['expires_at'] < time()) {
            unset($this->items[$id]);

            return null;
        }

        return $entry;
    }

    private function prefixed(string $key): string
    {
        return $this->prefix . $key;
    }
}
