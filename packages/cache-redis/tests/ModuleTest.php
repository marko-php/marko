<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Redis\Driver\RedisCacheDriver;

test('it binds CacheInterface to RedisCacheDriver', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module['bindings'])->toHaveKey(CacheInterface::class)
        ->and($module['bindings'][CacheInterface::class])->toBe(RedisCacheDriver::class);
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

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('autoload')
        ->and($composer['autoload'])->toHaveKey('psr-4')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\Cache\\Redis\\')
        ->and($composer['autoload']['psr-4']['Marko\\Cache\\Redis\\'])->toBe('src/');
});
