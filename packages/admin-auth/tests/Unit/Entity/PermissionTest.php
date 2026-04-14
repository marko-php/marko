<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Entity;

use DateTimeImmutable;
use Marko\AdminAuth\Entity\Permission;
use Marko\AdminAuth\Entity\PermissionInterface;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use ReflectionClass;

it('creates Permission entity with id, key, label, group, createdAt', function (): void {
    $permission = new Permission();
    $permission->id = 1;
    $permission->key = 'posts.create';
    $permission->label = 'Create Posts';
    $permission->group = 'Posts';
    $permission->createdAt = '2024-01-01 00:00:00';

    expect($permission->getId())->toBe(1)
        ->and($permission->getKey())->toBe('posts.create')
        ->and($permission->getLabel())->toBe('Create Posts')
        ->and($permission->getGroup())->toBe('Posts')
        ->and($permission->getCreatedAt())->toBeInstanceOf(DateTimeImmutable::class);
});

it('extends the Entity base class', function (): void {
    $permission = new Permission();

    expect($permission)->toBeInstanceOf(Entity::class);
});

it('implements PermissionInterface', function (): void {
    $permission = new Permission();

    expect($permission)->toBeInstanceOf(PermissionInterface::class);
});

it('has Table attribute with permissions table name', function (): void {
    $reflection = new ReflectionClass(Permission::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $tableAttribute = $attributes[0]->newInstance();
    expect($tableAttribute->name)->toBe('permissions');
});

it('has id property with primaryKey and autoIncrement Column attributes', function (): void {
    $reflection = new ReflectionClass(Permission::class);

    expect($reflection->hasProperty('id'))->toBeTrue();

    $property = $reflection->getProperty('id');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->primaryKey)->toBeTrue()
        ->and($columnAttribute->autoIncrement)->toBeTrue();
});

it('has key property with Column attribute and unique constraint', function (): void {
    $reflection = new ReflectionClass(Permission::class);

    expect($reflection->hasProperty('key'))->toBeTrue();

    $property = $reflection->getProperty('key');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->unique)->toBeTrue()
        ->and($property->getType()->getName())->toBe('string');
});

it('has label property with Column attribute', function (): void {
    $reflection = new ReflectionClass(Permission::class);

    expect($reflection->hasProperty('label'))->toBeTrue();

    $property = $reflection->getProperty('label');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1)
        ->and($property->getType()->getName())->toBe('string');
});

it('has group property with Column attribute', function (): void {
    $reflection = new ReflectionClass(Permission::class);

    expect($reflection->hasProperty('group'))->toBeTrue();

    $property = $reflection->getProperty('group');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1)
        ->and($property->getType()->getName())->toBe('string');
});

it('has created_at timestamp', function (): void {
    $reflection = new ReflectionClass(Permission::class);

    expect($reflection->hasProperty('createdAt'))->toBeTrue();

    $property = $reflection->getProperty('createdAt');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBeNull();
});

it('uses nullable types for optional fields appropriately', function (): void {
    $reflection = new ReflectionClass(Permission::class);

    $idProperty = $reflection->getProperty('id');
    $createdAtProperty = $reflection->getProperty('createdAt');
    $keyProperty = $reflection->getProperty('key');
    $labelProperty = $reflection->getProperty('label');
    $groupProperty = $reflection->getProperty('group');

    expect($idProperty->getType()->allowsNull())->toBeTrue()
        ->and($createdAtProperty->getType()->allowsNull())->toBeTrue()
        ->and($keyProperty->getType()->allowsNull())->toBeFalse()
        ->and($labelProperty->getType()->allowsNull())->toBeFalse()
        ->and($groupProperty->getType()->allowsNull())->toBeFalse();
});

it('returns null for createdAt when not set', function (): void {
    $permission = new Permission();
    $permission->key = 'posts.create';
    $permission->label = 'Create Posts';
    $permission->group = 'Posts';

    expect($permission->getCreatedAt())->toBeNull();
});
