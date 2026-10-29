<?php

declare(strict_types=1);

namespace Astral\Cache\Store;

use Astral\Cache\CacheStoreInterface;
use Astral\Cache\Support\ValueGuard;
use RuntimeException;

/**
 * Cache fichier — répertoire dédié hors web public.
 *
 * Format : serialize(['expires_at' => int, 'value' => mixed])
 * Fichier : md5(prefix + key) . '.cache'
 * Lecture corrompue / objets → soft miss + unlink.
 */
final class FileStore implements CacheStoreInterface
{
    public function __construct(
        private readonly string $cacheDir,
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
        $this->ensureDir();

        $payload = serialize([
            'expires_at' => time() + $ttl,
            'value'      => $value,
        ]);

        $written = file_put_contents($this->path($key), $payload, LOCK_EX);

        if ($written === false) {
            throw new RuntimeException(sprintf(
                'Unable to write cache file for key "%s".',
                $key
            ));
        }
    }

    public function has(string $key): bool
    {
        return $this->readValidEntry($key) !== null;
    }

    public function delete(string $key): void
    {
        $file = $this->path($key);

        if (is_file($file)) {
            @unlink($file);
        }
    }

    public function clear(): int
    {
        if (!is_dir($this->cacheDir)) {
            return 0;
        }

        $count = 0;
        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.cache');

        foreach ($files ?: [] as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{expires_at: int, value: mixed}|null
     */
    private function readValidEntry(string $key): ?array
    {
        $file = $this->path($key);

        if (!is_file($file)) {
            return null;
        }

        $raw = file_get_contents($file);

        if ($raw === false) {
            return null;
        }

        $data = @unserialize($raw, ['allowed_classes' => false]);

        if (!$this->isValidPayload($data)) {
            $this->delete($key);

            return null;
        }

        /** @var array{expires_at: int, value: mixed} $data */
        if (ValueGuard::containsObject($data['value'])) {
            $this->delete($key);

            return null;
        }

        if ($data['expires_at'] < time()) {
            $this->delete($key);

            return null;
        }

        return $data;
    }

    private function isValidPayload(mixed $data): bool
    {
        return is_array($data)
            && array_key_exists('expires_at', $data)
            && array_key_exists('value', $data)
            && is_int($data['expires_at']);
    }

    private function path(string $key): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . md5($this->prefix . $key) . '.cache';
    }

    private function ensureDir(): void
    {
        if (is_dir($this->cacheDir)) {
            return;
        }

        if (!mkdir($this->cacheDir, 0755, true) && !is_dir($this->cacheDir)) {
            throw new RuntimeException(sprintf(
                'Unable to create cache directory "%s".',
                $this->cacheDir
            ));
        }
    }
}
