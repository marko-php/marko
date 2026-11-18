<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Entity;

use Marko\AdminAuth\Entity\AdminUserRole;
use Marko\AdminAuth\Entity\Permission;
use Marko\AdminAuth\Entity\RememberToken;
use Marko\AdminAuth\Entity\RolePermission;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Schema\Index;

/**
 * @param class-string $entityClass
 *
 * @return array<int, string>
 */
function adminAuthIndexNames(
    string $entityClass,
): array {
    $table = new SchemaBuilder()->build(new EntityMetadataFactory()->parse($entityClass));

    return array_map(fn (Index $index): string => $index->name, $table->indexes);
}

it('derives the admin auth index names from their tables and columns', function (): void {
    expect(adminAuthIndexNames(AdminUserRole::class))
        ->toEqualCanonicalizing(['admin_user_roles_user_id_role_id_unique', 'admin_user_roles_role_id_index'])
        ->and(adminAuthIndexNames(Permission::class))->toBe(['permissions_group_index'])
        ->and(adminAuthIndexNames(RememberToken::class))->toBe(['remember_tokens_expires_at_index'])
        ->and(adminAuthIndexNames(RolePermission::class))
        ->toEqualCanonicalizing(
            ['role_permissions_role_id_permission_id_unique', 'role_permissions_permission_id_index'],
        );
});
