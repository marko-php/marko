<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit;

use Marko\AdminAuth\AdminUserProvider;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\Permission;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Repository\AdminUserRepositoryInterface;
use Marko\AdminAuth\Repository\RoleRepositoryInterface;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use ReflectionClass;
use RuntimeException;

/**
 * Tracking role repo: records which batch methods were called.
 * getPermissionsForRole throws to confirm it is never called after rewiring.
 */
class TrackingRoleRepo implements RoleRepositoryInterface
{
    /** @var array<int> */
    public array $batchRoleIdsReceived = [];

    public int $batchCallCount = 0;

    /**
     * @param array<int, array<Permission>> $permissionsMap
     */
    public function __construct(
        private readonly array $permissionsMap = [],
    ) {}

    public function find(int|string $id): ?Role
    {
        return null;
    }

    public function findOrFail(int|string $id): Role
    {
        throw new RuntimeException('Not found');
    }

    public function findAll(): EntityCollection
    {
        return new EntityCollection();
    }

    public function findBy(array $criteria): EntityCollection
    {
        return new EntityCollection();
    }

    public function findOneBy(array $criteria): ?Role
    {
        return null;
    }

    public function existsBy(array $criteria): bool
    {
        return false;
    }

    public function findBySlug(string $slug): ?Role
    {
        return null;
    }

    public function getPermissionsForRole(int $roleId): array
    {
        throw new RuntimeException('getPermissionsForRole must not be called after rewiring to batch');
    }

    public function getPermissionsForRoles(array $roleIds): array
    {
        $this->batchCallCount++;
        $this->batchRoleIdsReceived = $roleIds;

        if ($roleIds === []) {
            return [];
        }

        $permissions = [];
        $seen = [];

        foreach ($roleIds as $rid) {
            foreach ($this->permissionsMap[$rid] ?? [] as $permission) {
                if (!in_array($permission->key, $seen, true)) {
                    $seen[] = $permission->key;
                    $permissions[] = $permission;
                }
            }
        }

        return $permissions;
    }

    public function syncPermissions(
        int $roleId,
        array $permissionIds,
    ): void {}

    public function isSlugUnique(
        string $slug,
        ?int $excludeId = null,
    ): bool {
        return true;
    }

    public function save(Entity $entity): void {}

    public function delete(Entity $entity): void {}

    public function insertBatch(array $entities): void {}
}

it('implements UserProviderInterface', function (): void {
    $reflection = new ReflectionClass(AdminUserProvider::class);

    expect($reflection->implementsInterface(UserProviderInterface::class))->toBeTrue();
});

it('retrieves admin user by id via retrieveById', function (): void {
    $user = createTestAdminUser();

    $userRepo = createMockUserRepo(findReturn: $user);
    $roleRepo = createMockRoleRepo();
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher);

    $result = $provider->retrieveById(1);

    expect($result)->toBeInstanceOf(AdminUser::class)
        ->and($result->id)->toBe(1)
        ->and($result->email)->toBe('admin@example.com');
});

it('returns null from retrieveById when user not found', function (): void {
    $userRepo = createMockUserRepo();
    $roleRepo = createMockRoleRepo();
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher);

    $result = $provider->retrieveById(999);

    expect($result)->toBeNull();
});

it('returns null from retrieveById when user is inactive', function (): void {
    $user = createTestAdminUser(isActive: '0');

    $userRepo = createMockUserRepo(findReturn: $user);
    $roleRepo = createMockRoleRepo();
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher);

    $result = $provider->retrieveById(1);

    expect($result)->toBeNull();
});

it('retrieves admin user by email credentials via retrieveByCredentials', function (): void {
    $user = createTestAdminUser();

    $userRepo = createMockUserRepo(findByEmailReturn: $user);
    $roleRepo = createMockRoleRepo();
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher);

    $result = $provider->retrieveByCredentials(['email' => 'admin@example.com', 'password' => 'secret']);

    expect($result)->toBeInstanceOf(AdminUser::class)
        ->and($result->id)->toBe(1)
        ->and($result->email)->toBe('admin@example.com');
});

it('returns null from retrieveByCredentials when email not found', function (): void {
    $userRepo = createMockUserRepo();
    $roleRepo = createMockRoleRepo();
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher);

    $result = $provider->retrieveByCredentials(['email' => 'nonexistent@example.com', 'password' => 'secret']);

    expect($result)->toBeNull();
});

it('validates credentials using PasswordHasherInterface', function (): void {
    $user = createTestAdminUser();

    $userRepo = createMockUserRepo();
    $roleRepo = createMockRoleRepo();
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher);

    $result = $provider->validateCredentials($user, ['password' => 'plain_password']);

    expect($result)->toBeTrue();
});

it('returns false from validateCredentials when password is wrong', function (): void {
    $user = createTestAdminUser();

    $userRepo = createMockUserRepo();
    $roleRepo = createMockRoleRepo();
    $hasher = createMockHasher(verifyReturn: false);

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher);

    $result = $provider->validateCredentials($user, ['password' => 'wrong_password']);

    expect($result)->toBeFalse();
});

it('loads roles and permissions when retrieving a user', function (): void {
    $user = createTestAdminUser();

    $editorRole = new Role();
    $editorRole->id = 1;
    $editorRole->name = 'Editor';
    $editorRole->slug = 'editor';
    $editorRole->isSuperAdmin = '0';

    $moderatorRole = new Role();
    $moderatorRole->id = 2;
    $moderatorRole->name = 'Moderator';
    $moderatorRole->slug = 'moderator';
    $moderatorRole->isSuperAdmin = '0';

    $permission1 = new Permission();
    $permission1->id = 1;
    $permission1->key = 'posts.create';
    $permission1->label = 'Create Posts';
    $permission1->group = 'posts';

    $permission2 = new Permission();
    $permission2->id = 2;
    $permission2->key = 'posts.edit';
    $permission2->label = 'Edit Posts';
    $permission2->group = 'posts';

    $permission3 = new Permission();
    $permission3->id = 3;
    $permission3->key = 'comments.moderate';
    $permission3->label = 'Moderate Comments';
    $permission3->group = 'comments';

    $userRepo = createMockUserRepo(findReturn: $user, rolesReturn: [$editorRole, $moderatorRole]);
    $roleRepo = createMockRoleRepo(permissionsMap: [
        1 => [$permission1, $permission2],
        2 => [$permission3],
    ]);
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher);

    $result = $provider->retrieveById(1);

    expect($result)->toBeInstanceOf(AdminUser::class)
        ->and($result->getRoles())->toHaveCount(2)
        ->and($result->getRoles()[0]->getSlug())->toBe('editor')
        ->and($result->getRoles()[1]->getSlug())->toBe('moderator')
        ->and($result->hasPermission('posts.create'))->toBeTrue()
        ->and($result->hasPermission('posts.edit'))->toBeTrue()
        ->and($result->hasPermission('comments.moderate'))->toBeTrue()
        ->and($result->hasPermission('nonexistent.permission'))->toBeFalse();
});

it('retrieves user by remember token via retrieveByRememberToken', function (): void {
    $user = createTestAdminUser(rememberToken: 'valid_token_123');

    $userRepo = createMockUserRepo(findReturn: $user);
    $roleRepo = createMockRoleRepo();
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher);

    $result = $provider->retrieveByRememberToken(1, 'valid_token_123');

    expect($result)->toBeInstanceOf(AdminUser::class)
        ->and($result->id)->toBe(1)
        ->and($result->getRememberToken())->toBe('valid_token_123');
});

it('updates remember token via updateRememberToken', function (): void {
    $user = createTestAdminUser();

    $userRepo = createMockUserRepo();
    $roleRepo = createMockRoleRepo();
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher);

    $provider->updateRememberToken($user, 'new_remember_token');

    expect($user->getRememberToken())->toBe('new_remember_token')
        ->and($userRepo->saveCallCount)->toBe(1)
        ->and($userRepo->lastSavedUser)->toBe($user);
});

it('loads the same permission set as the per-role implementation', function (): void {
    $user = createTestAdminUser();

    $editorRole = new Role();
    $editorRole->id = 1;
    $editorRole->name = 'Editor';
    $editorRole->slug = 'editor';
    $editorRole->isSuperAdmin = '0';

    $permission1 = new Permission();
    $permission1->id = 1;
    $permission1->key = 'posts.create';
    $permission1->label = 'Create Posts';
    $permission1->group = 'posts';

    $permission2 = new Permission();
    $permission2->id = 2;
    $permission2->key = 'posts.edit';
    $permission2->label = 'Edit Posts';
    $permission2->group = 'posts';

    $roleRepo = new TrackingRoleRepo(permissionsMap: [
        1 => [$permission1, $permission2],
    ]);
    $userRepo = createMockUserRepo(findReturn: $user, rolesReturn: [$editorRole]);
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher);

    $result = $provider->retrieveById(1);

    expect($result)->toBeInstanceOf(AdminUser::class)
        ->and($result->hasPermission('posts.create'))->toBeTrue()
        ->and($result->hasPermission('posts.edit'))->toBeTrue()
        ->and($roleRepo->batchCallCount)->toBe(1);
});

it('deduplicates permission keys shared across roles', function (): void {
    $user = createTestAdminUser();

    $editorRole = new Role();
    $editorRole->id = 1;
    $editorRole->name = 'Editor';
    $editorRole->slug = 'editor';
    $editorRole->isSuperAdmin = '0';

    $moderatorRole = new Role();
    $moderatorRole->id = 2;
    $moderatorRole->name = 'Moderator';
    $moderatorRole->slug = 'moderator';
    $moderatorRole->isSuperAdmin = '0';

    $sharedPermission = new Permission();
    $sharedPermission->id = 1;
    $sharedPermission->key = 'posts.create';
    $sharedPermission->label = 'Create Posts';
    $sharedPermission->group = 'posts';

    $uniquePermission = new Permission();
    $uniquePermission->id = 2;
    $uniquePermission->key = 'comments.moderate';
    $uniquePermission->label = 'Moderate Comments';
    $uniquePermission->group = 'comments';

    $roleRepo = new TrackingRoleRepo(permissionsMap: [
        1 => [$sharedPermission],
        2 => [$sharedPermission, $uniquePermission],
    ]);
    $userRepo = createMockUserRepo(findReturn: $user, rolesReturn: [$editorRole, $moderatorRole]);
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher);

    $result = $provider->retrieveById(1);

    $roles = $result->getRoles();

    expect($result)->toBeInstanceOf(AdminUser::class)
        ->and($result->hasPermission('posts.create'))->toBeTrue()
        ->and($result->hasPermission('comments.moderate'))->toBeTrue()
        ->and($roles)->toHaveCount(2)
        ->and($roleRepo->batchCallCount)->toBe(1);
});

it('sets roles and unique permission keys on the authenticated user', function (): void {
    $user = createTestAdminUser();

    $editorRole = new Role();
    $editorRole->id = 1;
    $editorRole->name = 'Editor';
    $editorRole->slug = 'editor';
    $editorRole->isSuperAdmin = '0';

    $permission = new Permission();
    $permission->id = 1;
    $permission->key = 'posts.create';
    $permission->label = 'Create Posts';
    $permission->group = 'posts';

    $roleRepo = new TrackingRoleRepo(permissionsMap: [
        1 => [$permission],
    ]);
    $userRepo = createMockUserRepo(findReturn: $user, rolesReturn: [$editorRole]);
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher);

    $result = $provider->retrieveById(1);

    expect($result)->toBeInstanceOf(AdminUser::class)
        ->and($result->getRoles())->toHaveCount(1)
        ->and($result->getRoles()[0]->getSlug())->toBe('editor')
        ->and($result->hasPermission('posts.create'))->toBeTrue()
        ->and($roleRepo->batchCallCount)->toBe(1)
        ->and($roleRepo->batchRoleIdsReceived)->toBe([1]);
});

// Helper functions

function createTestAdminUser(
    int $id = 1,
    string $email = 'admin@example.com',
    string $password = 'hashed_password',
    string $name = 'Admin',
    string $isActive = '1',
    ?string $rememberToken = null,
): AdminUser {
    $user = new AdminUser();
    $user->id = $id;
    $user->email = $email;
    $user->password = $password;
    $user->name = $name;
    $user->isActive = $isActive;
    $user->rememberToken = $rememberToken;

    return $user;
}

function createMockUserRepo(
    ?AdminUser $findReturn = null,
    ?AdminUser $findByEmailReturn = null,
    array $rolesReturn = [],
): AdminUserRepositoryInterface {
    return new class ($findReturn, $findByEmailReturn, $rolesReturn) implements AdminUserRepositoryInterface
    {
        public int $saveCallCount = 0;

        public ?AdminUser $lastSavedUser = null;

        public function __construct(
            private readonly ?AdminUser $findReturn,
            private readonly ?AdminUser $findByEmailReturn,
            private readonly array $rolesReturn,
        ) {}

        public function find(
            int|string $id,
        ): ?AdminUser {
            return $this->findReturn;
        }

        public function findOrFail(
            int|string $id,
        ): AdminUser {
            return $this->findReturn ?? throw new RuntimeException('Not found');
        }

        public function findAll(): EntityCollection
        {
            return new EntityCollection();
        }

        public function findBy(
            array $criteria,
        ): EntityCollection {
            return new EntityCollection();
        }

        public function findOneBy(
            array $criteria,
        ): ?AdminUser {
            return null;
        }

        public function existsBy(
            array $criteria,
        ): bool {
            return $this->findOneBy(criteria: $criteria) !== null;
        }

        public function findByEmail(
            string $email,
        ): ?AdminUser {
            return $this->findByEmailReturn;
        }

        public function getRolesForUser(
            int $userId,
        ): array {
            return $this->rolesReturn;
        }

        public function syncRoles(
            int $userId,
            array $roleIds,
        ): void {}

        public function save(
            Entity $entity,
        ): void {
            $this->saveCallCount++;
            if ($entity instanceof AdminUser) {
                $this->lastSavedUser = $entity;
            }
        }

        public function delete(Entity $entity): void {}

        public function insertBatch(array $entities): void {}
    };
}

function createMockRoleRepo(
    array $permissionsMap = [],
): RoleRepositoryInterface {
    return new readonly class ($permissionsMap) implements RoleRepositoryInterface
    {
        /**
         * @param array<int, array<Permission>> $permissionsMap
         */
        public function __construct(
            private array $permissionsMap,
        ) {}

        public function find(
            int|string $id,
        ): ?Role {
            return null;
        }

        public function findOrFail(
            int|string $id,
        ): Role {
            throw new RuntimeException('Not found');
        }

        public function findAll(): EntityCollection
        {
            return new EntityCollection();
        }

        public function findBy(
            array $criteria,
        ): EntityCollection {
            return new EntityCollection();
        }

        public function findOneBy(
            array $criteria,
        ): ?Role {
            return null;
        }

        public function existsBy(
            array $criteria,
        ): bool {
            return $this->findOneBy(criteria: $criteria) !== null;
        }

        public function findBySlug(
            string $slug,
        ): ?Role {
            return null;
        }

        public function getPermissionsForRole(
            int $roleId,
        ): array {
            return $this->permissionsMap[$roleId] ?? [];
        }

        public function getPermissionsForRoles(
            array $roleIds,
        ): array {
            if ($roleIds === []) {
                return [];
            }

            $permissions = [];
            $seen = [];

            foreach ($roleIds as $roleId) {
                foreach ($this->permissionsMap[$roleId] ?? [] as $permission) {
                    if (!in_array($permission->key, $seen, true)) {
                        $seen[] = $permission->key;
                        $permissions[] = $permission;
                    }
                }
            }

            return $permissions;
        }

        public function syncPermissions(
            int $roleId,
            array $permissionIds,
        ): void {}

        public function isSlugUnique(
            string $slug,
            ?int $excludeId = null,
        ): bool {
            return true;
        }

        public function save(Entity $entity): void {}

        public function delete(Entity $entity): void {}

        public function insertBatch(array $entities): void {}
    };
}

function createMockHasher(
    bool $verifyReturn = true,
): PasswordHasherInterface {
    return new readonly class ($verifyReturn) implements PasswordHasherInterface
    {
        public function __construct(
            private bool $verifyReturn,
        ) {}

        public function hash(
            string $password,
        ): string {
            return 'hashed_' . $password;
        }

        public function verify(
            string $password,
            string $hash,
        ): bool {
            return $this->verifyReturn;
        }

        public function needsRehash(
            string $hash,
        ): bool {
            return false;
        }
    };
}
