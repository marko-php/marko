<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Entity;

use DateTimeImmutable;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Entity\RoleInterface;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use ReflectionClass;

it('creates Role entity with id, name, slug, description, isSuperAdmin, createdAt, updatedAt', function (): void {
    $role = new Role();
    $role->id = 1;
    $role->name = 'Administrator';
    $role->slug = 'administrator';
    $role->description = 'Full access administrator';
    $role->isSuperAdmin = '1';
    $role->createdAt = '2024-01-01 00:00:00';
    $role->updatedAt = '2024-01-02 00:00:00';

    expect($role->getId())->toBe(1)
        ->and($role->getName())->toBe('Administrator')
        ->and($role->getSlug())->toBe('administrator')
        ->and($role->getDescription())->toBe('Full access administrator')
        ->and($role->isSuperAdmin())->toBeTrue()
        ->and($role->getCreatedAt())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($role->getUpdatedAt())->toBeInstanceOf(DateTimeImmutable::class);
});

it('extends the Entity base class', function (): void {
    $role = new Role();

    expect($role)->toBeInstanceOf(Entity::class);
});

it('implements RoleInterface', function (): void {
    $role = new Role();

    expect($role)->toBeInstanceOf(RoleInterface::class);
});

it('has Table attribute with roles table name', function (): void {
    $reflection = new ReflectionClass(Role::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $tableAttribute = $attributes[0]->newInstance();
    expect($tableAttribute->name)->toBe('roles');
});

it('has id property with primaryKey and autoIncrement Column attributes', function (): void {
    $reflection = new ReflectionClass(Role::class);

    expect($reflection->hasProperty('id'))->toBeTrue();

    $property = $reflection->getProperty('id');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->primaryKey)->toBeTrue()
        ->and($columnAttribute->autoIncrement)->toBeTrue();
});

it('has name property with Column attribute', function (): void {
    $reflection = new ReflectionClass(Role::class);

    expect($reflection->hasProperty('name'))->toBeTrue();

    $property = $reflection->getProperty('name');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1)
        ->and($property->getType()->getName())->toBe('string');
});

it('has slug property with Column attribute and unique constraint', function (): void {
    $reflection = new ReflectionClass(Role::class);

    expect($reflection->hasProperty('slug'))->toBeTrue();

    $property = $reflection->getProperty('slug');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->unique)->toBeTrue()
        ->and($property->getType()->getName())->toBe('string');
});

it('has description property with TEXT type and nullable', function (): void {
    $reflection = new ReflectionClass(Role::class);

    expect($reflection->hasProperty('description'))->toBeTrue();

    $property = $reflection->getProperty('description');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->type)->toBe('TEXT')
        ->and($property->getType()->allowsNull())->toBeTrue();
});

it('has isSuperAdmin property with Column attribute and default 0', function (): void {
    $reflection = new ReflectionClass(Role::class);

    expect($reflection->hasProperty('isSuperAdmin'))->toBeTrue();

    $property = $reflection->getProperty('isSuperAdmin');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBeNull()
        ->and($columnAttribute->default)->toBe('0');
});

it('marks super admin role via isSuperAdmin boolean flag', function (): void {
    $role = new Role();
    $role->name = 'Super Admin';
    $role->slug = 'super-admin';
    $role->isSuperAdmin = '1';

    expect($role->isSuperAdmin())->toBeTrue();

    $role->isSuperAdmin = '0';
    expect($role->isSuperAdmin())->toBeFalse();
});

it('has created_at timestamp', function (): void {
    $reflection = new ReflectionClass(Role::class);

    expect($reflection->hasProperty('createdAt'))->toBeTrue();

    $property = $reflection->getProperty('createdAt');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBeNull();
});

it('has updated_at timestamp', function (): void {
    $reflection = new ReflectionClass(Role::class);

    expect($reflection->hasProperty('updatedAt'))->toBeTrue();

    $property = $reflection->getProperty('updatedAt');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBeNull();
});

it('uses nullable types for optional fields appropriately', function (): void {
    $reflection = new ReflectionClass(Role::class);

    $idProperty = $reflection->getProperty('id');
    $createdAtProperty = $reflection->getProperty('createdAt');
    $updatedAtProperty = $reflection->getProperty('updatedAt');
    $descriptionProperty = $reflection->getProperty('description');
    $nameProperty = $reflection->getProperty('name');
    $slugProperty = $reflection->getProperty('slug');

    expect($idProperty->getType()->allowsNull())->toBeTrue()
        ->and($createdAtProperty->getType()->allowsNull())->toBeTrue()
        ->and($updatedAtProperty->getType()->allowsNull())->toBeTrue()
        ->and($descriptionProperty->getType()->allowsNull())->toBeTrue()
        ->and($nameProperty->getType()->allowsNull())->toBeFalse()
        ->and($slugProperty->getType()->allowsNull())->toBeFalse();
});

it('returns null for timestamps when not set', function (): void {
    $role = new Role();
    $role->name = 'Editor';
    $role->slug = 'editor';

    expect($role->getCreatedAt())->toBeNull()
        ->and($role->getUpdatedAt())->toBeNull();
});

it('returns null for description when not set', function (): void {
    $role = new Role();
    $role->name = 'Editor';
    $role->slug = 'editor';

    expect($role->getDescription())->toBeNull();
});

it('defaults isSuperAdmin to 0', function (): void {
    $role = new Role();
    $role->name = 'Editor';
    $role->slug = 'editor';

    expect($role->isSuperAdmin())->toBeFalse();
});
