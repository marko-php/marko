<?php

declare(strict_types=1);

describe('Known Drivers', function (): void {
    it('ships a known-drivers.php file listing marko/authentication-token', function (): void {
        $path = __DIR__ . '/../known-drivers.php';

        expect(file_exists($path))->toBeTrue();

        $drivers = require $path;

        expect(array_key_exists('marko/authentication-token', $drivers))->toBeTrue();
    });
});
