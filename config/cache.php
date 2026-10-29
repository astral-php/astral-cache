<?php

declare(strict_types=1);

/**
 * Exemple de config cache (copier / adapter dans l'app).
 * Les valeurs .env priment via CacheServiceProvider.
 *
 * @return array{
 *     default: string,
 *     prefix: string,
 *     ttl: int,
 *     stores: array<string, array<string, mixed>>
 * }
 */
$filePath = $_ENV['CACHE_PATH'] ?? (
    defined('BASE_PATH')
        ? BASE_PATH . '/storage/cache'
        : getcwd() . '/storage/cache'
);

return [
    'default' => $_ENV['CACHE_DRIVER'] ?? 'file',
    'prefix'  => $_ENV['CACHE_PREFIX'] ?? 'astral:',
    'ttl'     => (int) ($_ENV['CACHE_TTL'] ?? 3600),
    'stores'  => [
        'array' => [
            'driver' => 'array',
        ],
        'file' => [
            'driver' => 'file',
            'path'   => $filePath,
        ],
        'core' => [
            'driver' => 'core',
            'path'   => $filePath,
        ],
        'redis' => [
            'driver'   => 'redis',
            'host'     => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port'     => (int) ($_ENV['REDIS_PORT'] ?? 6379),
            'password' => $_ENV['REDIS_PASSWORD'] ?? '',
            'database' => (int) ($_ENV['REDIS_DB'] ?? 0),
        ],
        'memcache' => [
            'driver' => 'memcache',
            'host'   => $_ENV['MEMCACHE_HOST'] ?? '127.0.0.1',
            'port'   => (int) ($_ENV['MEMCACHE_PORT'] ?? 11211),
        ],
    ],
];
