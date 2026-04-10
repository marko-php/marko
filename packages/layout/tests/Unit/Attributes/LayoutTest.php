<?php

declare(strict_types=1);

use Marko\Layout\Attributes\Layout;

it('can be instantiated with a component class string', function (): void {
    $attribute = new Layout(SomeComponent::class);

    expect($attribute)->toBeInstanceOf(Layout::class);
});

it('stores the component class as a public property', function (): void {
    $attribute = new Layout(SomeComponent::class);

    expect($attribute->component)->toBe(SomeComponent::class);
});

it('targets both classes and methods', function (): void {
    $reflection = new ReflectionClass(Layout::class);
    $attributes = $reflection->getAttributes(Attribute::class);

    expect($attributes)->toHaveCount(1);

    $attribute = $attributes[0]->newInstance();
    $flags = $attribute->flags;

    expect($flags & Attribute::TARGET_CLASS)->toBe(Attribute::TARGET_CLASS)
        ->and($flags & Attribute::TARGET_METHOD)->toBe(Attribute::TARGET_METHOD);
});

// Dummy component class for testing
class SomeComponent {}
