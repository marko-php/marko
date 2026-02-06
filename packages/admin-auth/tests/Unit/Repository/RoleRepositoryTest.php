<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Repository;

use Marko\AdminAuth\Entity\Permission;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Repository\RoleRepository;
use Marko\AdminAuth\Repository\RoleRepositoryInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
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

it('syncs permissions for a role via syncPermissions', function (): void {
    $queryHistory = [];
    $connection = createRoleMockConnectionWithHistory(
        [],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator);

    $repository->syncPermissions(1, [10, 20, 30]);

    // First query should be DELETE of existing permissions
    expect($queryHistory[0]['sql'])->toContain('DELETE FROM role_permissions')
        ->and($queryHistory[0]['sql'])->toContain('role_id = ?')
        ->and($queryHistory[0]['bindings'])->toBe([1]);

    // Next 3 queries should be INSERTs
    expect($queryHistory[1]['sql'])->toContain('INSERT INTO role_permissions')
        ->and($queryHistory[1]['bindings'])->toBe([1, 10])
        ->and($queryHistory[2]['bindings'])->toBe([1, 20])
        ->and($queryHistory[3]['bindings'])->toBe([1, 30]);
});

// Helper functions

function createRoleMockConnection(
    array $queryResult = [],
): ConnectionInterface {
    return createRoleMockConnectionWithHistory($queryResult, $unused);
}

/**
 * @param array<array<string, mixed>> $queryResult
 * @param array<array{sql: string, bindings: array<mixed>}>|null $queryHistory
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
