<?php

declare(strict_types=1);

it('ships a known-drivers.php file listing both media drivers', function (): void {
    $knownDriversPath = __DIR__ . '/../known-drivers.php';

    expect(file_exists($knownDriversPath))->toBeTrue()
        ->and(require $knownDriversPath)->toHaveKey('marko/media-gd')
        ->and(require $knownDriversPath)->toHaveKey('marko/media-imagick');
});

it('lists marko/media-gd first as the recommended driver', function (): void {
    $knownDriversPath = __DIR__ . '/../known-drivers.php';
    $drivers = require $knownDriversPath;
    $keys = array_keys($drivers);

    expect($keys[0])->toBe('marko/media-gd');
});
