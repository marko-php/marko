<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Entity;

use Marko\AdminAuth\Entity\RolePermission;
use Marko\AdminAuth\Entity\RolePermissionInterface;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use ReflectionClass;

it('creates RolePermission junction entity with roleId and permissionId', function (): void {
    $rolePermission = new RolePermission();
    $rolePermission->roleId = 1;
    $rolePermission->permissionId = 2;

    expect($rolePermission->roleId)->toBe(1)
        ->and($rolePermission->permissionId)->toBe(2)
        ->and($rolePermission)->toBeInstanceOf(Entity::class)
        ->and($rolePermission)->toBeInstanceOf(RolePermissionInterface::class);

    $reflection = new ReflectionClass(RolePermission::class);
    $tableAttributes = $reflection->getAttributes(Table::class);

    expect($tableAttributes)->toHaveCount(1);

    $tableAttribute = $tableAttributes[0]->newInstance();
    expect($tableAttribute->name)->toBe('role_permissions');
});

it('enforces foreign key to roles table', function (): void {
    $reflection = new ReflectionClass(RolePermission::class);
    $property = $reflection->getProperty('roleId');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('role_id')
        ->and($columnAttribute->references)->toBe('roles.id')
        ->and($columnAttribute->onDelete)->toBe('CASCADE');
});

it('enforces foreign key to permissions table', function (): void {
    $reflection = new ReflectionClass(RolePermission::class);
    $property = $reflection->getProperty('permissionId');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('permission_id')
        ->and($columnAttribute->references)->toBe('permissions.id')
        ->and($columnAttribute->onDelete)->toBe('CASCADE');
});

it('prevents duplicate role permission combinations', function (): void {
    $reflection = new ReflectionClass(RolePermission::class);
    $indexAttributes = $reflection->getAttributes(Index::class);

    expect($indexAttributes)->not->toBeEmpty();

    $foundUniqueIndex = false;

    foreach ($indexAttributes as $attribute) {
        $index = $attribute->newInstance();
        if ($index->unique && in_array('role_id', $index->columns, true) && in_array(
            'permission_id',
            $index->columns,
            true,
        )) {
            $foundUniqueIndex = true;
            break;
        }
    }

    expect($foundUniqueIndex)->toBeTrue();
});

it('exposes getter methods via RolePermissionInterface', function (): void {
    $rolePermission = new RolePermission();
    $rolePermission->roleId = 5;
    $rolePermission->permissionId = 10;

    expect($rolePermission->getRoleId())->toBe(5)
        ->and($rolePermission->getPermissionId())->toBe(10);
});
