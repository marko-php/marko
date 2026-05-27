<?php

declare(strict_types=1);

it('ships a known-drivers.php file listing both pubsub drivers', function (): void {
    $path = __DIR__ . '/../known-drivers.php';

    expect(file_exists($path))->toBeTrue();

    $drivers = require $path;

    expect($drivers)->toHaveKey('marko/pubsub-redis')
        ->and($drivers)->toHaveKey('marko/pubsub-pgsql');
});

it('lists marko/pubsub-redis first as the recommended driver', function (): void {
    $drivers = require __DIR__ . '/../known-drivers.php';

    $keys = array_keys($drivers);

    expect($keys[0])->toBe('marko/pubsub-redis');
});
