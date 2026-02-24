<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Sse\Exceptions\SseException;

it('has a valid composer.json with name marko/sse', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->not->toBeNull()
        ->and($composer['name'])->toBe('marko/sse');
});

it('has composer.json with type marko-module and extra.marko.module true', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['type'])->toBe('marko-module')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

it('has PSR-4 autoloading configured for Marko\Sse namespace', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\Sse\\')
        ->and($composer['autoload']['psr-4']['Marko\\Sse\\'])->toBe('src/');
});

it('requires marko/core and marko/routing as dependencies', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require'])->toHaveKey('marko/routing');
});

it('has a module.php that returns array with bindings key', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings');
});

it('has SseException extending MarkoException', function (): void {
    $exceptionPath = dirname(__DIR__) . '/src/Exceptions/SseException.php';

    expect(file_exists($exceptionPath))->toBeTrue();

    expect(SseException::class)->toExtend(MarkoException::class);
});
