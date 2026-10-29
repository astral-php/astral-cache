<?php

declare(strict_types=1);

namespace Astral\Cache\Tests;

use Astral\Cache\CacheStoreInterface;
use Astral\Cache\Store\FileStore;
use Astral\Cache\Tests\Contract\CacheStoreContractTestCase;

final class FileStoreTest extends CacheStoreContractTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/astral-cache-test-' . uniqid('', true);
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
        return new FileStore($this->dir, 'test:');
    }

    public function testCorruptPayloadIsSoftMiss(): void
    {
        $store = $this->createStore();
        $store->set('ok', 'v', 60);

        $files = glob($this->dir . DIRECTORY_SEPARATOR . '*.cache') ?: [];
        self::assertNotEmpty($files);
        file_put_contents($files[0], 'not-serialized');

        self::assertFalse($store->has('ok'));
        self::assertNull($store->get('ok'));
    }
}
