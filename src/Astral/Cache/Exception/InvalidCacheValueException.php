<?php

declare(strict_types=1);

namespace Astral\Cache\Exception;

use InvalidArgumentException;

/**
 * Valeur refusée (objets, etc.) — alignement Core\Cache 2.0.
 */
final class InvalidCacheValueException extends InvalidArgumentException
{
}
