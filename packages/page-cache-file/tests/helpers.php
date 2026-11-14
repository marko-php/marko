<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\PageCache\Config\PageCacheConfig;
use Marko\PageCache\File\Driver\FilePageCacheDriver;
use Marko\Routing\Http\Request;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Psr\Clock\ClockInterface;

function createPageCacheConfig(
    string $path,
    int $defaultTtl = 3600,
    array $trustedHosts = [],
    int $maxVariantsPerPath = 1000,
): PageCacheConfig {
    return new PageCacheConfig(new FakeConfigRepository([
        'page-cache.driver' => 'file',
        'page-cache.path' => $path,
        'page-cache.default_ttl' => $defaultTtl,
        'page-cache.cacheable_status_codes' => [200],
        'page-cache.cacheable_methods' => ['GET'],
        'page-cache.trusted_hosts' => $trustedHosts,
        'page-cache.max_variants_per_path' => $maxVariantsPerPath,
    ]));
}

function createTestRequest(
    string $method = 'GET',
    string $path = '/test',
    array $query = [],
    string $host = 'example.com',
    bool $https = false,
): Request {
    $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $path, 'HTTP_HOST' => $host];

    if ($https) {
        $server['HTTPS'] = 'on';
    }

    return new Request(server: $server, query: $query);
}

function createPageCacheFileDriver(
    string $tmpDir,
    int $defaultTtl = 3600,
    ?ClockInterface $clock = null,
    array $trustedHosts = [],
    int $maxVariantsPerPath = 1000,
): FilePageCacheDriver {
    return new FilePageCacheDriver(
        createPageCacheConfig($tmpDir, $defaultTtl, $trustedHosts, $maxVariantsPerPath),
        new ProjectPaths($tmpDir),
        $clock ?? new FakeClock(),
    );
}

function createPageCacheFileDriverWithPaths(
    string $path,
    int $defaultTtl,
    ProjectPaths $paths,
): FilePageCacheDriver {
    return new FilePageCacheDriver(createPageCacheConfig($path, $defaultTtl), $paths, new FakeClock());
}

function cleanupPageCacheDir(
    string $dir,
): void {
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

function writeExpiredPageCacheEntry(
    string $tmpDir,
    string $hash,
    int $now,
): void {
    writePageCachePayload($tmpDir, $hash, [
        'status_code' => 200,
        'body' => 'expired body',
        'headers' => [],
        'tags' => [],
        'expires_at' => $now - 10,
        'created_at' => $now - 20,
    ]);
}

/**
 * @param array<string, mixed> $data
 */
function writePageCachePayload(
    string $tmpDir,
    string $hash,
    array $data,
): void {
    $pagesDir = $tmpDir . '/pages';
    if (!is_dir($pagesDir)) {
        mkdir($pagesDir, 0755, true);
    }

    file_put_contents($pagesDir . '/' . $hash . '.cache', serialize($data));
}
