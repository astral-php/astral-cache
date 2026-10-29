<?php

declare(strict_types=1);

namespace Astral\Cache\Store;

use Astral\Cache\CacheStoreInterface;
use Astral\Cache\Support\ValueGuard;
use Redis;
use RuntimeException;
use Throwable;

/**
 * Cache Redis (ext-redis / phpredis).
 *
 * - TTL natif (SET EX)
 * - clear() = SCAN + DEL sur le préfixe uniquement (jamais FLUSHDB)
 * - Payload sérialisé : ['value' => mixed] (autorise null / false)
 */
final class RedisStore implements CacheStoreInterface
{
    public function __construct(
        private readonly Redis $redis,
        private readonly string $prefix = 'astral:cache:',
    ) {}

    /**
     * @param array{
     *     host?: string,
     *     port?: int,
     *     password?: string|null,
     *     database?: int,
     *     timeout?: float,
     *     prefix?: string
     * } $config
     */
    public static function connect(array $config = []): self
    {
        if (!class_exists(Redis::class)) {
            throw new RuntimeException(
                'CACHE_DRIVER=redis nécessite l\'extension PHP redis (ext-redis).'
            );
        }

        $redis   = new Redis();
        $host    = (string) ($config['host'] ?? '127.0.0.1');
        $port    = (int) ($config['port'] ?? 6379);
        $timeout = (float) ($config['timeout'] ?? 2.0);

        try {
            $ok = $redis->connect($host, $port, $timeout);
        } catch (Throwable $e) {
            throw new RuntimeException(
                sprintf('Unable to connect to Redis at %s:%d.', $host, $port),
                0,
                $e
            );
        }

        if ($ok !== true) {
            throw new RuntimeException(
                sprintf('Unable to connect to Redis at %s:%d.', $host, $port)
            );
        }

        $password = (string) ($config['password'] ?? '');
        if ($password !== '') {
            $redis->auth($password);
        }

        $database = (int) ($config['database'] ?? 0);
        if ($database > 0) {
            $redis->select($database);
        }

        return new self($redis, (string) ($config['prefix'] ?? 'astral:cache:'));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->redis->get($this->fullKey($key));

        if ($raw === false) {
            return $default;
        }

        if (!is_string($raw)) {
            return $default;
        }

        $data = @unserialize($raw, ['allowed_classes' => false]);

        if (!is_array($data) || !array_key_exists('value', $data)) {
            $this->delete($key);

            return $default;
        }

        if (ValueGuard::containsObject($data['value'])) {
            $this->delete($key);

            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        ValueGuard::assertPositiveTtl($ttl);
        ValueGuard::assertStorable($value);

        $payload = serialize(['value' => $value]);
        $ok      = $this->redis->set($this->fullKey($key), $payload, ['ex' => $ttl]);

        if ($ok !== true) {
            throw new RuntimeException(sprintf(
                'Unable to write Redis cache key "%s".',
                $key
            ));
        }
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->fullKey($key)) > 0;
    }

    public function delete(string $key): void
    {
        $this->redis->del($this->fullKey($key));
    }

    public function clear(): int
    {
        $pattern = $this->prefix . '*';
        $count   = 0;
        $cursor  = null;

        do {
            $keys = $this->redis->scan($cursor, $pattern, 100);

            if ($keys === false) {
                break;
            }

            if ($keys !== []) {
                $deleted = $this->redis->del(...$keys);
                $count  += is_int($deleted) ? $deleted : count($keys);
            }
        } while ($cursor > 0);

        return $count;
    }

    private function fullKey(string $key): string
    {
        return $this->prefix . $key;
    }
}
