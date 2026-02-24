<?php

declare(strict_types=1);

use Marko\Admin\Config\AdminConfig;
use Marko\Admin\Config\AdminConfigInterface;
use Marko\Admin\Exceptions\InvalidAdminConfigException;
use Marko\Testing\Fake\FakeConfigRepository;

it('uses FakeConfigRepository in AdminConfigTest', function (): void {
    $config = new FakeConfigRepository(['admin.route_prefix' => '/admin']);

    expect($config)->toBeInstanceOf(FakeConfigRepository::class);
});

it('provides default admin route prefix of /admin', function (): void {
    $config = new AdminConfig(new FakeConfigRepository([
        'admin.route_prefix' => '/admin',
    ]));

    expect($config->getRoutePrefix())->toBe('/admin');
});

it('provides configurable admin route prefix from config', function (): void {
    $config = new AdminConfig(new FakeConfigRepository([
        'admin.route_prefix' => '/backend',
    ]));

    expect($config->getRoutePrefix())->toBe('/backend');
});

it('throws InvalidAdminConfigException when route prefix does not start with slash', function (): void {
    $config = new AdminConfig(new FakeConfigRepository([
        'admin.route_prefix' => 'admin',
    ]));

    expect(fn () => $config->getRoutePrefix())
        ->toThrow(InvalidAdminConfigException::class, "must start with '/'");
});

it('provides default admin name of Admin', function (): void {
    $config = new AdminConfig(new FakeConfigRepository([
        'admin.name' => 'Admin',
    ]));

    expect($config->getName())->toBe('Admin');
});

it('provides configurable admin name from config', function (): void {
    $config = new AdminConfig(new FakeConfigRepository([
        'admin.name' => 'Dashboard',
    ]));

    expect($config->getName())->toBe('Dashboard');
});

it('has valid config/admin.php with default values', function (): void {
    $configPath = dirname(__DIR__, 3) . '/config/admin.php';
    $configData = require $configPath;

    expect(file_exists($configPath))->toBeTrue()
        ->and($configData)->toBeArray()
        ->and($configData)->toHaveKey('name')
        ->and($configData)->toHaveKey('route_prefix')
        ->and($configData['name'])->toBe('Admin')
        ->and($configData['route_prefix'])->toBe('/admin');
});

it('binds AdminConfigInterface to AdminConfig in module.php', function (): void {
    $modulePath = dirname(__DIR__, 3) . '/module.php';
    $module = require $modulePath;

    expect(file_exists($modulePath))->toBeTrue()
        ->and($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(AdminConfigInterface::class)
        ->and($module['bindings'][AdminConfigInterface::class])
            ->toBe(AdminConfig::class);
});
