<?php

declare(strict_types=1);

use Marko\Core\Attributes\Observer;
use Marko\Database\Events\EntityCreated;
use Marko\Database\Events\EntityDeleted;
use Marko\Database\Events\EntityUpdated;
use Marko\PageCache\Entity\Observer\PurgeOnEntityCreated;
use Marko\PageCache\Entity\Observer\PurgeOnEntityDeleted;
use Marko\PageCache\Entity\Observer\PurgeOnEntityUpdated;

it('places each observer class under src/Observer/ so ObserverDiscovery picks them up', function (): void {
    $srcObserverDir = dirname(__DIR__, 3) . '/src/Observer';

    expect(is_dir($srcObserverDir))->toBeTrue()
        ->and(file_exists($srcObserverDir . '/PurgeOnEntityCreated.php'))->toBeTrue()
        ->and(file_exists($srcObserverDir . '/PurgeOnEntityUpdated.php'))->toBeTrue()
        ->and(file_exists($srcObserverDir . '/PurgeOnEntityDeleted.php'))->toBeTrue();
});

it('declares PurgeOnEntityCreated with #[Observer(event: EntityCreated::class)] via reflection', function (): void {
    $reflection = new ReflectionClass(PurgeOnEntityCreated::class);
    $attributes = $reflection->getAttributes(Observer::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->event)->toBe(EntityCreated::class);
});

it('declares PurgeOnEntityUpdated with #[Observer(event: EntityUpdated::class)] via reflection', function (): void {
    $reflection = new ReflectionClass(PurgeOnEntityUpdated::class);
    $attributes = $reflection->getAttributes(Observer::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->event)->toBe(EntityUpdated::class);
});

it('declares PurgeOnEntityDeleted with #[Observer(event: EntityDeleted::class)] via reflection', function (): void {
    $reflection = new ReflectionClass(PurgeOnEntityDeleted::class);
    $attributes = $reflection->getAttributes(Observer::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->event)->toBe(EntityDeleted::class);
});
