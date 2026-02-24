<?php

declare(strict_types=1);

namespace Marko\AdminAuth;

use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Repository\AdminUserRepositoryInterface;
use Marko\AdminAuth\Repository\RoleRepositoryInterface;
use Marko\Auth\AuthenticatableInterface;
use Marko\Auth\Contracts\PasswordHasherInterface;
use Marko\Auth\Contracts\UserProviderInterface;

readonly class AdminUserProvider implements UserProviderInterface
{
    public function __construct(
        private AdminUserRepositoryInterface $userRepository,
        private RoleRepositoryInterface $roleRepository,
        private PasswordHasherInterface $passwordHasher,
    ) {}

    public function retrieveById(
        int|string $identifier,
    ): ?AuthenticatableInterface {
        $user = $this->userRepository->find((int) $identifier);

        if (!$user instanceof AdminUser) {
            return null;
        }

        if ($user->isActive !== '1') {
            return null;
        }

        $this->loadRolesAndPermissions($user);

        return $user;
    }

    public function retrieveByCredentials(
        array $credentials,
    ): ?AuthenticatableInterface {
        $email = $credentials['email'] ?? null;

        if ($email === null) {
            return null;
        }

        $user = $this->userRepository->findByEmail($email);

        if ($user === null) {
            return null;
        }

        if ($user->isActive !== '1') {
            return null;
        }

        $this->loadRolesAndPermissions($user);

        return $user;
    }

    public function validateCredentials(
        AuthenticatableInterface $user,
        array $credentials,
    ): bool {
        $password = $credentials['password'] ?? '';

        return $this->passwordHasher->verify($password, $user->getAuthPassword());
    }

    public function retrieveByRememberToken(
        int|string $identifier,
        string $token,
    ): ?AuthenticatableInterface {
        $user = $this->userRepository->find((int) $identifier);

        if (!$user instanceof AdminUser) {
            return null;
        }

        if ($user->isActive !== '1') {
            return null;
        }

        if ($user->getRememberToken() !== $token) {
            return null;
        }

        $this->loadRolesAndPermissions($user);

        return $user;
    }

    public function updateRememberToken(
        AuthenticatableInterface $user,
        ?string $token,
    ): void {
        if (!$user instanceof AdminUser) {
            return;
        }

        $user->setRememberToken($token);

        $this->userRepository->save($user);
    }

    private function loadRolesAndPermissions(
        AdminUser $user,
    ): void {
        $roles = $this->userRepository->getRolesForUser($user->id);

        $permissionKeys = [];

        foreach ($roles as $role) {
            if ($role->id === null) {
                continue;
            }

            $permissions = $this->roleRepository->getPermissionsForRole($role->id);

            foreach ($permissions as $permission) {
                $permissionKeys[] = $permission->key;
            }
        }

        $user->setRoles(
            roles: $roles,
            permissionKeys: array_unique($permissionKeys),
        );
    }
}
