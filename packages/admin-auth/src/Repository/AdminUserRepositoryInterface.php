<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Repository;

use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\Role;
use Marko\Database\Repository\RepositoryInterface;

/**
 * Interface for AdminUser entity repository.
 */
interface AdminUserRepositoryInterface extends RepositoryInterface
{
    /**
     * Find an admin user by email address.
     */
    public function findByEmail(
        string $email,
    ): ?AdminUser;

    /**
     * Get all roles for a user.
     *
     * @return array<Role>
     */
    public function getRolesForUser(
        int $userId,
    ): array;

    /**
     * Sync roles for a user, replacing all existing.
     *
     * @param array<int> $roleIds
     */
    public function syncRoles(
        int $userId,
        array $roleIds,
    ): void;
}
