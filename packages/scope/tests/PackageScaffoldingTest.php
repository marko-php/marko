<?php

declare(strict_types=1);

it('requires PHP ^8.5, marko/core, marko/config, and marko/database in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('php')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require'])->toHaveKey('marko/config')
        ->and($composer['require'])->toHaveKey('marko/database');
});

it('has no version field in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->not->toHaveKey('version');
});

it('has a module.php returning array with bindings', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toBeArray();
});

it('autoloads PSR-4 test namespace Marko\Scope\Tests\ from packages/scope/tests/', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\Scope\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Marko\\Scope\\Tests\\'])->toBe('tests/');
});

it('autoloads PSR-4 namespace Marko\Scope\ from packages/scope/src/', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\Scope\\')
        ->and($composer['autoload']['psr-4']['Marko\\Scope\\'])->toBe('src/');
});

it('has a valid composer.json with name marko/scope and extra.marko.module set to true', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->not->toBeNull()
        ->and($composer['name'])->toBe('marko/scope')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});
