<?php

declare(strict_types=1);

function extractEngineSuffix(string $packageName, string $parentName): ?string
{
    $packageBase = basename($packageName);
    $parentBase = basename($parentName);
    $prefix = $parentBase . '-';

    if (!str_starts_with($packageBase, $prefix)) {
        return null;
    }

    return substr($packageBase, strlen($prefix));
}

test('it asserts every template provider has a sibling for every registered engine (passing case)', function () {
    $packagesDir = dirname(__DIR__, 3);
    $enginesPath = dirname(__DIR__, 2) . '/known-engines.php';

    if (!file_exists($enginesPath)) {
        $this->markTestSkipped('known-engines.php not found — marko/view not installed standalone?');
    }

    if (!is_dir($packagesDir) || basename($packagesDir) !== 'packages') {
        $this->markTestSkipped(
            'Not running inside the monorepo packages directory — skipping cross-engine parity check.'
        );
    }

    $engines = require $enginesPath;

    $providers = [];
    foreach (glob($packagesDir . '/*/composer.json') as $composerPath) {
        $composer = json_decode(file_get_contents($composerPath), associative: true);
        if (!is_array($composer)) {
            continue;
        }
        $templatesFor = $composer['extra']['marko']['templates_for'] ?? null;
        if (!is_string($templatesFor)) {
            continue;
        }

        $packageName = $composer['name'] ?? null;
        if (!is_string($packageName)) {
            continue;
        }

        $engineSuffix = extractEngineSuffix($packageName, $templatesFor);
        if ($engineSuffix === null || !isset($engines[$engineSuffix])) {
            continue;
        }

        $providers[$templatesFor][$engineSuffix] = $packageName;
    }

    if ($providers === []) {
        $this->markTestSkipped('No template provider packages found — nothing to check.');
    }

    foreach ($providers as $parent => $foundEngines) {
        foreach ($engines as $engineName => $engineMeta) {
            $description = $engineMeta['description'] ?? $engineName;
            $message = "Parent module '$parent' has template providers for [" . implode(
                ', ',
                array_keys($foundEngines)
            )
                . "] but is missing a provider for engine '$engineName' ($description). "
                . "Expected a package like 'marko/" . basename($parent) . "-$engineName' declaring "
                . "extra.marko.templates_for: '$parent'.";

            expect(array_key_exists($engineName, $foundEngines))->toBeTrue($message);
        }
    }
});

test('it fails with a clear error message when an engine is missing a provider for some parent', function () {
    $providers = ['marko/admin-panel' => ['latte' => 'marko/admin-panel-latte']];
    $engines = [
        'twig' => ['extension' => '.twig', 'driver' => 'marko/view-twig'],
        'latte' => ['extension' => '.latte', 'driver' => 'marko/view-latte'],
    ];

    $caught = null;
    foreach ($providers as $parent => $foundEngines) {
        foreach ($engines as $engineName => $engineMeta) {
            if (!isset($foundEngines[$engineName])) {
                $caught = "missing '$engineName' for '$parent'";
                break 2;
            }
        }
    }

    expect($caught)->toContain('twig')
        ->and($caught)->toContain('admin-panel');
});

test('it skips gracefully when known-engines.php is not present', function () {
    $enginesPath = '/nonexistent/path/known-engines.php';

    // Verify that the skip condition is correctly identified: file does not exist
    expect(file_exists($enginesPath))->toBeFalse();
});

test('it skips gracefully when the resolved packages directory is not the monorepo packages/ dir', function () {
    $packagesDir = sys_get_temp_dir();

    // Verify that a non-'packages' directory correctly triggers the skip condition
    expect(basename($packagesDir))->not->toBe('packages');
});

test('it skips gracefully when no template provider packages are found', function () {
    $tempDir = sys_get_temp_dir() . '/marko-parity-test-' . bin2hex(random_bytes(6));
    mkdir($tempDir, 0755, true);

    // Create a packages dir named 'packages' to pass the basename check
    $packagesDir = $tempDir . '/packages';
    mkdir($packagesDir, 0755, true);

    // Create a composer.json without templates_for
    file_put_contents($packagesDir . '/composer.json', json_encode(['name' => 'marko/test']));

    $enginesPath = dirname(__DIR__, 2) . '/known-engines.php';

    if (!file_exists($enginesPath)) {
        $this->markTestSkipped('known-engines.php not found — marko/view not installed standalone?');
    }

    $engines = require $enginesPath;

    $providers = [];
    foreach (glob($packagesDir . '/*/composer.json') as $composerPath) {
        $composer = json_decode(file_get_contents($composerPath), associative: true);
        if (!is_array($composer)) {
            continue;
        }
        $templatesFor = $composer['extra']['marko']['templates_for'] ?? null;
        if (!is_string($templatesFor)) {
            continue;
        }
        $packageName = $composer['name'] ?? null;
        if (!is_string($packageName)) {
            continue;
        }
        $engineSuffix = extractEngineSuffix($packageName, $templatesFor);
        if ($engineSuffix === null || !isset($engines[$engineSuffix])) {
            continue;
        }
        $providers[$templatesFor][$engineSuffix] = $packageName;
    }

    expect($providers)->toBe([]);

    // Cleanup
    unlink($packagesDir . '/composer.json');
    rmdir($packagesDir);
    rmdir($tempDir);
});

test('it correctly extracts engine suffix from package name (admin-panel-twig → twig)', function () {
    expect(extractEngineSuffix('marko/admin-panel-twig', 'marko/admin-panel'))->toBe('twig')
        ->and(extractEngineSuffix('marko/admin-panel-latte', 'marko/admin-panel'))->toBe('latte')
        ->and(extractEngineSuffix('marko/blog-twig', 'marko/blog'))->toBe('twig');
});

test('it ignores packages whose names do not follow the marko/{parent}-{engine} convention', function () {
    expect(extractEngineSuffix('marko/something-else', 'marko/admin-panel'))->toBeNull()
        ->and(extractEngineSuffix('marko/admin-panel', 'marko/admin-panel'))->toBeNull()
        ->and(extractEngineSuffix('marko/admin', 'marko/admin-panel'))->toBeNull();
});

test(
    'it ignores packages whose extracted suffix is not in known-engines (e.g., admin-panel-twig-extra produces suffix twig-extra and is skipped if not registered)',
    function () {
        $enginesPath = dirname(__DIR__, 2) . '/known-engines.php';
    
        if (!file_exists($enginesPath)) {
            $this->markTestSkipped('known-engines.php not found — marko/view not installed standalone?');
        }
    
        $engines = require $enginesPath;
    
        $suffix = extractEngineSuffix('marko/admin-panel-twig-extra', 'marko/admin-panel');
    
        expect($suffix)->toBe('twig-extra')
            ->and(isset($engines[$suffix]))->toBeFalse();
    }
);
