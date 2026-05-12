<?php

declare(strict_types=1);

require_once __DIR__ . '/../../helpers.php';

use Marko\Core\Path\ProjectPaths;
use Marko\PageCache\CachePolicy;
use Marko\Routing\Http\Response;

it('resolves a relative path against the project base directory', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-base-' . bin2hex(random_bytes(8));
    mkdir($baseDir, 0755, true);

    $paths = new ProjectPaths($baseDir);
    $driver = createPageCacheFileDriverWithPaths('storage/page-cache', 3600, $paths);

    $request = createTestRequest();
    $response = new Response(body: 'Hello');
    $policy = new CachePolicy(ttl: 3600, tags: []);

    $driver->store($request, $response, $policy);

    $expectedDir = $baseDir . '/storage/page-cache/pages';
    expect(is_dir($expectedDir))->toBeTrue();

    cleanupPageCacheDir($baseDir);
});

it('uses an absolute path as-is when configured with an absolute path', function (): void {
    $absolutePath = sys_get_temp_dir() . '/marko-abs-cache-' . bin2hex(random_bytes(8));
    $baseDir = sys_get_temp_dir() . '/marko-base-' . bin2hex(random_bytes(8));
    mkdir($baseDir, 0755, true);

    $paths = new ProjectPaths($baseDir);
    $driver = createPageCacheFileDriverWithPaths($absolutePath, 3600, $paths);

    $request = createTestRequest();
    $response = new Response(body: 'Hello');
    $policy = new CachePolicy(ttl: 3600, tags: []);

    $driver->store($request, $response, $policy);

    $expectedDir = $absolutePath . '/pages';
    $unexpectedDir = $baseDir . '/' . ltrim($absolutePath, '/') . '/pages';

    expect(is_dir($expectedDir))->toBeTrue()
        ->and(is_dir($unexpectedDir))->toBeFalse();

    cleanupPageCacheDir($absolutePath);
    cleanupPageCacheDir($baseDir);
});

it('stores page cache files outside the public directory when using the default relative path', function (): void {
    $projectBase = sys_get_temp_dir() . '/marko-project-' . bin2hex(random_bytes(8));
    $publicDir = $projectBase . '/public';
    mkdir($publicDir, 0755, true);

    $paths = new ProjectPaths($projectBase);
    // Default config path is 'storage/page-cache'
    $driver = createPageCacheFileDriverWithPaths('storage/page-cache', 3600, $paths);

    $request = createTestRequest();
    $response = new Response(body: 'Hello');
    $policy = new CachePolicy(ttl: 3600, tags: []);

    $driver->store($request, $response, $policy);

    $expectedDir = $projectBase . '/storage/page-cache/pages';
    $forbiddenDir = $publicDir . '/storage/page-cache/pages';

    expect(is_dir($expectedDir))->toBeTrue()
        ->and(is_dir($forbiddenDir))->toBeFalse();

    cleanupPageCacheDir($projectBase);
});
