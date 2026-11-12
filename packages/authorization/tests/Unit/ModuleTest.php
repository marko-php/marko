<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Unit;

use Marko\Authorization\Contracts\GateInterface;
use Marko\Authorization\Discovery\GateHookCacheContributor;
use Marko\Authorization\Gate;
use Marko\Authorization\GateHookRegistry;
use Marko\Authorization\PolicyRegistry;

it('registers GateHookRegistry as a singleton and contributes gate hooks to the discovery cache', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['singletons'])->toContain(GateHookRegistry::class)
        ->and($module['discovery'])->toBe([GateHookCacheContributor::class]);
});

it('creates module.php with Gate binding', function (): void {
    $modulePath = dirname(__DIR__, 2) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(GateInterface::class);
});

it('registers PolicyRegistry and GateInterface as singletons', function (): void {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $module = require $modulePath;

    expect($module)->toHaveKey('singletons')
        ->and($module['singletons'])->toContain(PolicyRegistry::class)
        ->and($module['singletons'])->toContain(GateInterface::class);
});

it('binds GateInterface to Gate as a class binding', function (): void {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $module = require $modulePath;

    expect($module['bindings'][GateInterface::class])->toBe(Gate::class);
});

it('declares only the guard argument for Gate', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['arguments'][Gate::class])->toHaveKey('guard')
        ->and(array_keys($module['arguments'][Gate::class]))->toBe(['guard']);
});

it('includes config/authorization.php with sensible defaults', function (): void {
    $configPath = dirname(__DIR__, 2) . '/config/authorization.php';

    expect(file_exists($configPath))->toBeTrue();

    $config = require $configPath;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('default_guard')
        ->and($config['default_guard'])->toBeNull();
});
