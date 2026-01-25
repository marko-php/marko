<?php

declare(strict_types=1);
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\DefaultFilesystem;

it('has a valid composer.json with correct package name marko/filesystem', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue()
        ->and(json_decode(file_get_contents($composerPath), true))->toBeArray()
        ->and(json_decode(file_get_contents($composerPath), true)['name'])->toBe('marko/filesystem');
});

it('has correct description in composer.json', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['description'])->toBe('Filesystem interfaces and infrastructure for Marko Framework');
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

    expect($composer['require'])->toHaveKey('php')
        ->and($composer['require']['php'])->toBe('^8.5');
});

it('has PSR-4 autoloading configured for Marko\\Filesystem namespace', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('autoload')
        ->and($composer['autoload'])->toHaveKey('psr-4')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\Filesystem\\')
        ->and($composer['autoload']['psr-4']['Marko\\Filesystem\\'])->toBe('src/');
});

it('has src directory for source code', function () {
    $srcPath = dirname(__DIR__) . '/src';

    expect(is_dir($srcPath))->toBeTrue();
});

it('has tests directory for tests', function () {
    $testsPath = dirname(__DIR__) . '/tests';

    expect(is_dir($testsPath))->toBeTrue();
});

it('has config directory for default configuration', function () {
    $configPath = dirname(__DIR__) . '/config';

    expect(is_dir($configPath))->toBeTrue();
});

it('has default filesystem.php config file', function () {
    $configPath = dirname(__DIR__) . '/config/filesystem.php';

    expect(file_exists($configPath))->toBeTrue();

    $config = require $configPath;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('default')
        ->and($config)->toHaveKey('disks');
});

it('has module.php with enabled set to true', function () {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $config = require $modulePath;

    expect($config)->toBeArray();
});

it('binds FilesystemInterface to DefaultFilesystem', function () {
    $modulePath = dirname(__DIR__) . '/module.php';
    $config = require $modulePath;

    expect($config)->toHaveKey('bindings')
        ->and($config['bindings'])->toHaveKey(FilesystemInterface::class)
        ->and($config['bindings'][FilesystemInterface::class])
            ->toBe(DefaultFilesystem::class);
});
