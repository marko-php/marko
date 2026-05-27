<?php

declare(strict_types=1);

describe('Known Drivers', function (): void {
    it('ships a known-drivers.php file listing both session drivers', function (): void {
        $path = __DIR__ . '/../known-drivers.php';

        expect(file_exists($path))->toBeTrue();
    });

    it('lists marko/session-file first as the recommended driver', function (): void {
        $drivers = (static fn (): array => require __DIR__ . '/../known-drivers.php')();
        $keys = array_keys($drivers);

        expect($keys[0])->toBe('marko/session-file');
    });
});
