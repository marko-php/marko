<?php

declare(strict_types=1);

namespace Marko\AdminAuth;

use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Repository\AdminUserRepositoryInterface;
use Marko\AdminAuth\Repository\RoleRepositoryInterface;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Database\Exceptions\EntityException;

readonly class AdminUserProvider implements UserProviderInterface
{
    public function __construct(
        private AdminUserRepositoryInterface $userRepository,
        private RoleRepositoryInterface $roleRepository,
        private PasswordHasherInterface $passwordHasher,
    ) {}

    /**
     * @throws EntityException
     */
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

    /**
     * @throws EntityException
     */
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

    /**
     * @throws EntityException
     */
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

    /**
     * @throws EntityException
     */
    private function loadRolesAndPermissions(
        AdminUser $user,
    ): void {
        $roles = $this->userRepository->getRolesForUser($user->id);

        $roleIds = array_filter(
            array_map(fn (mixed $role): ?int => $role->id, $roles),
            fn (?int $id): bool => $id !== null,
        );

        $permissions = $this->roleRepository->getPermissionsForRoles(array_values($roleIds));

        $permissionKeys = array_unique(
            array_map(fn (mixed $permission): string => $permission->key, $permissions),
        );

        $user->setRoles(
            roles: $roles,
            permissionKeys: $permissionKeys,
        );
    }
}
