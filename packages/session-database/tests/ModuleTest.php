<?php

declare(strict_types=1);

use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Database\Handler\DatabaseSessionHandler;

test('it binds SessionHandlerInterface to DatabaseSessionHandler', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module['bindings'])->toHaveKey(SessionHandlerInterface::class)
        ->and($module['bindings'][SessionHandlerInterface::class])->toBe(DatabaseSessionHandler::class);
});

test('it returns valid module configuration array', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toBeArray();
});

test('it has marko module flag in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('extra')
        ->and($composer['extra'])->toHaveKey('marko')
        ->and($composer['extra']['marko'])->toHaveKey('module')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

test('it has correct PSR-4 autoloading namespace', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('autoload')
        ->and($composer['autoload'])->toHaveKey('psr-4')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\Session\\Database\\')
        ->and($composer['autoload']['psr-4']['Marko\\Session\\Database\\'])->toBe('src/');
});
