<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Unit;

use Marko\Authorization\Contracts\GateInterface;
use Marko\Authorization\PolicyRegistry;

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

it('wires Gate with factory closure', function (): void {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $module = require $modulePath;

    expect($module['bindings'][GateInterface::class])->toBeCallable();
});

it('includes config/authorization.php with sensible defaults', function (): void {
    $configPath = dirname(__DIR__, 2) . '/config/authorization.php';

    expect(file_exists($configPath))->toBeTrue();

    $config = require $configPath;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('default_guard')
        ->and($config['default_guard'])->toBeNull();
});
