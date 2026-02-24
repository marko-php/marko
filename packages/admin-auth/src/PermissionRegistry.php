<?php

declare(strict_types=1);

namespace Marko\AdminAuth;

use Marko\AdminAuth\Contracts\PermissionRegistryInterface;
use Marko\AdminAuth\Exceptions\AdminAuthException;

class PermissionRegistry implements PermissionRegistryInterface
{
    /** @var array<string, RegisteredPermission> */
    private array $permissions = [];

    /**
     * @throws AdminAuthException
     */
    public function register(
        string $key,
        string $label,
        string $group,
    ): void {
        if (isset($this->permissions[$key])) {
            throw AdminAuthException::duplicatePermission($key);
        }

        $this->permissions[$key] = new RegisteredPermission(
            key: $key,
            label: $label,
            group: $group,
        );
    }

    /**
     * @return array<RegisteredPermission>
     */
    public function all(): array
    {
        return array_values($this->permissions);
    }

    /**
     * @return array<RegisteredPermission>
     */
    public function getByGroup(
        string $group,
    ): array {
        return array_values(
            array_filter(
                $this->permissions,
                static fn (RegisteredPermission $permission): bool => $permission->group === $group,
            ),
        );
    }

    public function matches(
        string $pattern,
        string $permissionKey,
    ): bool {
        if ($pattern === '*') {
            return true;
        }

        if (!str_contains($pattern, '*')) {
            return $pattern === $permissionKey;
        }

        $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/';

        return (bool) preg_match($regex, $permissionKey);
    }
}
