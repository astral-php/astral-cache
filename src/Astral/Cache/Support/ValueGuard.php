<?php

declare(strict_types=1);

namespace Astral\Cache\Support;

use Astral\Cache\Exception\InvalidCacheValueException;
use InvalidArgumentException;

/**
 * Validations partagées : TTL et valeurs (pas d'objets).
 */
final class ValueGuard
{
    public static function assertPositiveTtl(int $ttl): void
    {
        if ($ttl < 1) {
            throw new InvalidArgumentException(
                'TTL must be a positive number of seconds.'
            );
        }
    }

    public static function assertStorable(mixed $value): void
    {
        if (self::containsObject($value)) {
            throw new InvalidCacheValueException(
                'Cache values must not contain objects (scalars and arrays only).'
            );
        }
    }

    public static function containsObject(mixed $value): bool
    {
        if (is_object($value)) {
            return true;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (self::containsObject($item)) {
                return true;
            }
        }

        return false;
    }
}
