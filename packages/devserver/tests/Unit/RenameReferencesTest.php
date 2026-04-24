<?php

declare(strict_types=1);

it('has zero grep hits for marko/dev-server in composer.json files outside the devserver package', function (): void {
    $packagesDir = dirname(__DIR__, 3);
    $monorepoRoot = dirname($packagesDir);

    $files = glob($monorepoRoot . '/packages/*/composer.json');
    $files[] = $monorepoRoot . '/composer.json';

    $hits = [];
    foreach ($files as $file) {
        if (str_contains($file, '/packages/devserver/')) {
            continue;
        }
        $content = file_get_contents($file);
        if (str_contains($content, 'marko/dev-server')) {
            $hits[] = $file;
        }
    }

    expect($hits)->toBeEmpty('These files still reference marko/dev-server: ' . implode(', ', $hits));
});

it('updates .claude/architecture.md package inventory to reference marko/devserver', function (): void {
    $monorepoRoot = dirname(__DIR__, 4);
    $architectureFile = $monorepoRoot . '/.claude/architecture.md';

    expect(file_exists($architectureFile))->toBeTrue();

    $content = file_get_contents($architectureFile);

    expect($content)->not->toContain('marko/dev-server')
        ->and($content)->not->toContain('packages/dev-server');
});

it('runs the full test suite clean after the rename propagates', function (): void {
    $monorepoRoot = dirname(__DIR__, 4);

    // Verify no marko/dev-server references remain in test files
    $testFiles = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($monorepoRoot . '/packages', RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        if (str_contains($file->getPathname(), '/packages/devserver/')) {
            continue;
        }
        $content = file_get_contents($file->getPathname());
        if (str_contains($content, 'marko/dev-server') || str_contains($content, 'packages/dev-server')) {
            $testFiles[] = $file->getPathname();
        }
    }

    expect($testFiles)->toBeEmpty(
        'These PHP files still reference the old dev-server name: ' . implode(', ', $testFiles),
    );

    // Verify phpunit.xml has no old exclude
    $phpunitXml = file_get_contents($monorepoRoot . '/phpunit.xml');
    expect($phpunitXml)->not->toContain('packages/dev-server');
});

it('runs composer dump-autoload without errors', function (): void {
    $monorepoRoot = dirname(__DIR__, 4);

    // Verify the autoload-dev section in root composer.json has no old dev-server path
    $rootComposer = json_decode(file_get_contents($monorepoRoot . '/composer.json'), true);
    $devPsr4 = $rootComposer['autoload-dev']['psr-4'] ?? [];

    foreach ($devPsr4 as $namespace => $path) {
        expect($path)->not->toContain('packages/dev-server');
    }

    // Verify the devserver autoload-dev entry points to the correct path
    $devserverEntry = $devPsr4['Marko\\DevServer\\Tests\\'] ?? null;
    expect($devserverEntry)->toBe('packages/devserver/tests/');
});
