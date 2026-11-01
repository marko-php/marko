<?php

declare(strict_types=1);

namespace Marko\AdminAuth;

use DateTimeImmutable;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Repository\AdminUserRepositoryInterface;
use Marko\AdminAuth\Repository\RoleRepositoryInterface;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Contracts\PasswordVerifierInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Database\Exceptions\EntityException;

readonly class AdminUserProvider implements UserProviderInterface
{
    public function __construct(
        private AdminUserRepositoryInterface $userRepository,
        private RoleRepositoryInterface $roleRepository,
        private PasswordHasherInterface $passwordHasher,
        private PasswordVerifierInterface $passwordVerifier,
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

        // Credentials come straight from request input: `email[]=x` is a failed login, not a TypeError
        $user = is_string($email) ? $this->userRepository->findByEmail($email) : null;

        if ($user === null || $user->isActive !== '1') {
            // Pay for a password check anyway, so response timing does not reveal which accounts exist
            $this->passwordVerifier->verify(null, $this->passwordFrom($credentials) ?? '');

            return null;
        }

        $this->loadRolesAndPermissions($user);

        return $user;
    }

    public function validateCredentials(
        AuthenticatableInterface $user,
        array $credentials,
    ): bool {
        $password = $this->passwordFrom($credentials);

        if ($password === null) {
            // Same cost as a real check, so a malformed password does not single out existing accounts
            $this->passwordVerifier->verify(null, '');

            return false;
        }

        return $this->passwordVerifier->verify($user, $password);
    }

    public function rehashPasswordIfNeeded(
        AuthenticatableInterface $user,
        array $credentials,
    ): void {
        $password = $this->passwordFrom($credentials);

        if (
            !$user instanceof AdminUser
            || $password === null
            || !$this->passwordHasher->needsRehash($user->getAuthPassword())
        ) {
            return;
        }

        $user->password = $this->passwordHasher->hash($password);

        $this->userRepository->save($user);
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function passwordFrom(
        array $credentials,
    ): ?string {
        $password = $credentials['password'] ?? null;

        return is_string($password) ? $password : null;
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

        $storedToken = $user->getRememberToken();

        if ($storedToken === null || !hash_equals($storedToken, $token)) {
            return null;
        }

        $this->loadRolesAndPermissions($user);

        return $user;
    }

    public function updateRememberToken(
        AuthenticatableInterface $user,
        ?string $token,
        ?DateTimeImmutable $expiresAt,
    ): void {
        if (!$user instanceof AdminUser) {
            return;
        }

        $user->setRememberToken($token);
        $user->setRememberTokenExpiresAt($expiresAt);

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
