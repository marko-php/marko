<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Repository;

use Marko\AdminAuth\Entity\Permission;
use Marko\AdminAuth\PermissionRegistry;
use Marko\AdminAuth\Repository\PermissionRepository;
use Marko\AdminAuth\Repository\PermissionRepositoryInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;
use ReflectionClass;
use RuntimeException;

it('creates PermissionRepository extending Repository', function (): void {
    $reflection = new ReflectionClass(PermissionRepository::class);

    expect($reflection->isSubclassOf(Repository::class))->toBeTrue()
        ->and($reflection->implementsInterface(PermissionRepositoryInterface::class))->toBeTrue();
});

it('defines ENTITY_CLASS constant pointing to Permission entity', function (): void {
    $reflection = new ReflectionClass(PermissionRepository::class);

    expect($reflection->hasConstant('ENTITY_CLASS'))->toBeTrue()
        ->and($reflection->getConstant('ENTITY_CLASS'))->toBe(Permission::class);
});

it('can find a permission by id using inherited find method', function (): void {
    $connection = createPermissionMockConnection([
        [
            'id' => 1,
            'key' => 'blog.posts.create',
            'label' => 'Create Posts',
            'group' => 'blog',
            'created_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PermissionRepository($connection, $metadataFactory, $hydrator);

    $permission = $repository->find(1);

    expect($permission)->toBeInstanceOf(Permission::class)
        ->and($permission->id)->toBe(1)
        ->and($permission->key)->toBe('blog.posts.create')
        ->and($permission->label)->toBe('Create Posts')
        ->and($permission->group)->toBe('blog');
});

it('provides findByKey convenience method for key lookups', function (): void {
    $connection = createPermissionMockConnection([
        [
            'id' => 1,
            'key' => 'blog.posts.create',
            'label' => 'Create Posts',
            'group' => 'blog',
            'created_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PermissionRepository($connection, $metadataFactory, $hydrator);

    $permission = $repository->findByKey('blog.posts.create');

    expect($permission)->toBeInstanceOf(Permission::class)
        ->and($permission->key)->toBe('blog.posts.create');
});

it('provides findByGroup method for group lookups', function (): void {
    $queryHistory = [];
    $connection = createPermissionMockConnectionWithHistory(
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

    $repository = new PermissionRepository($connection, $metadataFactory, $hydrator);

    $permissions = $repository->findByGroup('blog');

    expect($permissions)->toHaveCount(2)
        ->and($permissions[0])->toBeInstanceOf(Permission::class)
        ->and($permissions[0]->group)->toBe('blog')
        ->and($queryHistory[0]['sql'])->toContain('`group` = ?')
        ->and($queryHistory[0]['bindings'])->toContain('blog');
});

it('syncs permissions from registry to database creating new and preserving existing', function (): void {
    $queryHistory = [];
    $callCount = 0;

    // Mock connection that simulates:
    // - First findByKey ('blog.posts.create') returns existing permission
    // - Second findByKey ('blog.posts.edit') returns empty (new permission)
    $connection = createPermissionSyncMockConnection($queryHistory, $callCount);

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $registry = new PermissionRegistry();
    $registry->register('blog.posts.create', 'Create Posts', 'blog');
    $registry->register('blog.posts.edit', 'Edit Posts', 'blog');

    $repository = new PermissionRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        null,
        $registry,
    );

    $repository->syncFromRegistry();

    // Should have queried for both permissions by key
    $findByKeyQueries = array_filter(
        $queryHistory,
        fn (array $entry): bool => str_contains($entry['sql'], 'SELECT') && str_contains($entry['sql'], 'key = ?'),
    );
    expect(count($findByKeyQueries))->toBe(2);

    // Should have inserted only the new permission (blog.posts.edit)
    $insertQueries = array_filter(
        $queryHistory,
        fn (array $entry): bool => str_contains($entry['sql'], 'INSERT'),
    );
    expect(count($insertQueries))->toBe(1);
});

// Helper functions

function createPermissionMockConnection(
    array $queryResult = [],
): ConnectionInterface {
    return createPermissionMockConnectionWithHistory($queryResult, $unused);
}

/**
 * @param array<array<string, mixed>> $queryResult
 * @param array<array{sql: string, bindings: array<mixed>}>|null $queryHistory
 */
function createPermissionMockConnectionWithHistory(
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

/**
 * Creates a mock connection for syncFromRegistry tests.
 *
 * First findByKey query returns an existing permission (blog.posts.create).
 * Second findByKey query returns empty (blog.posts.edit is new).
 *
 * @param array<array{sql: string, bindings: array<mixed>}> $queryHistory
 * @param int $callCount
 */
function createPermissionSyncMockConnection(
    array &$queryHistory,
    int &$callCount,
): ConnectionInterface {
    return new class ($queryHistory, $callCount) implements ConnectionInterface
    {
        public function __construct(
            private array &$queryHistory,
            private int &$callCount,
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
            $this->queryHistory[] = ['sql' => $sql, 'bindings' => $bindings];

            // For findByKey lookups (SELECT with key = ?)
            if (str_contains($sql, 'key = ?')) {
                $this->callCount++;
                // First call: existing permission found
                if ($this->callCount === 1) {
                    return [
                        [
                            'id' => 1,
                            'key' => 'blog.posts.create',
                            'label' => 'Create Posts',
                            'group' => 'blog',
                            'created_at' => '2024-01-01 00:00:00',
                        ],
                    ];
                }

                // Second call: not found (new permission)
                return [];
            }

            return [];
        }

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
            return 2;
        }
    };
}
