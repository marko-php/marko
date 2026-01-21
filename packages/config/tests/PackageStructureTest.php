<?php

declare(strict_types=1);

it('has a valid composer.json with correct package name marko/config', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue()
        ->and(json_decode(file_get_contents($composerPath), true))->toBeArray()
        ->and(json_decode(file_get_contents($composerPath), true)['name'])->toBe('marko/config');
});

it('has correct description in composer.json', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['description'])->toBe('Configuration loading and merging for Marko Framework');
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

it('has PSR-4 autoloading configured for Marko\Config namespace', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('autoload')
        ->and($composer['autoload'])->toHaveKey('psr-4')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\Config\\')
        ->and($composer['autoload']['psr-4']['Marko\\Config\\'])->toBe('src/');
});

it('has dev autoloading configured for Marko\Config\Tests namespace', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('autoload-dev')
        ->and($composer['autoload-dev'])->toHaveKey('psr-4')
        ->and($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\Config\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Marko\\Config\\Tests\\'])->toBe('tests/');
});

it('requires marko/core as a dependency', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('require')
        ->and($composer['require'])->toHaveKey('marko/core');
});

it('has pestphp/pest as dev dependency', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('require-dev')
        ->and($composer['require-dev'])->toHaveKey('pestphp/pest');
});

it('has module.php with enabled set to true', function () {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $config = require $modulePath;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('enabled')
        ->and($config['enabled'])->toBeTrue();
});

it('has module.php with empty bindings array', function () {
    $modulePath = dirname(__DIR__) . '/module.php';
    $config = require $modulePath;

    expect($config)->toHaveKey('bindings')
        ->and($config['bindings'])->toBeArray()
        ->and($config['bindings'])->toBeEmpty();
});

it('has src directory for source code', function () {
    $srcPath = dirname(__DIR__) . '/src';

    expect(is_dir($srcPath))->toBeTrue();
});

it('has tests directory for tests', function () {
    $testsPath = dirname(__DIR__) . '/tests';

    expect(is_dir($testsPath))->toBeTrue();
});

it('has tests/Unit directory for unit tests', function () {
    $unitPath = dirname(__DIR__) . '/tests/Unit';

    expect(is_dir($unitPath))->toBeTrue();
});

it('has tests/Feature directory for feature tests', function () {
    $featurePath = dirname(__DIR__) . '/tests/Feature';

    expect(is_dir($featurePath))->toBeTrue();
});
