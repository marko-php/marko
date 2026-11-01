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

it('uses a UNC path as-is instead of prefixing the project base directory', function (): void {
    // Run from a scratch directory: on a non-Windows host the UNC path is a
    // relative filename, so writing to it as-is lands under the cwd.
    $workDir = sys_get_temp_dir() . '/marko-unc-cwd-' . bin2hex(random_bytes(8));
    $baseDir = sys_get_temp_dir() . '/marko-base-' . bin2hex(random_bytes(8));
    mkdir($workDir, 0755, true);
    mkdir($baseDir, 0755, true);
    $originalCwd = getcwd();
    chdir($workDir);

    try {
        $uncPath = '\\\\server\\share\\page-cache';
        $driver = createPageCacheFileDriverWithPaths($uncPath, 3600, new ProjectPaths($baseDir));

        $driver->store(createTestRequest(), new Response(body: 'Hello'), new CachePolicy(ttl: 3600, tags: []));

        expect(is_dir($workDir . '/' . $uncPath . '/pages'))->toBeTrue()
            ->and(is_dir($baseDir . '/' . $uncPath))->toBeFalse();
    } finally {
        chdir($originalCwd);
        cleanupPageCacheDir($workDir);
        cleanupPageCacheDir($baseDir);
    }
});
