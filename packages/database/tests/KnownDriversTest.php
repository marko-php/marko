<?php

declare(strict_types=1);

describe('Known Drivers', function (): void {
    it('ships a known-drivers.php file in the database package', function (): void {
        $path = __DIR__ . '/../known-drivers.php';

        expect(file_exists($path))->toBeTrue();
    });

    it('lists marko/database-pgsql as the first entry (recommended default)', function (): void {
        $drivers = require __DIR__ . '/../known-drivers.php';
        $keys = array_keys($drivers);

        expect($keys[0])->toBe('marko/database-pgsql');
    });

    it('lists marko/database-mysql as the second entry', function (): void {
        $drivers = (static fn (): array => require __DIR__ . '/../known-drivers.php')();
        $keys = array_keys($drivers);

        expect($keys[1])->toBe('marko/database-mysql');
    });

    it('does not list marko/database-readwrite (add-on, not a driver)', function (): void {
        $drivers = (static fn (): array => require __DIR__ . '/../known-drivers.php')();

        expect(array_key_exists('marko/database-readwrite', $drivers))->toBeFalse();
    });

    it('returns a flat package-to-description associative array', function (): void {
        $drivers = (static fn (): array => require __DIR__ . '/../known-drivers.php')();

        expect(is_array($drivers))->toBeTrue();

        foreach ($drivers as $key => $value) {
            expect(is_string($key))->toBeTrue()
                ->and(is_string($value))->toBeTrue();
        }
    });

    it('uses declare strict_types', function (): void {
        $contents = file_get_contents(__DIR__ . '/../known-drivers.php');

        expect(str_contains($contents, 'declare(strict_types=1)'))->toBeTrue();
    });
});
