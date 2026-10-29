<?php

declare(strict_types=1);

namespace Astral\Cache\Tests;

use Astral\Cache\CacheStoreInterface;
use Astral\Cache\Store\CoreCacheStore;
use Astral\Cache\Tests\Contract\CacheStoreContractTestCase;

final class CoreCacheStoreTest extends CacheStoreContractTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/astral-cache-core-' . uniqid('', true);
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    protected function createStore(): CacheStoreInterface
    {
        return CoreCacheStore::forDirectory($this->dir);
    }
}
