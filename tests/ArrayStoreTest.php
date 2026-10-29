<?php

declare(strict_types=1);

namespace Astral\Cache\Tests;

use Astral\Cache\CacheStoreInterface;
use Astral\Cache\Store\ArrayStore;
use Astral\Cache\Tests\Contract\CacheStoreContractTestCase;

final class ArrayStoreTest extends CacheStoreContractTestCase
{
    protected function createStore(): CacheStoreInterface
    {
        return new ArrayStore('test:');
    }
}
