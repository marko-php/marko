<?php

declare(strict_types=1);

use Marko\Config\ConfigDiscovery;
use Marko\Config\ConfigLoader;
use Marko\Config\ConfigMerger;

it('can be instantiated with ConfigLoader and ConfigMerger', function () {
    $loader = new ConfigLoader();
    $merger = new ConfigMerger();

    $discovery = new ConfigDiscovery(
        loader: $loader,
        merger: $merger,
    );

    expect($discovery)->toBeInstanceOf(ConfigDiscovery::class);
});

it('discovers config from a single module', function () {
    $loader = new ConfigLoader();
    $merger = new ConfigMerger();
    $discovery = new ConfigDiscovery(
        loader: $loader,
        merger: $merger,
    );

    $modulePaths = [__DIR__ . '/fixtures/discovery/module-a'];

    $result = $discovery->discover(
        modulePaths: $modulePaths,
        rootConfigPath: __DIR__ . '/fixtures/discovery/empty-root',
    );

    expect($result)->toBe([
        'database' => [
            'host' => 'localhost',
            'port' => 3306,
        ],
    ]);
});

it('merges overlapping config from multiple modules', function () {
    $loader = new ConfigLoader();
    $merger = new ConfigMerger();
    $discovery = new ConfigDiscovery(
        loader: $loader,
        merger: $merger,
    );

    $modulePaths = [
        __DIR__ . '/fixtures/discovery/module-a',
        __DIR__ . '/fixtures/discovery/module-b',
    ];

    $result = $discovery->discover(
        modulePaths: $modulePaths,
        rootConfigPath: __DIR__ . '/fixtures/discovery/empty-root',
    );

    expect($result)->toBe([
        'database' => [
            'host' => 'db.example.com',
            'port' => 3306,
            'name' => 'app_db',
        ],
    ]);
});

it('skips modules without config directory', function () {
    $loader = new ConfigLoader();
    $merger = new ConfigMerger();
    $discovery = new ConfigDiscovery(
        loader: $loader,
        merger: $merger,
    );

    $modulePaths = [
        __DIR__ . '/fixtures/discovery/module-a',
        __DIR__ . '/fixtures/discovery/module-no-config',
    ];

    $result = $discovery->discover(
        modulePaths: $modulePaths,
        rootConfigPath: __DIR__ . '/fixtures/discovery/empty-root',
    );

    expect($result)->toBe([
        'database' => [
            'host' => 'localhost',
            'port' => 3306,
        ],
    ]);
});

it('root config overrides module config', function () {
    $loader = new ConfigLoader();
    $merger = new ConfigMerger();
    $discovery = new ConfigDiscovery(
        loader: $loader,
        merger: $merger,
    );

    $modulePaths = [__DIR__ . '/fixtures/discovery/module-a'];

    $result = $discovery->discover(
        modulePaths: $modulePaths,
        rootConfigPath: __DIR__ . '/fixtures/discovery/root-config',
    );

    expect($result)->toBe([
        'database' => [
            'host' => 'production.db.com',
            'port' => 3306,
        ],
    ]);
});

it('loads multiple config files per module', function () {
    $loader = new ConfigLoader();
    $merger = new ConfigMerger();
    $discovery = new ConfigDiscovery(
        loader: $loader,
        merger: $merger,
    );

    $modulePaths = [__DIR__ . '/fixtures/discovery/module-multi-config'];

    $result = $discovery->discover(
        modulePaths: $modulePaths,
        rootConfigPath: __DIR__ . '/fixtures/discovery/empty-root',
    );

    expect($result)->toBe([
        'cache' => [
            'driver' => 'redis',
            'ttl' => 3600,
        ],
        'database' => [
            'host' => 'localhost',
            'port' => 5432,
        ],
    ]);
});

it('respects priority order: later modules override earlier, root overrides all', function () {
    $loader = new ConfigLoader();
    $merger = new ConfigMerger();
    $discovery = new ConfigDiscovery(
        loader: $loader,
        merger: $merger,
    );

    // Priority order: module-a < module-b < root-config
    $modulePaths = [
        __DIR__ . '/fixtures/discovery/module-a',
        __DIR__ . '/fixtures/discovery/module-b',
    ];

    $result = $discovery->discover(
        modulePaths: $modulePaths,
        rootConfigPath: __DIR__ . '/fixtures/discovery/root-config',
    );

    // module-a: host=localhost, port=3306
    // module-b: host=db.example.com, name=app_db (overrides module-a host, adds name)
    // root: host=production.db.com (overrides module-b host)
    expect($result)->toBe([
        'database' => [
            'host' => 'production.db.com',
            'port' => 3306,
            'name' => 'app_db',
        ],
    ]);
});
