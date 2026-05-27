<?php

declare(strict_types=1);

$knownDriversPath = __DIR__ . '/../known-drivers.php';

it('ships a known-drivers.php file listing both view drivers', function () use ($knownDriversPath): void {
    expect(file_exists($knownDriversPath))->toBeTrue();

    $drivers = require $knownDriversPath;

    expect($drivers)->toBeArray()
        ->and($drivers)->toHaveKey('marko/view-twig')
        ->and($drivers)->toHaveKey('marko/view-latte');
});

it('lists marko/view-twig first as the recommended driver', function () use ($knownDriversPath): void {
    $drivers = require $knownDriversPath;
    $keys = array_keys($drivers);

    expect($keys[0])->toBe('marko/view-twig');
});
