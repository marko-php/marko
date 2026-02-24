<?php

declare(strict_types=1);

it('creates valid package scaffolding with composer.json, module.php, and config', function () {
    $packageRoot = dirname(__DIR__);

    expect(file_exists($packageRoot . '/composer.json'))->toBeTrue()
        ->and(file_exists($packageRoot . '/module.php'))->toBeTrue()
        ->and(is_dir($packageRoot . '/config'))->toBeTrue();
});

it('has a valid composer.json with correct package name marko/api', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['name'])->toBe('marko/api');
});

it('has correct description in composer.json', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['description'])->toBe('API resource interfaces and value objects for Marko Framework');
});

it('has type marko-module in composer.json', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['type'])->toBe('marko-module');
});

it('has MIT license in composer.json', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['license'])->toBe('MIT');
});

it('requires PHP 8.5 or higher', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require']['php'])->toBe('^8.5');
});

it('requires marko/core, marko/routing, and marko/pagination', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require'])->toHaveKey('marko/routing')
        ->and($composer['require'])->toHaveKey('marko/pagination');
});

it('has PSR-4 autoloading configured for Marko\\Api namespace', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\Api\\')
        ->and($composer['autoload']['psr-4']['Marko\\Api\\'])->toBe('src/');
});

it('has an api.php config file', function () {
    $configPath = dirname(__DIR__) . '/config/api.php';
    $config = require $configPath;

    expect(file_exists($configPath))->toBeTrue()
        ->and($config)->toBeArray();
});

it('has module.php with bindings array', function () {
    $modulePath = dirname(__DIR__) . '/module.php';
    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings');
});
