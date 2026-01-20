<?php

declare(strict_types=1);

use Marko\Routing\Attributes\DisableRoute;

it('has no parameters', function () {
    $reflection = new ReflectionClass(DisableRoute::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->toBeNull();
});

it('targets methods only', function () {
    $reflection = new ReflectionClass(DisableRoute::class);
    $attributes = $reflection->getAttributes(Attribute::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->flags)->toBe(Attribute::TARGET_METHOD);
});

it('is readonly', function () {
    $reflection = new ReflectionClass(DisableRoute::class);

    expect($reflection->isReadOnly())->toBeTrue();
});

it('can be instantiated without arguments', function () {
    $attribute = new DisableRoute();

    expect($attribute)->toBeInstanceOf(DisableRoute::class);
});
