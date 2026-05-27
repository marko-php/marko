<?php

declare(strict_types=1);

it('ships a known-drivers.php file listing only marko/page-cache-file', function (): void {
    $path = __DIR__ . '/../known-drivers.php';

    expect(file_exists($path))->toBeTrue();

    $drivers = require $path;

    expect($drivers)->toBeArray()
        ->and(array_keys($drivers))->toBe(['marko/page-cache-file']);
});

it('does not list marko/page-cache-entity (add-on, not driver)', function (): void {
    $path = __DIR__ . '/../known-drivers.php';
    $drivers = require $path;

    expect(array_key_exists('marko/page-cache-entity', $drivers))->toBeFalse();
});
