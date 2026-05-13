<?php

declare(strict_types=1);

use Marko\Scope\Attributes\Scoped;

it('defaults axes to empty array meaning single-axis fallback to registry default', function (): void {
    $scoped = new Scoped();

    expect($scoped->axes)->toBe([]);
});

it('preserves axes order as declared', function (): void {
    $scoped = new Scoped(axes: ['website', 'store', 'customer_group']);

    expect($scoped->axes)->toBe(['website', 'store', 'customer_group'])
        ->and(array_keys($scoped->axes))->toBe([0, 1, 2]);
});

it('accepts an axes array in the constructor', function (): void {
    $scoped = new Scoped(axes: ['store', 'website']);

    expect($scoped->axes)->toBe(['store', 'website']);
});

it('is reflectable on a property and round-trips via getAttributes', function (): void {
    $entity = new class ()
    {
        #[Scoped(axes: ['store', 'website'])]
        public string $value = 'test';
    };

    $reflection = new ReflectionObject($entity);
    $property = $reflection->getProperty('value');
    $attributes = $property->getAttributes(Scoped::class);
    $scoped = $attributes[0]->newInstance();

    expect($attributes)->toHaveCount(1)
        ->and($scoped)->toBeInstanceOf(Scoped::class)
        ->and($scoped->axes)->toBe(['store', 'website']);
});

it('is a readonly class targeting properties only', function (): void {
    $reflection = new ReflectionClass(Scoped::class);
    $attributes = $reflection->getAttributes(Attribute::class);
    $attribute = $attributes[0]->newInstance();

    expect($reflection->isReadOnly())->toBeTrue()
        ->and($attributes)->toHaveCount(1)
        ->and($attribute->flags)->toBe(Attribute::TARGET_PROPERTY);
});
