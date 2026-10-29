<?php

declare(strict_types=1);

namespace Astral\Cache\Store;

use Astral\Cache\CacheStoreInterface;
use Astral\Cache\Support\ValueGuard;
use Memcache;
use Memcached;
use RuntimeException;
use Throwable;

/**
 * Cache Memcache / Memcached (opt-in).
 *
 * - Windows / Laragon : souvent `ext-memcache` (classe Memcache)
 * - Linux / Docker : souvent `ext-memcached` (classe Memcached)
 * - clear() = bump de génération (invalidation lazy) — jamais flush serveur partagé
 * - Clés hachées (contraintes longueur / caractères Memcache)
 */
final class MemcacheStore implements CacheStoreInterface
{
    private const GEN_SUFFIX = '__generation';

    private int $generation;

    public function __construct(
        private readonly Memcache|Memcached $client,
        private readonly string $prefix = 'astral:cache:',
    ) {
        $this->generation = $this->loadGeneration();
    }

    /**
     * @param array{
     *     host?: string,
     *     port?: int,
     *     prefix?: string,
     *     persistent_id?: string
     * } $config
     */
    public static function connect(array $config = []): self
    {
        $host   = (string) ($config['host'] ?? '127.0.0.1');
        $port   = (int) ($config['port'] ?? 11211);
        $prefix = (string) ($config['prefix'] ?? 'astral:cache:');

        if (class_exists(Memcached::class)) {
            $client = new Memcached((string) ($config['persistent_id'] ?? ''));
            if ($client->getServerList() === []) {
                $client->addServer($host, $port);
            }

            return new self($client, $prefix);
        }

        if (class_exists(Memcache::class)) {
            $client = new Memcache();
            try {
                $ok = @$client->connect($host, $port);
            } catch (Throwable $e) {
                throw new RuntimeException(
                    sprintf('Unable to connect to Memcache at %s:%d.', $host, $port),
                    0,
                    $e
                );
            }

            if ($ok !== true) {
                throw new RuntimeException(
                    sprintf('Unable to connect to Memcache at %s:%d.', $host, $port)
                );
            }

            return new self($client, $prefix);
        }

        throw new RuntimeException(
            'CACHE_DRIVER=memcache nécessite ext-memcache ou ext-memcached.'
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->clientGet($this->storageKey($key));

        if ($raw === false || !is_string($raw)) {
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
        $ok      = $this->clientSet($this->storageKey($key), $payload, $ttl);

        if ($ok !== true) {
            throw new RuntimeException(sprintf(
                'Unable to write Memcache key "%s".',
                $key
            ));
        }
    }

    public function has(string $key): bool
    {
        $raw = $this->clientGet($this->storageKey($key));

        if ($raw === false || !is_string($raw)) {
            return false;
        }

        $data = @unserialize($raw, ['allowed_classes' => false]);

        return is_array($data) && array_key_exists('value', $data)
            && !ValueGuard::containsObject($data['value']);
    }

    public function delete(string $key): void
    {
        $this->clientDelete($this->storageKey($key));
    }

    /**
     * Invalide le namespace courant (génération++) — n'appelle jamais flush().
     *
     * @return int Toujours 1 (génération avancée) ; les anciennes clés expirent via TTL
     */
    public function clear(): int
    {
        $this->generation++;
        $this->clientSet($this->generationKey(), (string) $this->generation, 0);

        return 1;
    }

    private function loadGeneration(): int
    {
        $raw = $this->clientGet($this->generationKey());

        if (is_string($raw) && ctype_digit($raw)) {
            return (int) $raw;
        }

        if (is_int($raw)) {
            return $raw;
        }

        $this->clientSet($this->generationKey(), '1', 0);

        return 1;
    }

    private function generationKey(): string
    {
        return $this->safeKey($this->prefix . self::GEN_SUFFIX);
    }

    private function storageKey(string $key): string
    {
        return $this->safeKey($this->prefix . $this->generation . ':' . $key);
    }

    /** Memcache : max ~250 chars, pas d'espaces / contrôles. */
    private function safeKey(string $key): string
    {
        return hash('sha256', $key);
    }

    private function clientGet(string $key): mixed
    {
        return $this->client->get($key);
    }

    private function clientSet(string $key, string $payload, int $ttl): bool
    {
        if ($this->client instanceof Memcached) {
            return $this->client->set($key, $payload, $ttl);
        }

        // Memcache : flag 0, expire = TTL (0 = pas d'expiration — utilisé pour la génération)
        return (bool) $this->client->set($key, $payload, 0, $ttl);
    }

    private function clientDelete(string $key): void
    {
        $this->client->delete($key);
    }
}
