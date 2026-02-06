<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Repository;

use Marko\AdminAuth\Entity\Permission;
use Marko\AdminAuth\Entity\Role;
use Marko\Database\Repository\RepositoryInterface;

/**
 * Interface for Role entity repository.
 */
interface RoleRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a role by its slug.
     */
    public function findBySlug(
        string $slug,
    ): ?Role;

    /**
     * Get all permissions for a role.
     *
     * @return array<Permission>
     */
    public function getPermissionsForRole(
        int $roleId,
    ): array;

    /**
     * Sync permissions for a role, replacing all existing.
     *
     * @param array<int> $permissionIds
     */
    public function syncPermissions(
        int $roleId,
        array $permissionIds,
    ): void;

    /**
     * Check if a slug is unique within the roles table.
     *
     * @param string $slug The slug to check
     * @param int|null $excludeId Optional role ID to exclude (for updates)
     */
    public function isSlugUnique(
        string $slug,
        ?int $excludeId = null,
    ): bool;
}
