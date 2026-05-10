<?php

declare(strict_types=1);

use Marko\PageCache\Config\PageCacheConfig;
use Marko\PageCache\File\Driver\FilePageCacheDriver;
use Marko\Routing\Http\Request;
use Marko\Testing\Fake\FakeConfigRepository;

function createPageCacheConfig(string $path, int $defaultTtl = 3600): PageCacheConfig
{
    return new PageCacheConfig(new FakeConfigRepository([
        'page-cache.driver' => 'file',
        'page-cache.path' => $path,
        'page-cache.default_ttl' => $defaultTtl,
        'page-cache.cacheable_status_codes' => [200],
        'page-cache.cacheable_methods' => ['GET'],
    ]));
}

function createTestRequest(string $method = 'GET', string $path = '/test', array $query = []): Request
{
    return new Request(
        server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $path],
        query: $query,
    );
}

function createPageCacheFileDriver(string $tmpDir, int $defaultTtl = 3600): FilePageCacheDriver
{
    return new FilePageCacheDriver(createPageCacheConfig($tmpDir, $defaultTtl));
}

function cleanupPageCacheDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }

    rmdir($dir);
}

function writeExpiredPageCacheEntry(string $tmpDir, string $hash): void
{
    $pagesDir = $tmpDir . '/pages';
    if (!is_dir($pagesDir)) {
        mkdir($pagesDir, 0755, true);
    }

    $data = [
        'status_code' => 200,
        'body' => 'expired body',
        'headers' => [],
        'tags' => [],
        'expires_at' => time() - 10,
        'created_at' => time() - 20,
    ];

    file_put_contents($pagesDir . '/' . $hash . '.cache', serialize($data));
}
