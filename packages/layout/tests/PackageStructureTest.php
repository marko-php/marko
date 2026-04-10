<?php

declare(strict_types=1);

it('has a valid composer.json with correct name, dependencies, and autoload', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toBeArray()
        ->and($composer['name'])->toBe('marko/layout')
        ->and($composer['autoload']['psr-4']['Marko\\Layout\\'])->toBe('src/')
        ->and($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require'])->toHaveKey('marko/view')
        ->and($composer['require'])->toHaveKey('marko/routing');
});

it('has a module.php that returns a valid module configuration array', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray();
});

it('has a config/layout.php that returns a configuration array', function (): void {
    $configPath = dirname(__DIR__) . '/config/layout.php';

    expect(file_exists($configPath))->toBeTrue();

    $config = require $configPath;

    expect($config)->toBeArray();
});
