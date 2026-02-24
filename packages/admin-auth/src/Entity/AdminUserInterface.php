<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Entity;

use Marko\Authentication\AuthenticatableInterface;

interface AdminUserInterface extends AuthenticatableInterface
{
    public function getEmail(): string;

    public function getName(): string;

    /**
     * Set the loaded roles and their aggregated permission keys.
     *
     * @param array<Role> $roles
     * @param array<string> $permissionKeys
     */
    public function setRoles(
        array $roles,
        array $permissionKeys = [],
    ): void;

    /**
     * @return array<Role>
     */
    public function getRoles(): array;

    /**
     * Get the raw permission keys assigned via roles.
     *
     * @return array<string>
     */
    public function getPermissionKeys(): array;

    /**
     * Check if the user has a specific permission via their loaded roles.
     */
    public function hasPermission(
        string $key,
    ): bool;

    /**
     * Check if the user has a specific role by slug.
     */
    public function hasRole(
        string $slug,
    ): bool;
}
