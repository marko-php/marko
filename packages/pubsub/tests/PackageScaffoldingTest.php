<?php

declare(strict_types=1);

it('has PackageScaffoldingTest or equivalent in each package', function (): void {
    $packagesDir = dirname(__DIR__, 2);

    expect(file_exists($packagesDir . '/pubsub/tests/PackageScaffoldingTest.php'))->toBeTrue()
        ->and(file_exists($packagesDir . '/amphp/tests/PackageScaffoldingTest.php'))->toBeTrue()
        ->and(file_exists($packagesDir . '/pubsub-redis/tests/PackageScaffoldingTest.php'))->toBeTrue()
        ->and(file_exists($packagesDir . '/pubsub-pgsql/tests/PackageScaffoldingTest.php'))->toBeTrue();
});

it('has Pest.php config file in each package test directory', function (): void {
    $packagesDir = dirname(__DIR__, 2);

    expect(file_exists($packagesDir . '/pubsub/tests/Pest.php'))->toBeTrue()
        ->and(file_exists($packagesDir . '/amphp/tests/Pest.php'))->toBeTrue()
        ->and(file_exists($packagesDir . '/pubsub-redis/tests/Pest.php'))->toBeTrue()
        ->and(file_exists($packagesDir . '/pubsub-pgsql/tests/Pest.php'))->toBeTrue();
});

it('has valid module.php for marko/pubsub with empty bindings', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toBeArray()
        ->and($module['bindings'])->toBeEmpty();
});

it('creates README.md for marko/pubsub with all required sections', function (): void {
    $readme = file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)
        ->toContain('## Overview')
        ->and($readme)->toContain('## Installation')
        ->and($readme)->toContain('## Usage')
        ->and($readme)->toContain('## API Reference');
});

it('has valid composer.json with name marko/pubsub and required fields', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->not->toBeNull()
        ->and($composer['name'])->toBe('marko/pubsub')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer['license'])->toBe('MIT')
        ->and($composer['require'])->toHaveKey('php')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require'])->toHaveKey('marko/core')
        ->and($composer['extra']['marko']['module'])->toBeTrue()
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\PubSub\\')
        ->and($composer['autoload']['psr-4']['Marko\\PubSub\\'])->toBe('src/')
        ->and($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\PubSub\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Marko\\PubSub\\Tests\\'])->toBe('tests/');
});
