<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit;

use DateTimeImmutable;
use Marko\AdminAuth\AdminUserProvider;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\Permission;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Repository\AdminUserRepositoryInterface;
use Marko\AdminAuth\Repository\RoleRepositoryInterface;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Contracts\PasswordVerifierInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Guard\SessionGuard;
use Marko\Authentication\Hashing\BcryptPasswordHasher;
use Marko\Authentication\Hashing\HashManagerPasswordHasher;
use Marko\Authentication\Hashing\PasswordVerifier;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Hashing\Config\HashConfig;
use Marko\Hashing\Factory\HasherFactory;
use Marko\Hashing\HashManager;
use Marko\Session\Contracts\SessionInterface;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeSession;
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

/**
 * Spy hasher: records every real and dummy verification so tests can prove each login path does
 * exactly one password check.
 */
class SpyPasswordHasher implements PasswordHasherInterface
{
    /** @var array<int, string> */
    public array $verifiedPasswords = [];

    /** @var array<int, string> */
    public array $dummyVerifiedPasswords = [];

    public function __construct(
        private readonly bool $verifyReturn = false,
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
        $this->verifiedPasswords[] = $password;

        return $this->verifyReturn;
    }

    public function needsRehash(
        string $hash,
    ): bool {
        return false;
    }

    public function verifyDummy(
        string $password,
    ): void {
        $this->dummyVerifiedPasswords[] = $password;
    }

    public function checkCount(): int
    {
        return count($this->verifiedPasswords) + count($this->dummyVerifiedPasswords);
    }
}

/**
 * Spy verifier: records every check (user and password) and answers with a fixed result.
 */
class SpyPasswordVerifier implements PasswordVerifierInterface
{
    /** @var array<int, array{user: ?AuthenticatableInterface, password: string}> */
    public array $checks = [];

    public function __construct(
        private readonly bool $verifyReturn = false,
    ) {}

    public function verify(
        ?AuthenticatableInterface $user,
        string $password,
    ): bool {
        $this->checks[] = ['user' => $user, 'password' => $password];

        return $user !== null && $this->verifyReturn;
    }
}

it('validates credentials through PasswordVerifierInterface', function (
    bool $verifierResult,
): void {
    $user = createTestAdminUser();
    $verifier = new SpyPasswordVerifier(verifyReturn: $verifierResult);
    $provider = new AdminUserProvider(createMockUserRepo(), createMockRoleRepo(), new SpyPasswordHasher(), $verifier);

    $result = $provider->validateCredentials($user, ['password' => 'secret']);

    expect($result)->toBe($verifierResult)
        ->and($verifier->checks)->toBe([['user' => $user, 'password' => 'secret']]);
})->with([
    'valid password' => [true],
    'invalid password' => [false],
]);

it('checks the password of an unknown user through PasswordVerifierInterface with no user', function (): void {
    $verifier = new SpyPasswordVerifier(verifyReturn: true);
    $hasher = new SpyPasswordHasher(verifyReturn: true);
    $provider = new AdminUserProvider(createMockUserRepo(), createMockRoleRepo(), $hasher, $verifier);

    $result = $provider->retrieveByCredentials(['email' => 'nobody@example.com', 'password' => 'guess']);

    expect($result)->toBeNull()
        ->and($verifier->checks)->toBe([['user' => null, 'password' => 'guess']])
        ->and($hasher->checkCount())->toBe(0);
});

it('checks a non-string password through PasswordVerifierInterface with no user', function (): void {
    $verifier = new SpyPasswordVerifier(verifyReturn: true);
    $hasher = new SpyPasswordHasher(verifyReturn: true);
    $provider = new AdminUserProvider(createMockUserRepo(), createMockRoleRepo(), $hasher, $verifier);

    $result = $provider->validateCredentials(createTestAdminUser(), ['password' => ['secret']]);

    expect($result)->toBeFalse()
        ->and($verifier->checks)->toBe([['user' => null, 'password' => '']])
        ->and($hasher->checkCount())->toBe(0);
});

it('rejects wrong and accepts right admin passwords end to end with real hashing', function (): void {
    $hasher = new BcryptPasswordHasher(cost: 4);
    $user = createTestAdminUser(password: $hasher->hash('secret'));
    $provider = new AdminUserProvider(
        createMockUserRepo(),
        createMockRoleRepo(),
        $hasher,
        new PasswordVerifier($hasher),
    );

    expect($provider->validateCredentials($user, ['password' => 'secret']))->toBeTrue()
        ->and($provider->validateCredentials($user, ['password' => 'wrong']))->toBeFalse();
});

it('implements UserProviderInterface', function (): void {
    $reflection = new ReflectionClass(AdminUserProvider::class);

    expect($reflection->implementsInterface(UserProviderInterface::class))->toBeTrue();
});

it('retrieves admin user by id via retrieveById', function (): void {
    $user = createTestAdminUser();

    $userRepo = createMockUserRepo(findReturn: $user);
    $roleRepo = createMockRoleRepo();
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher, new PasswordVerifier($hasher));

    $result = $provider->retrieveById(1);

    expect($result)->toBeInstanceOf(AdminUser::class)
        ->and($result->id)->toBe(1)
        ->and($result->email)->toBe('admin@example.com');
});

it('returns null from retrieveById when user not found', function (): void {
    $userRepo = createMockUserRepo();
    $roleRepo = createMockRoleRepo();
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher, new PasswordVerifier($hasher));

    $result = $provider->retrieveById(999);

    expect($result)->toBeNull();
});

it('returns null from retrieveById when user is inactive', function (): void {
    $user = createTestAdminUser(isActive: '0');

    $userRepo = createMockUserRepo(findReturn: $user);
    $roleRepo = createMockRoleRepo();
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher, new PasswordVerifier($hasher));

    $result = $provider->retrieveById(1);

    expect($result)->toBeNull();
});

it('retrieves admin user by email credentials via retrieveByCredentials', function (): void {
    $user = createTestAdminUser();

    $userRepo = createMockUserRepo(findByEmailReturn: $user);
    $roleRepo = createMockRoleRepo();
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher, new PasswordVerifier($hasher));

    $result = $provider->retrieveByCredentials(['email' => 'admin@example.com', 'password' => 'secret']);

    expect($result)->toBeInstanceOf(AdminUser::class)
        ->and($result->id)->toBe(1)
        ->and($result->email)->toBe('admin@example.com');
});

it('returns null from retrieveByCredentials when email not found', function (): void {
    $userRepo = createMockUserRepo();
    $roleRepo = createMockRoleRepo();
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher, new PasswordVerifier($hasher));

    $result = $provider->retrieveByCredentials(['email' => 'nonexistent@example.com', 'password' => 'secret']);

    expect($result)->toBeNull();
});

it('validates credentials using PasswordHasherInterface', function (): void {
    $user = createTestAdminUser();

    $userRepo = createMockUserRepo();
    $roleRepo = createMockRoleRepo();
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher, new PasswordVerifier($hasher));

    $result = $provider->validateCredentials($user, ['password' => 'plain_password']);

    expect($result)->toBeTrue();
});

it('returns false from validateCredentials when password is wrong', function (): void {
    $user = createTestAdminUser();

    $userRepo = createMockUserRepo();
    $roleRepo = createMockRoleRepo();
    $hasher = createMockHasher(verifyReturn: false);

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher, new PasswordVerifier($hasher));

    $result = $provider->validateCredentials($user, ['password' => 'wrong_password']);

    expect($result)->toBeFalse();
});

it('runs a dummy password check when the email is unknown', function (): void {
    $hasher = new SpyPasswordHasher();
    $provider = new AdminUserProvider(
        createMockUserRepo(),
        createMockRoleRepo(),
        $hasher,
        new PasswordVerifier($hasher),
    );

    $result = $provider->retrieveByCredentials(['email' => 'nobody@example.com', 'password' => 'guess']);

    expect($result)->toBeNull()
        ->and($hasher->dummyVerifiedPasswords)->toBe(['guess'])
        ->and($hasher->verifiedPasswords)->toBe([]);
});

it('runs a dummy password check when the user is inactive', function (): void {
    $hasher = new SpyPasswordHasher();
    $userRepo = createMockUserRepo(findByEmailReturn: createTestAdminUser(isActive: '0'));
    $provider = new AdminUserProvider($userRepo, createMockRoleRepo(), $hasher, new PasswordVerifier($hasher));

    $result = $provider->retrieveByCredentials(['email' => 'admin@example.com', 'password' => 'guess']);

    expect($result)->toBeNull()
        ->and($hasher->dummyVerifiedPasswords)->toBe(['guess'])
        ->and($hasher->verifiedPasswords)->toBe([]);
});

it('does not run a dummy password check when an active user is found', function (): void {
    $hasher = new SpyPasswordHasher();
    $userRepo = createMockUserRepo(findByEmailReturn: createTestAdminUser());
    $provider = new AdminUserProvider($userRepo, createMockRoleRepo(), $hasher, new PasswordVerifier($hasher));

    $result = $provider->retrieveByCredentials(['email' => 'admin@example.com', 'password' => 'guess']);

    expect($result)->toBeInstanceOf(AdminUser::class)
        ->and($hasher->dummyVerifiedPasswords)->toBe([]);
});

it('performs exactly one password check per failed login whether or not the account exists', function (
    ?AdminUser $storedUser,
): void {
    $hasher = new SpyPasswordHasher(verifyReturn: false);
    $userRepo = createMockUserRepo(findByEmailReturn: $storedUser);
    $provider = new AdminUserProvider($userRepo, createMockRoleRepo(), $hasher, new PasswordVerifier($hasher));
    $guard = new SessionGuard(test()->createStub(SessionInterface::class), $provider);

    $result = $guard->attempt(['email' => 'admin@example.com', 'password' => 'guess']);

    expect($result)->toBeFalse()
        ->and($hasher->checkCount())->toBe(1);
})->with([
    'unknown email' => [null],
    'inactive user' => [fn (): AdminUser => createTestAdminUser(isActive: '0')],
    'active user, wrong password' => [fn (): AdminUser => createTestAdminUser()],
]);

it('returns null from retrieveByCredentials when the email is not a string', function (
    mixed $email,
): void {
    $hasher = new SpyPasswordHasher();
    $provider = new AdminUserProvider(
        createMockUserRepo(),
        createMockRoleRepo(),
        $hasher,
        new PasswordVerifier($hasher),
    );

    $result = $provider->retrieveByCredentials(['email' => $email, 'password' => 'guess']);

    expect($result)->toBeNull()
        ->and($hasher->dummyVerifiedPasswords)->toBe(['guess']);
})->with([
    'array' => [['admin@example.com']],
    'null' => [null],
    'integer' => [42],
]);

it('runs the dummy check with an empty password when the password is not a string', function (): void {
    $hasher = new SpyPasswordHasher();
    $provider = new AdminUserProvider(
        createMockUserRepo(),
        createMockRoleRepo(),
        $hasher,
        new PasswordVerifier($hasher),
    );

    $result = $provider->retrieveByCredentials(['email' => 'nobody@example.com', 'password' => ['guess']]);

    expect($result)->toBeNull()
        ->and($hasher->dummyVerifiedPasswords)->toBe(['']);
});

it('returns false from validateCredentials when the password is not a string', function (
    mixed $password,
): void {
    $hasher = new SpyPasswordHasher(verifyReturn: true);
    $provider = new AdminUserProvider(
        createMockUserRepo(),
        createMockRoleRepo(),
        $hasher,
        new PasswordVerifier($hasher),
    );

    $result = $provider->validateCredentials(createTestAdminUser(), ['password' => $password]);

    expect($result)->toBeFalse()
        ->and($hasher->verifiedPasswords)->toBe([])
        ->and($hasher->dummyVerifiedPasswords)->toBe(['']);
})->with([
    'array' => [['secret']],
    'null' => [null],
    'integer' => [123],
]);

it('rejects array-valued login fields through the guard without a TypeError', function (): void {
    $hasher = new SpyPasswordHasher(verifyReturn: true);
    $userRepo = createMockUserRepo(findByEmailReturn: createTestAdminUser());
    $provider = new AdminUserProvider($userRepo, createMockRoleRepo(), $hasher, new PasswordVerifier($hasher));
    $guard = new SessionGuard(test()->createStub(SessionInterface::class), $provider);

    expect($guard->attempt(['email' => ['admin@example.com'], 'password' => 'secret']))->toBeFalse()
        ->and($guard->attempt(['email' => 'admin@example.com', 'password' => ['secret']]))->toBeFalse();
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

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher, new PasswordVerifier($hasher));

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

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher, new PasswordVerifier($hasher));

    $result = $provider->retrieveByRememberToken(1, 'valid_token_123');

    expect($result)->toBeInstanceOf(AdminUser::class)
        ->and($result->id)->toBe(1)
        ->and($result->getRememberToken())->toBe('valid_token_123');
});

it('returns null from retrieveByRememberToken when the stored token does not match', function (): void {
    $user = createTestAdminUser(rememberToken: 'valid_token_123');

    $provider = new AdminUserProvider(
        createMockUserRepo(findReturn: $user),
        createMockRoleRepo(),
        createMockHasher(),
        new PasswordVerifier(createMockHasher()),
    );

    expect($provider->retrieveByRememberToken(1, 'forged_token'))->toBeNull();
});

it('returns null from retrieveByRememberToken when the user has no stored token', function (): void {
    $user = createTestAdminUser();

    $provider = new AdminUserProvider(
        createMockUserRepo(findReturn: $user),
        createMockRoleRepo(),
        createMockHasher(),
        new PasswordVerifier(createMockHasher()),
    );

    expect($provider->retrieveByRememberToken(1, ''))->toBeNull();
});

it('updates remember token via updateRememberToken', function (): void {
    $user = createTestAdminUser();

    $userRepo = createMockUserRepo();
    $roleRepo = createMockRoleRepo();
    $hasher = createMockHasher();

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher, new PasswordVerifier($hasher));

    $expiresAt = new DateTimeImmutable('2030-01-01 00:00:00');
    $provider->updateRememberToken($user, 'new_remember_token', $expiresAt);

    expect($user->getRememberToken())->toBe('new_remember_token')
        ->and($user->getRememberTokenExpiresAt())->toBe($expiresAt)
        ->and($userRepo->saveCallCount)->toBe(1)
        ->and($userRepo->lastSavedUser)->toBe($user);
});

it('rehashes and saves the password when the bcrypt cost has been raised', function (): void {
    $user = createTestAdminUser(password: new BcryptPasswordHasher(cost: 4)->hash('secret'));
    $userRepo = createMockUserRepo();
    $hasher = new BcryptPasswordHasher(cost: 5);
    $provider = new AdminUserProvider($userRepo, createMockRoleRepo(), $hasher, new PasswordVerifier($hasher));

    $provider->rehashPasswordIfNeeded($user, ['password' => 'secret']);

    expect($user->password)->toStartWith('$2y$05$')
        ->and($hasher->verify('secret', $user->password))->toBeTrue()
        ->and($userRepo->saveCallCount)->toBe(1)
        ->and($userRepo->lastSavedUser)->toBe($user);
});

it('leaves an up-to-date password hash alone', function (): void {
    $hash = new BcryptPasswordHasher(cost: 4)->hash('secret');
    $user = createTestAdminUser(password: $hash);
    $userRepo = createMockUserRepo();
    $provider = new AdminUserProvider(
        $userRepo,
        createMockRoleRepo(),
        new BcryptPasswordHasher(cost: 4),
        new PasswordVerifier(new BcryptPasswordHasher(cost: 4)),
    );

    $provider->rehashPasswordIfNeeded($user, ['password' => 'secret']);

    expect($user->password)->toBe($hash)
        ->and($userRepo->saveCallCount)->toBe(0);
});

it('does not rehash when the password is not a string', function (): void {
    $hash = new BcryptPasswordHasher(cost: 4)->hash('secret');
    $user = createTestAdminUser(password: $hash);
    $userRepo = createMockUserRepo();
    $provider = new AdminUserProvider(
        $userRepo,
        createMockRoleRepo(),
        new BcryptPasswordHasher(cost: 5),
        new PasswordVerifier(new BcryptPasswordHasher(cost: 5)),
    );

    $provider->rehashPasswordIfNeeded($user, ['password' => ['secret']]);

    expect($user->password)->toBe($hash)
        ->and($userRepo->saveCallCount)->toBe(0);
});

it('upgrades a bcrypt user to argon2id on login when marko/hashing uses the argon2id driver', function (): void {
    $user = createTestAdminUser(password: password_hash('secret', PASSWORD_BCRYPT, ['cost' => 4]));
    $userRepo = createMockUserRepo(findByEmailReturn: $user);
    $config = new HashConfig(new FakeConfigRepository([
        'hashing.default' => 'argon2id',
        'hashing.hashers.bcrypt.cost' => 4,
        'hashing.hashers.argon2id.memory' => 1024,
        'hashing.hashers.argon2id.time' => 1,
        'hashing.hashers.argon2id.threads' => 1,
    ]));
    $hasher = new HashManagerPasswordHasher(new HashManager($config, new HasherFactory($config)));
    $provider = new AdminUserProvider($userRepo, createMockRoleRepo(), $hasher, new PasswordVerifier($hasher));
    $session = new FakeSession();
    $session->start();
    $guard = new SessionGuard($session, $provider);

    $loggedIn = $guard->attempt(['email' => 'admin@example.com', 'password' => 'secret']);

    expect($loggedIn)->toBeTrue()
        ->and($user->password)->toStartWith('$argon2id$')
        ->and($hasher->verify('secret', $user->password))->toBeTrue()
        ->and($userRepo->lastSavedUser)->toBe($user)
        ->and($guard->attempt(['email' => 'admin@example.com', 'password' => 'secret']))->toBeTrue()
        ->and($userRepo->saveCallCount)->toBe(1);
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

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher, new PasswordVerifier($hasher));

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

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher, new PasswordVerifier($hasher));

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

    $provider = new AdminUserProvider($userRepo, $roleRepo, $hasher, new PasswordVerifier($hasher));

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

        public function verifyDummy(
            string $password,
        ): void {}
    };
}
