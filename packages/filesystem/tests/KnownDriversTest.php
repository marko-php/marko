<?php

declare(strict_types=1);

describe('Known Drivers', function (): void {
    it('ships a known-drivers.php file listing both filesystem drivers', function (): void {
        $path = __DIR__ . '/../known-drivers.php';

        expect(file_exists($path))->toBeTrue();

        $drivers = require $path;

        expect(array_key_exists('marko/filesystem-local', $drivers))->toBeTrue()
            ->and(array_key_exists('marko/filesystem-s3', $drivers))->toBeTrue();
    });

    it('lists marko/filesystem-local first as the recommended driver', function (): void {
        $drivers = (static fn (): array => require __DIR__ . '/../known-drivers.php')();
        $keys = array_keys($drivers);

        expect($keys[0])->toBe('marko/filesystem-local');
    });
});
