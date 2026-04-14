<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('admin_users')]
class AdminUser extends Entity implements AdminUserInterface
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(unique: true)]
    public string $email;

    #[Column]
    public string $password;

    #[Column]
    public string $name;

    #[Column]
    public ?string $rememberToken = null;

    #[Column(default: '1')]
    public string $isActive = '1';

    #[Column]
    public ?string $createdAt = null;

    #[Column]
    public ?string $updatedAt = null;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAuthIdentifier(): int|string
    {
        return $this->id ?? 0;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function setRememberToken(
        ?string $token,
    ): void {
        $this->rememberToken = $token;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    /** @var array<Role> */
    private array $roles = [];

    /** @var array<string> */
    private array $permissionKeys = [];

    /**
     * Set the loaded roles and their aggregated permission keys.
     *
     * @param array<Role> $roles
     * @param array<string> $permissionKeys
     */
    public function setRoles(
        array $roles,
        array $permissionKeys = [],
    ): void {
        $this->roles = $roles;
        $this->permissionKeys = $permissionKeys;
    }

    /**
     * @return array<Role>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @return array<string>
     */
    public function getPermissionKeys(): array
    {
        return $this->permissionKeys;
    }

    public function hasPermission(
        string $key,
    ): bool {
        if (array_any($this->roles, fn ($role) => $role->isSuperAdmin())) {
            return true;
        }

        return in_array($key, $this->permissionKeys, true);
    }

    public function hasRole(
        string $slug,
    ): bool {
        return array_any($this->roles, fn ($role) => $role->getSlug() === $slug);
    }
}
