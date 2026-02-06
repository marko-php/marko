<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Repository;

use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Repository\AdminUserRepository;
use Marko\AdminAuth\Repository\AdminUserRepositoryInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;
use ReflectionClass;
use RuntimeException;

it('creates AdminUserRepository extending Repository', function (): void {
    $reflection = new ReflectionClass(AdminUserRepository::class);

    expect($reflection->isSubclassOf(Repository::class))->toBeTrue()
        ->and($reflection->implementsInterface(AdminUserRepositoryInterface::class))->toBeTrue();
});

it('defines ENTITY_CLASS constant pointing to AdminUser entity', function (): void {
    $reflection = new ReflectionClass(AdminUserRepository::class);

    expect($reflection->hasConstant('ENTITY_CLASS'))->toBeTrue()
        ->and($reflection->getConstant('ENTITY_CLASS'))->toBe(AdminUser::class);
});

it('can find an admin user by id using inherited find method', function (): void {
    $connection = createAdminUserMockConnection([
        [
            'id' => 1,
            'email' => 'admin@example.com',
            'password' => 'hashed_password',
            'name' => 'Admin User',
            'remember_token' => null,
            'is_active' => '1',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AdminUserRepository($connection, $metadataFactory, $hydrator);

    $user = $repository->find(1);

    expect($user)->toBeInstanceOf(AdminUser::class)
        ->and($user->id)->toBe(1)
        ->and($user->email)->toBe('admin@example.com')
        ->and($user->name)->toBe('Admin User');
});

it('provides findByEmail convenience method for email lookups', function (): void {
    $connection = createAdminUserMockConnection([
        [
            'id' => 1,
            'email' => 'admin@example.com',
            'password' => 'hashed_password',
            'name' => 'Admin User',
            'remember_token' => null,
            'is_active' => '1',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AdminUserRepository($connection, $metadataFactory, $hydrator);

    $user = $repository->findByEmail('admin@example.com');

    expect($user)->toBeInstanceOf(AdminUser::class)
        ->and($user->email)->toBe('admin@example.com');
});

it('loads roles for a user via getRolesForUser', function (): void {
    $queryHistory = [];
    $connection = createAdminUserMockConnectionWithHistory(
        [
            [
                'id' => 1,
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Full access',
                'is_super_admin' => '1',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Can edit content',
                'is_super_admin' => '0',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
        ],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AdminUserRepository($connection, $metadataFactory, $hydrator);

    $roles = $repository->getRolesForUser(1);

    expect($roles)->toHaveCount(2)
        ->and($roles[0])->toBeInstanceOf(Role::class)
        ->and($roles[0]->name)->toBe('Administrator')
        ->and($roles[0]->slug)->toBe('admin')
        ->and($roles[1]->name)->toBe('Editor')
        ->and($queryHistory[0]['sql'])->toContain('admin_user_roles')
        ->and($queryHistory[0]['sql'])->toContain('user_id = ?')
        ->and($queryHistory[0]['bindings'])->toBe([1]);
});

it('syncs roles for a user via syncRoles', function (): void {
    $queryHistory = [];
    $connection = createAdminUserMockConnectionWithHistory(
        [],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AdminUserRepository($connection, $metadataFactory, $hydrator);

    $repository->syncRoles(1, [10, 20]);

    // First query should be DELETE of existing roles
    expect($queryHistory[0]['sql'])->toContain('DELETE FROM admin_user_roles')
        ->and($queryHistory[0]['sql'])->toContain('user_id = ?')
        ->and($queryHistory[0]['bindings'])->toBe([1]);

    // Next 2 queries should be INSERTs
    expect($queryHistory[1]['sql'])->toContain('INSERT INTO admin_user_roles')
        ->and($queryHistory[1]['bindings'])->toBe([1, 10])
        ->and($queryHistory[2]['bindings'])->toBe([1, 20]);
});

// Helper functions

function createAdminUserMockConnection(
    array $queryResult = [],
): ConnectionInterface {
    return createAdminUserMockConnectionWithHistory($queryResult, $unused);
}

/**
 * @param array<array<string, mixed>> $queryResult
 * @param array<array{sql: string, bindings: array<mixed>}>|null $queryHistory
 */
function createAdminUserMockConnectionWithHistory(
    array $queryResult = [],
    ?array &$queryHistory = null,
): ConnectionInterface {
    $queryHistory ??= [];

    return new class ($queryResult, $queryHistory) implements ConnectionInterface
    {
        /**
         * @param array<array<string, mixed>> $queryResult
         * @param array<array{sql: string, bindings: array<mixed>}> $queryHistory
         */
        public function __construct(
            private array $queryResult,
            private array &$queryHistory,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        /**
         * @param array<mixed> $bindings
         * @return array<array<string, mixed>>
         */
        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            $this->queryHistory[] = ['sql' => $sql, 'bindings' => $bindings];

            return $this->queryResult;
        }

        /**
         * @param array<mixed> $bindings
         */
        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            $this->queryHistory[] = ['sql' => $sql, 'bindings' => $bindings];

            return 1;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 1;
        }
    };
}
