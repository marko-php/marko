<?php

declare(strict_types=1);

$composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true);

it('has a valid composer.json named marko/clock', function () use ($composer): void {
    expect($composer)->toBeArray()
        ->and($composer['name'])->toBe('marko/clock')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer['license'])->toBe('MIT');
});

it('has no hardcoded version in composer.json', function () use ($composer): void {
    expect($composer)->not->toHaveKey('version');
});

it('requires php 8.5 and psr/clock', function () use ($composer): void {
    expect($composer['require'])->toBe([
        'php' => '^8.5',
        'psr/clock' => '^1.0',
    ]);
});

it('has PSR-4 autoloading for Marko\Clock', function () use ($composer): void {
    expect($composer['autoload']['psr-4']['Marko\\Clock\\'])->toBe('src/')
        ->and($composer['autoload-dev']['psr-4']['Marko\\Clock\\Tests\\'])->toBe('tests/');
});

it('is marked as a marko module', function () use ($composer): void {
    expect($composer['extra']['marko']['module'])->toBeTrue();
});
