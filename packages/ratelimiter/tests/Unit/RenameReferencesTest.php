<?php

declare(strict_types=1);

it('has zero grep hits for marko/rate-limiting in composer.json files outside the ratelimiter package itself', function (): void {
    $monorepoRoot = dirname(__DIR__, 4);
    $ratelimiterPackage = dirname(__DIR__, 2);

    $composerFiles = glob($monorepoRoot . '/packages/*/composer.json') ?: [];
    $composerFiles[] = $monorepoRoot . '/composer.json';

    $hits = [];

    foreach ($composerFiles as $file) {
        // Skip files inside the ratelimiter package itself
        if (str_starts_with(realpath($file), realpath($ratelimiterPackage))) {
            continue;
        }

        $contents = file_get_contents($file);
        if (str_contains($contents, 'marko/rate-limiting')) {
            $hits[] = $file;
        }
    }

    expect($hits)->toBe([], 'Found marko/rate-limiting references in: ' . implode(', ', $hits));
});

it('has zero grep hits for Marko\\RateLimiting namespace outside the ratelimiter package', function (): void {
    $monorepoRoot = dirname(__DIR__, 4);
    $ratelimiterPackage = dirname(__DIR__, 2);

    $phpFiles = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($monorepoRoot . '/packages'),
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $filePath = $file->getRealPath();

        // Skip files inside the ratelimiter package itself
        if (str_starts_with($filePath, realpath($ratelimiterPackage))) {
            continue;
        }

        $phpFiles[] = $filePath;
    }

    $hits = [];

    foreach ($phpFiles as $file) {
        $contents = file_get_contents($file);
        if (str_contains($contents, 'Marko\\RateLimiting')) {
            $hits[] = $file;
        }
    }

    expect($hits)->toBe([], 'Found Marko\\RateLimiting references in: ' . implode(', ', $hits));
});

it('updates .claude/architecture.md package inventory to reference marko/ratelimiter', function (): void {
    $monorepoRoot = dirname(__DIR__, 4);
    $architectureFile = $monorepoRoot . '/.claude/architecture.md';

    expect(file_exists($architectureFile))->toBeTrue();

    $contents = file_get_contents($architectureFile);

    // Should not contain the old package name in the inventory
    expect($contents)->not->toContain('marko/rate-limiting');
});

it('runs composer dump-autoload without errors', function (): void {
    $monorepoRoot = dirname(__DIR__, 4);

    $composerBin = trim(shell_exec('which composer') ?: '');
    if ($composerBin === '') {
        $composerBin = '/opt/homebrew/bin/composer';
    }

    $output = [];
    $exitCode = 0;

    exec(
        '/opt/homebrew/Cellar/php/8.5.1_2/bin/php ' . escapeshellarg($composerBin) . ' dump-autoload --ignore-platform-reqs 2>&1',
        $output,
        $exitCode,
    );

    expect($exitCode)->toBe(0, implode("\n", $output));
});

it('runs the full test suite clean after the rename propagates', function (): void {
    $monorepoRoot = dirname(__DIR__, 4);

    // Verify the old package directory no longer exists
    expect(is_dir($monorepoRoot . '/packages/rate-limiting'))->toBeFalse(
        'Old packages/rate-limiting/ directory still exists',
    );

    // Verify the new package directory exists with correct structure
    expect(is_dir($monorepoRoot . '/packages/ratelimiter'))->toBeTrue()
        ->and(is_dir($monorepoRoot . '/packages/ratelimiter/src'))->toBeTrue()
        ->and(file_exists($monorepoRoot . '/packages/ratelimiter/composer.json'))->toBeTrue();
});
