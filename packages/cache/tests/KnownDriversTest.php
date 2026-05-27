<?php

declare(strict_types=1);

it('ships a known-drivers.php file listing all three cache drivers', function (): void {
    $knownDriversPath = dirname(__DIR__) . '/known-drivers.php';

    expect(file_exists($knownDriversPath))->toBeTrue();

    $drivers = require $knownDriversPath;

    expect($drivers)->toBeArray()
        ->and($drivers)->toHaveKey('marko/cache-file')
        ->and($drivers)->toHaveKey('marko/cache-redis')
        ->and($drivers)->toHaveKey('marko/cache-array');
});

it('lists marko/cache-file first as the recommended driver', function (): void {
    $knownDriversPath = dirname(__DIR__) . '/known-drivers.php';
    $drivers = require $knownDriversPath;

    $keys = array_keys($drivers);

    expect($keys[0])->toBe('marko/cache-file');
});
