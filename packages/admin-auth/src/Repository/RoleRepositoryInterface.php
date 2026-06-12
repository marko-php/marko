<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Repository;

use Marko\AdminAuth\Entity\Permission;
use Marko\AdminAuth\Entity\Role;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Repository\RepositoryInterface;
use Throwable;

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
     * @throws EntityException
     */
    public function getPermissionsForRole(
        int $roleId,
    ): array;

    /**
     * Get the deduplicated permission set across all given role ids in a single query.
     * Returns an empty array without querying when $roleIds is empty.
     *
     * @param array<int> $roleIds
     * @return array<Permission>
     * @throws EntityException
     */
    public function getPermissionsForRoles(
        array $roleIds,
    ): array;

    /**
     * Sync permissions for a role, replacing all existing.
     *
     * @param array<int> $permissionIds
     * @throws Throwable
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
