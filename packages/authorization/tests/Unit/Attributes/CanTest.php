<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Unit\Attributes;

use Attribute;
use Marko\Authorization\Attributes\Can;
use ReflectionClass;

it('creates Can attribute with ability name', function (): void {
    $can = new Can(ability: 'view');

    expect($can->ability)->toBe('view')
        ->and($can->entityClass)->toBeNull();
});

it('creates Can attribute with ability and entity class', function (): void {
    $can = new Can(ability: 'update', entityClass: 'App\\Entity\\Post');

    expect($can->ability)->toBe('update')
        ->and($can->entityClass)->toBe('App\\Entity\\Post');
});

it('targets methods only', function (): void {
    $reflection = new ReflectionClass(Can::class);
    $attributes = $reflection->getAttributes(Attribute::class);

    expect($attributes)->toHaveCount(1);

    $attributeInstance = $attributes[0]->newInstance();
    expect($attributeInstance->flags)->toBe(Attribute::TARGET_METHOD);
});
