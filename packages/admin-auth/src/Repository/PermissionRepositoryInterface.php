<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Repository;

use Marko\AdminAuth\Entity\Permission;
use Marko\Database\Repository\RepositoryInterface;

/**
 * Interface for Permission entity repository.
 */
interface PermissionRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a permission by its key.
     */
    public function findByKey(
        string $key,
    ): ?Permission;

    /**
     * Find all permissions in a group.
     *
     * @return array<Permission>
     */
    public function findByGroup(
        string $group,
    ): array;

    /**
     * Sync permissions from the registry to the database.
     *
     * Creates new permissions that exist in the registry but not in the database.
     * Preserves existing permissions.
     */
    public function syncFromRegistry(): void;
}
