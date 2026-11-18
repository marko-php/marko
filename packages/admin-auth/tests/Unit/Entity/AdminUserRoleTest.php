<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Entity;

use Marko\AdminAuth\Entity\AdminUserRole;
use Marko\AdminAuth\Entity\AdminUserRoleInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;

/**
 * The admin_user_roles table AdminUserRole declares, as db:migrate builds it.
 */
function adminUserRoleTable(): Table
{
    return new SchemaBuilder()->build(new EntityMetadataFactory()->parse(AdminUserRole::class));
}

it('maps AdminUserRole to the admin_user_roles table with an auto-increment id', function (): void {
    $table = adminUserRoleTable();
    $id = array_find($table->columns, fn (Column $column): bool => $column->name === 'id');

    expect($table->name)->toBe('admin_user_roles')
        ->and(array_map(fn (Column $column): string => $column->name, $table->columns))
        ->toBe(['id', 'user_id', 'role_id'])
        ->and($id?->primaryKey)->toBeTrue()
        ->and($id?->autoIncrement)->toBeTrue();
});

it('references admin_users and roles with cascading deletes', function (): void {
    $references = array_map(
        fn (ForeignKey $foreignKey): array => [
            $foreignKey->columns,
            $foreignKey->referencedTable,
            $foreignKey->referencedColumns,
            $foreignKey->onDelete,
        ],
        adminUserRoleTable()->foreignKeys,
    );

    expect($references)->toEqualCanonicalizing([
        [['user_id'], 'admin_users', ['id'], 'CASCADE'],
        [['role_id'], 'roles', ['id'], 'CASCADE'],
    ]);
});

it('declares a unique index on user_id and role_id', function (): void {
    $index = array_find(
        adminUserRoleTable()->indexes,
        fn (Index $index): bool => $index->name === 'admin_user_roles_user_id_role_id_unique',
    );

    expect($index?->columns)->toBe(['user_id', 'role_id'])
        ->and($index?->type)->toBe(IndexType::Unique);
});

it('indexes role_id for role lookups and cascading role deletes', function (): void {
    $index = array_find(
        adminUserRoleTable()->indexes,
        fn (Index $index): bool => $index->name === 'admin_user_roles_role_id_index',
    );

    expect($index?->columns)->toBe(['role_id'])
        ->and($index?->type)->toBe(IndexType::Btree);
});

it('exposes the user and role ids through AdminUserRoleInterface', function (): void {
    $userRole = new AdminUserRole();
    $userRole->userId = 3;
    $userRole->roleId = 7;

    expect($userRole)->toBeInstanceOf(Entity::class)
        ->and($userRole)->toBeInstanceOf(AdminUserRoleInterface::class)
        ->and($userRole->getUserId())->toBe(3)
        ->and($userRole->getRoleId())->toBe(7);
});
