<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Repository;

use Marko\AdminAuth\Entity\Permission;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Repository\RoleRepository;
use Marko\AdminAuth\Repository\RoleRepositoryInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;
use ReflectionClass;
use RuntimeException;

it('creates RoleRepository extending Repository', function (): void {
    $reflection = new ReflectionClass(RoleRepository::class);

    expect($reflection->isSubclassOf(Repository::class))->toBeTrue()
        ->and($reflection->implementsInterface(RoleRepositoryInterface::class))->toBeTrue();
});

it('defines ENTITY_CLASS constant pointing to Role entity', function (): void {
    $reflection = new ReflectionClass(RoleRepository::class);

    expect($reflection->hasConstant('ENTITY_CLASS'))->toBeTrue()
        ->and($reflection->getConstant('ENTITY_CLASS'))->toBe(Role::class);
});

it('can find a role by id using inherited find method', function (): void {
    $connection = createRoleMockConnection([
        [
            'id' => 1,
            'name' => 'Administrator',
            'slug' => 'admin',
            'description' => 'Full access',
            'is_super_admin' => '1',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator);

    $role = $repository->find(1);

    expect($role)->toBeInstanceOf(Role::class)
        ->and($role->id)->toBe(1)
        ->and($role->name)->toBe('Administrator')
        ->and($role->slug)->toBe('admin');
});

it('provides findBySlug convenience method for slug lookups', function (): void {
    $connection = createRoleMockConnection([
        [
            'id' => 1,
            'name' => 'Editor',
            'slug' => 'editor',
            'description' => 'Can edit content',
            'is_super_admin' => '0',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator);

    $role = $repository->findBySlug('editor');

    expect($role)->toBeInstanceOf(Role::class)
        ->and($role->slug)->toBe('editor')
        ->and($role->name)->toBe('Editor');
});

it('checks if slug is unique via isSlugUnique method', function (): void {
    $queryHistory = [];
    $connection = createRoleMockConnectionWithHistory(
        [],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator);
    $isUnique = $repository->isSlugUnique('new-unique-slug');

    expect($isUnique)->toBeTrue()
        ->and($queryHistory[0]['sql'])->toContain('slug = ?')
        ->and($queryHistory[0]['bindings'])->toContain('new-unique-slug');
});

it('checks slug uniqueness excludes given id', function (): void {
    $queryHistory = [];
    $connection = createRoleMockConnectionWithHistory(
        [],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator);
    $isUnique = $repository->isSlugUnique('existing-slug', 5);

    expect($isUnique)->toBeTrue()
        ->and($queryHistory[0]['sql'])->toContain('slug = ?')
        ->and($queryHistory[0]['sql'])->toContain('id != ?')
        ->and($queryHistory[0]['bindings'])->toBe(['existing-slug', 5]);
});

it('loads permissions for a role via getPermissionsForRole', function (): void {
    $queryHistory = [];
    $connection = createRoleMockConnectionWithHistory(
        [
            [
                'id' => 1,
                'key' => 'blog.posts.create',
                'label' => 'Create Posts',
                'group' => 'blog',
                'created_at' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'key' => 'blog.posts.edit',
                'label' => 'Edit Posts',
                'group' => 'blog',
                'created_at' => '2024-01-01 00:00:00',
            ],
        ],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator);

    $permissions = $repository->getPermissionsForRole(1);

    expect($permissions)->toHaveCount(2)
        ->and($permissions[0])->toBeInstanceOf(Permission::class)
        ->and($permissions[0]->key)->toBe('blog.posts.create')
        ->and($permissions[1]->key)->toBe('blog.posts.edit')
        ->and($queryHistory[0]['sql'])->toContain('role_permissions')
        ->and($queryHistory[0]['sql'])->toContain('role_id = ?')
        ->and($queryHistory[0]['bindings'])->toBe([1]);
});

it('replaces a role\'s permissions with the new set', function (): void {
    $queryHistory = [];
    $connection = createRoleMockConnectionWithHistory(
        [],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator);

    $repository->syncPermissions(1, [10, 20, 30]);

    expect($queryHistory[0]['sql'])->toContain('DELETE FROM role_permissions')
        ->and($queryHistory[0]['sql'])->toContain('role_id = ?')
        ->and($queryHistory[0]['bindings'])->toBe([1]);
});

it('clears all permissions when given an empty permission id list', function (): void {
    $queryHistory = [];
    $connection = createRoleMockConnectionWithHistory(
        [],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator);

    $repository->syncPermissions(1, []);

    expect($queryHistory)->toHaveCount(1)
        ->and($queryHistory[0]['sql'])->toContain('DELETE FROM role_permissions')
        ->and($queryHistory[0]['bindings'])->toBe([1]);
});

it('inserts all new permissions in a single multi-row insert', function (): void {
    $queryHistory = [];
    $connection = createRoleMockConnectionWithHistory(
        [],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator);

    $repository->syncPermissions(1, [10, 20, 30]);

    $insertEntries = array_values(array_filter(
        $queryHistory,
        fn (array $e): bool => str_contains($e['sql'], 'INSERT'),
    ));

    expect($insertEntries)->toHaveCount(1)
        ->and($insertEntries[0]['sql'])->toContain('INSERT INTO role_permissions')
        ->and($insertEntries[0]['bindings'])->toBe([1, 10, 1, 20, 1, 30]);
});

it('wraps the delete and insert in one transaction when the connection supports transactions', function (): void {
    $txLog = [];
    $connection = createRoleTransactionalConnectionWithHistory([], $txLog);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator);

    $repository->syncPermissions(1, [10, 20]);

    $ops = array_column($txLog, 'op');

    expect($ops[0])->toBe('beginTransaction')
        ->and(in_array('execute', $ops, true))->toBeTrue()
        ->and($ops[count($ops) - 1])->toBe('commit');
});

it('rolls back and leaves permissions unchanged when an insert fails mid-sync', function (): void {
    $txLog = [];
    $connection = createRoleTransactionalConnectionWithHistory([], $txLog, failOnInsert: true);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator);

    expect(fn () => $repository->syncPermissions(1, [10, 20]))
        ->toThrow(RuntimeException::class);

    $ops = array_column($txLog, 'op');

    expect(in_array('beginTransaction', $ops, true))->toBeTrue()
        ->and(in_array('rollback', $ops, true))->toBeTrue()
        ->and(in_array('commit', $ops, true))->toBeFalse();
});

it('still syncs when the connection does not support transactions', function (): void {
    $queryHistory = [];
    $connection = createRoleMockConnectionWithHistory([], $queryHistory);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator);

    $repository->syncPermissions(1, [10, 20]);

    $insertEntries = array_values(array_filter(
        $queryHistory,
        fn (array $e): bool => str_contains($e['sql'], 'INSERT'),
    ));

    expect($insertEntries)->toHaveCount(1)
        ->and($insertEntries[0]['bindings'])->toBe([1, 10, 1, 20]);
});

it('returns an empty array from getPermissionsForRoles without querying when given no role ids', function (): void {
    $queryHistory = [];
    $connection = createRoleMockConnectionWithHistory([], $queryHistory);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator);

    $permissions = $repository->getPermissionsForRoles([]);

    expect($permissions)->toBeEmpty()
        ->and($queryHistory)->toBeEmpty();
});

it(
    'queries permissions with a WHERE role_id IN clause whose placeholder count matches the role ids',
    function (): void {
        $queryHistory = [];
        $connection = createRoleMockConnectionWithHistory(
            [
                [
                    'id' => 1,
                    'key' => 'posts.create',
                    'label' => 'Create Posts',
                    'group' => 'posts',
                    'created_at' => '2024-01-01 00:00:00',
                ],
                [
                    'id' => 2,
                    'key' => 'posts.edit',
                    'label' => 'Edit Posts',
                    'group' => 'posts',
                    'created_at' => '2024-01-01 00:00:00',
                ],
            ],
            $queryHistory,
        );
        $metadataFactory = new EntityMetadataFactory();
        $hydrator = new EntityHydrator();

        $repository = new RoleRepository($connection, $metadataFactory, $hydrator);

        $permissions = $repository->getPermissionsForRoles([1, 2, 3]);

        expect($permissions)->toHaveCount(2)
            ->and($queryHistory)->toHaveCount(1)
            ->and($queryHistory[0]['sql'])->toContain('IN (')
            ->and($queryHistory[0]['sql'])->toContain('role_permissions')
            ->and($queryHistory[0]['bindings'])->toBe([1, 2, 3]);
    },
);

it('issues exactly one permissions query for a user with multiple roles', function (): void {
    $queryHistory = [];
    $connection = createRoleMockConnectionWithHistory(
        [
            [
                'id' => 1,
                'key' => 'posts.create',
                'label' => 'Create Posts',
                'group' => 'posts',
                'created_at' => '2024-01-01 00:00:00',
            ],
        ],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator);

    $repository->getPermissionsForRoles([1, 2, 3]);

    $permissionQueries = array_filter(
        $queryHistory,
        fn (array $entry): bool => str_contains($entry['sql'], 'role_permissions') && str_contains(
            $entry['sql'],
            'IN (',
        ),
    );

    expect($permissionQueries)->toHaveCount(1);
});

it('issues no permissions query when the user has no roles', function (): void {
    $queryHistory = [];
    $connection = createRoleMockConnectionWithHistory([], $queryHistory);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator);

    $permissions = $repository->getPermissionsForRoles([]);

    expect($permissions)->toBeEmpty()
        ->and($queryHistory)->toBeEmpty();
});

// Helper functions

/**
 * @param array<array<string, mixed>> $queryResult
 * @param array<array{op: string, sql?: string, bindings?: array}> $txLog
 */
function createRoleTransactionalConnectionWithHistory(
    array $queryResult = [],
    ?array &$txLog = null,
    bool $failOnInsert = false,
): ConnectionInterface&TransactionInterface {
    $txLog ??= [];

    return new class ($queryResult, $txLog, $failOnInsert) implements ConnectionInterface, TransactionInterface
    {
        /**
         * @param array<array<string, mixed>> $queryResult
         * @param array<array{op: string, sql?: string, bindings?: array}> $txLog
         */
        public function __construct(
            private readonly array $queryResult,
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$txLog,
            private readonly bool $failOnInsert,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            $this->txLog[] = ['op' => 'execute', 'sql' => $sql, 'bindings' => $bindings];

            return $this->queryResult;
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            if ($this->failOnInsert && str_contains($sql, 'INSERT')) {
                throw new RuntimeException('Simulated insert failure');
            }

            $this->txLog[] = ['op' => 'execute', 'sql' => $sql, 'bindings' => $bindings];

            return 1;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 1;
        }

        public function driverName(): string
        {
            return 'sqlite';
        }

        public function beginTransaction(): void
        {
            $this->txLog[] = ['op' => 'beginTransaction'];
        }

        public function commit(): void
        {
            $this->txLog[] = ['op' => 'commit'];
        }

        public function rollback(): void
        {
            $this->txLog[] = ['op' => 'rollback'];
        }

        public function inTransaction(): bool
        {
            return array_any($this->txLog, fn (array $e): bool => $e['op'] === 'beginTransaction')
                && !array_any($this->txLog, fn (array $e): bool => $e['op'] === 'commit' || $e['op'] === 'rollback');
        }

        public function transaction(callable $callback): null
        {
            return null;
        }
    };
}

function createRoleMockConnection(
    array $queryResult = [],
): ConnectionInterface {
    return createRoleMockConnectionWithHistory($queryResult, $unused);
}

/**
 * @param array<array<string, mixed>> $queryResult
 * @param array<array{sql: string, bindings: array}>|null $queryHistory
 */
function createRoleMockConnectionWithHistory(
    array $queryResult = [],
    ?array &$queryHistory = null,
): ConnectionInterface {
    $queryHistory ??= [];

    return new class ($queryResult, $queryHistory) implements ConnectionInterface
    {
        /**
         * @param array<array<string, mixed>> $queryResult
         * @param array<array{sql: string, bindings: array}> $queryHistory
         */
        public function __construct(
            private readonly array $queryResult,
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$queryHistory,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        /**
         * @param string $sql
         * @param array $bindings
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
         * @param string $sql
         * @param array $bindings
         * @return int
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

        public function driverName(): string
        {
            return 'sqlite';
        }
    };
}
