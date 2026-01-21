<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Attributes;

use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use ReflectionClass;

#[Table('users')]
class UserEntity extends Entity {}

it('creates #[Table] attribute with table name parameter', function (): void {
    $reflection = new ReflectionClass(UserEntity::class);
    $attributes = $reflection->getAttributes(Table::class);
    $tableAttribute = $attributes[0]->newInstance();

    expect($attributes)->toHaveCount(1)
        ->and($tableAttribute)->toBeInstanceOf(Table::class)
        ->and($tableAttribute->name)->toBe('users');
});
