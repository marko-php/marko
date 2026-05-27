<?php

declare(strict_types=1);

test('it ships a known-drivers.php file listing all three inertia drivers', function (): void {
    $knownDriversPath = __DIR__ . '/../known-drivers.php';

    expect(file_exists($knownDriversPath))->toBeTrue();

    $drivers = require $knownDriversPath;

    expect($drivers)->toHaveKey('marko/inertia-react')
        ->and($drivers)->toHaveKey('marko/inertia-svelte')
        ->and($drivers)->toHaveKey('marko/inertia-vue');
});

test('it lists marko/inertia-react first as the recommended driver', function (): void {
    $drivers = require __DIR__ . '/../known-drivers.php';
    $keys = array_keys($drivers);

    expect($keys[0])->toBe('marko/inertia-react');
});
