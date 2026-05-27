<?php

declare(strict_types=1);

describe('Known Drivers', function (): void {
    it('ships a known-drivers.php file listing both mail drivers', function (): void {
        $path = __DIR__ . '/../known-drivers.php';

        expect(file_exists($path))->toBeTrue();

        $drivers = require $path;

        expect($drivers)->toHaveKey('marko/mail-smtp')
            ->and($drivers)->toHaveKey('marko/mail-log');
    });

    it('lists marko/mail-smtp first as the recommended driver', function (): void {
        $drivers = (static fn (): array => require __DIR__ . '/../known-drivers.php')();
        $keys = array_keys($drivers);

        expect($keys[0])->toBe('marko/mail-smtp');
    });
});
