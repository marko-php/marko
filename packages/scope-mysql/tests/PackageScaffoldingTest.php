<?php

declare(strict_types=1);

it('has a valid composer.json with name marko/scope-mysql and extra.marko.module true', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->not->toBeNull()
        ->and($composer['name'])->toBe('marko/scope-mysql')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

it('requires marko/scope and marko/database-mysql in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('marko/scope')
        ->and($composer['require'])->toHaveKey('marko/database-mysql');
});

it('autoloads PSR-4 namespace Marko\Scope\MySql\ from packages/scope-mysql/src/', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\Scope\\MySql\\')
        ->and($composer['autoload']['psr-4']['Marko\\Scope\\MySql\\'])->toBe('src/');
});

it('autoloads tests namespace Marko\Scope\MySql\Tests\ from tests/', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\Scope\\MySql\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Marko\\Scope\\MySql\\Tests\\'])->toBe('tests/');
});

it('has a module.php returning an array with bindings key', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toBeArray();
});

it('has no version field in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->not->toHaveKey('version');
});
