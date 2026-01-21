<?php

declare(strict_types=1);

use Marko\Config\ConfigDiscovery;
use Marko\Config\ConfigLoader;
use Marko\Config\ConfigMerger;
use Marko\Config\ConfigRepositoryInterface;

it('module.php binds ConfigRepositoryInterface to factory closure', function () {
    $moduleConfig = require __DIR__ . '/../../module.php';

    expect($moduleConfig['bindings'])->toHaveKey(ConfigRepositoryInterface::class)
        ->and($moduleConfig['bindings'][ConfigRepositoryInterface::class])->toBeInstanceOf(Closure::class);
});

it('module.php binds ConfigLoader to itself', function () {
    $moduleConfig = require __DIR__ . '/../../module.php';

    expect($moduleConfig['bindings'])->toHaveKey(ConfigLoader::class)
        ->and($moduleConfig['bindings'][ConfigLoader::class])->toBe(ConfigLoader::class);
});

it('module.php binds ConfigMerger to itself', function () {
    $moduleConfig = require __DIR__ . '/../../module.php';

    expect($moduleConfig['bindings'])->toHaveKey(ConfigMerger::class)
        ->and($moduleConfig['bindings'][ConfigMerger::class])->toBe(ConfigMerger::class);
});

it('module.php binds ConfigDiscovery to itself', function () {
    $moduleConfig = require __DIR__ . '/../../module.php';

    expect($moduleConfig['bindings'])->toHaveKey(ConfigDiscovery::class)
        ->and($moduleConfig['bindings'][ConfigDiscovery::class])->toBe(ConfigDiscovery::class);
});
