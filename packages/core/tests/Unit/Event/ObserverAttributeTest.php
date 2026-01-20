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
