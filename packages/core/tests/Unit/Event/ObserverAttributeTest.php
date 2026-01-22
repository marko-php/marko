<?php

declare(strict_types=1);

use Marko\Core\Attributes\Observer;

// Test fixtures
#[Observer(event: TestEvent::class)]
class SimpleObserver {}

#[Observer(event: TestEvent::class, priority: 100)]
class PriorityObserver {}

class TestEvent {}

it('creates Observer attribute with event and optional priority parameters', function (): void {
    $reflection = new ReflectionClass(PriorityObserver::class);
    $attributes = $reflection->getAttributes(Observer::class);

    expect($attributes)->toHaveCount(1);

    $observer = $attributes[0]->newInstance();

    expect($observer->event)->toBe(TestEvent::class)
        ->and($observer->priority)->toBe(100);
});

it('defaults observer priority to 0 when not specified', function (): void {
    $reflection = new ReflectionClass(SimpleObserver::class);
    $attributes = $reflection->getAttributes(Observer::class);

    $observer = $attributes[0]->newInstance();

    expect($observer->priority)->toBe(0);
});

#[Observer(event: TestEvent::class, async: true)]
class AsyncObserver {}

it('accepts async parameter', function (): void {
    $reflection = new ReflectionClass(AsyncObserver::class);
    $attributes = $reflection->getAttributes(Observer::class);

    expect($attributes)->toHaveCount(1);

    $observer = $attributes[0]->newInstance();

    expect($observer->async)->toBeTrue();
});

it('defaults async to false', function (): void {
    $reflection = new ReflectionClass(SimpleObserver::class);
    $attributes = $reflection->getAttributes(Observer::class);

    $observer = $attributes[0]->newInstance();

    expect($observer->async)->toBeFalse();
});

it('stores async value', function (): void {
    $observer = new Observer(event: TestEvent::class, async: true);

    expect($observer->async)->toBeTrue();

    $syncObserver = new Observer(event: TestEvent::class, async: false);

    expect($syncObserver->async)->toBeFalse();
});
