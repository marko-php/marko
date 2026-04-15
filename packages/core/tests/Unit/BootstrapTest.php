<?php

declare(strict_types=1);

use Marko\Core\Application;

it('still returns an Application instance from the bootstrap closure', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-bootstrap-test-' . uniqid();
    $vendorDir = $baseDir . '/vendor';
    $modulesDir = $baseDir . '/modules';
    $appDir = $baseDir . '/app';

    mkdir($vendorDir, 0755, true);

    $bootstrap = require dirname(__DIR__, 2) . '/bootstrap.php';

    $app = $bootstrap(
        vendorPath: $vendorDir,
        modulesPath: $modulesDir,
        appPath: $appDir,
    );

    expect($app)->toBeInstanceOf(Application::class);

    // Cleanup
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($baseDir);
});

it('loads environment variables during initialize() using class_exists(EnvLoader::class) guard', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-bootstrap-test-' . uniqid();
    $vendorDir = $baseDir . '/vendor';

    mkdir($vendorDir, 0755, true);

    // Write a .env file in the base path (dirname of vendorPath)
    $envKey = 'MARKO_TEST_INIT_ENV_' . strtoupper(uniqid());
    file_put_contents($baseDir . '/.env', $envKey . '=initialize_loaded');

    // Call initialize() directly — env loading must happen inside initialize()
    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );
    $app->initialize();

    $value = getenv($envKey);
    putenv($envKey); // cleanup env

    expect($value)->toBe('initialize_loaded');

    // Cleanup
    unlink($baseDir . '/.env');
    rmdir($vendorDir);
    rmdir($baseDir);
});

it('derives basePath for env loading via dirname($this->vendorPath) inside initialize()', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-bootstrap-test-' . uniqid();
    // Use a non-standard vendor path name to confirm dirname() is used
    $vendorDir = $baseDir . '/my-vendor';

    mkdir($vendorDir, 0755, true);

    // Write .env in the derived base path (dirname of vendorDir = baseDir)
    $envKey = 'MARKO_TEST_DERIVE_BASE_' . strtoupper(uniqid());
    file_put_contents($baseDir . '/.env', $envKey . '=derived_base');

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );
    $app->initialize();

    $value = getenv($envKey);
    putenv($envKey); // cleanup env

    expect($value)->toBe('derived_base');

    // Cleanup
    unlink($baseDir . '/.env');
    rmdir($vendorDir);
    rmdir($baseDir);
});

it('still accepts explicit vendorPath, modulesPath, and appPath parameters', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-bootstrap-test-' . uniqid();
    $vendorDir = $baseDir . '/vendor';
    $modulesDir = $baseDir . '/modules';
    $appDir = $baseDir . '/app';

    mkdir($vendorDir, 0755, true);

    $bootstrap = require dirname(__DIR__, 2) . '/bootstrap.php';

    $app = $bootstrap(
        vendorPath: $vendorDir,
        modulesPath: $modulesDir,
        appPath: $appDir,
    );

    expect($app->vendorPath)->toBe($vendorDir)
        ->and($app->modulesPath)->toBe($modulesDir)
        ->and($app->appPath)->toBe($appDir);

    // Cleanup
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($baseDir);
});
