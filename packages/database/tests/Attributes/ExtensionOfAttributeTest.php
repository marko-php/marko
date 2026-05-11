<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Attributes;

use Attribute;
use Marko\Database\Attributes\ExtensionOf;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityExtension;
use ReflectionClass;

class UserEntityForExtension extends Entity {}

#[ExtensionOf(UserEntityForExtension::class)]
class UserEntityExtension extends EntityExtension {}

it('stores the entity class in the attribute', function (): void {
    $attribute = new ExtensionOf(UserEntityForExtension::class);

    expect($attribute->entityClass)->toBe(UserEntityForExtension::class);
});

it('targets class-level application only', function (): void {
    $reflection = new ReflectionClass(ExtensionOf::class);
    $attributes = $reflection->getAttributes(Attribute::class);
    $attributeMeta = $attributes[0]->newInstance();

    expect($attributeMeta->flags)->toBe(Attribute::TARGET_CLASS);
});

it('has an ExtensionOf attribute that accepts an entity class string', function (): void {
    $reflection = new ReflectionClass(UserEntityExtension::class);
    $attributes = $reflection->getAttributes(ExtensionOf::class);
    $extensionOfAttribute = $attributes[0]->newInstance();

    expect($attributes)->toHaveCount(1)
        ->and($extensionOfAttribute)->toBeInstanceOf(ExtensionOf::class)
        ->and($extensionOfAttribute->entityClass)->toBe(UserEntityForExtension::class);
});
