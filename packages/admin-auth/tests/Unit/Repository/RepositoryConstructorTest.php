<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Repository;

use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Events\AdminUserCreated;
use Marko\AdminAuth\Events\AdminUserUpdated;
use Marko\AdminAuth\Events\RoleCreated;
use Marko\AdminAuth\Events\RoleDeleted;
use Marko\AdminAuth\Events\RoleUpdated;
use Marko\AdminAuth\Repository\AdminUserRepository;
use Marko\AdminAuth\Repository\RoleRepository;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Events\EntityCreated;
use Marko\Database\Events\EntityCreating;
use Marko\Database\Events\EntityUpdated;
use Marko\Database\Events\EntityUpdating;
use Marko\Testing\Fake\FakeEventDispatcher;
use RuntimeException;

it('constructs AdminUserRepository without explicit EventDispatcherInterface', function (): void {
    $connection = createConstructorMockConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AdminUserRepository($connection, $metadataFactory, $hydrator);

    expect($repository)->toBeInstanceOf(AdminUserRepository::class);
});

it('dispatches lifecycle events and AdminUserCreated domain event on new user save', function (): void {
    $eventDispatcher = new FakeEventDispatcher();
    $connection = createConstructorMockConnection(lastInsertId: 1);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AdminUserRepository($connection, $metadataFactory, $hydrator, null, $eventDispatcher);

    $user = new AdminUser();
    $user->email = 'admin@example.com';
    $user->password = 'hashed_password';
    $user->name = 'Admin User';

    $repository->save($user);

    $classes = array_map(fn (object $e): string => $e::class, $eventDispatcher->dispatched);

    expect($classes)->toContain(EntityCreating::class)
        ->and($classes)->toContain(EntityCreated::class)
        ->and($classes)->toContain(AdminUserCreated::class);
});

it('dispatches lifecycle events and AdminUserUpdated domain event on existing user save', function (): void {
    $eventDispatcher = new FakeEventDispatcher();
    $connection = createConstructorMockConnection(lastInsertId: 0);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AdminUserRepository($connection, $metadataFactory, $hydrator, null, $eventDispatcher);

    $user = new AdminUser();
    $user->id = 1;
    $user->email = 'admin@example.com';
    $user->password = 'hashed_password';
    $user->name = 'Admin Updated';

    $repository->save($user);

    $classes = array_map(fn (object $e): string => $e::class, $eventDispatcher->dispatched);

    expect($classes)->toContain(EntityUpdating::class)
        ->and($classes)->toContain(EntityUpdated::class)
        ->and($classes)->toContain(AdminUserUpdated::class);
});

it('constructs RoleRepository without explicit EventDispatcherInterface', function (): void {
    $connection = createConstructorMockConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator);

    expect($repository)->toBeInstanceOf(RoleRepository::class);
});

it('dispatches lifecycle events and RoleCreated domain event on new role save', function (): void {
    $eventDispatcher = new FakeEventDispatcher();
    $connection = createConstructorMockConnection(lastInsertId: 1);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator, null, $eventDispatcher);

    $role = new Role();
    $role->name = 'Editor';
    $role->slug = 'editor';

    $repository->save($role);

    $classes = array_map(fn (object $e): string => $e::class, $eventDispatcher->dispatched);

    expect($classes)->toContain(EntityCreating::class)
        ->and($classes)->toContain(EntityCreated::class)
        ->and($classes)->toContain(RoleCreated::class);
});

it('dispatches lifecycle events and RoleUpdated domain event on existing role save', function (): void {
    $eventDispatcher = new FakeEventDispatcher();
    $connection = createConstructorMockConnection(lastInsertId: 0);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator, null, $eventDispatcher);

    $role = new Role();
    $role->id = 1;
    $role->name = 'Editor Updated';
    $role->slug = 'editor-updated';

    $repository->save($role);

    $classes = array_map(fn (object $e): string => $e::class, $eventDispatcher->dispatched);

    expect($classes)->toContain(EntityUpdating::class)
        ->and($classes)->toContain(EntityUpdated::class)
        ->and($classes)->toContain(RoleUpdated::class);
});

it('dispatches RoleDeleted event on role delete', function (): void {
    $eventDispatcher = new FakeEventDispatcher();
    $connection = createConstructorMockConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository($connection, $metadataFactory, $hydrator, null, $eventDispatcher);

    $role = new Role();
    $role->id = 1;
    $role->name = 'Editor';
    $role->slug = 'editor';

    $repository->delete($role);

    $classes = array_map(fn (object $e): string => $e::class, $eventDispatcher->dispatched);

    expect($classes)->toContain(RoleDeleted::class);
});

// Helper functions

function createConstructorMockConnection(
    int $lastInsertId = 1,
): ConnectionInterface {
    return new readonly class ($lastInsertId) implements ConnectionInterface
    {
        public function __construct(
            private int $lastInsertId,
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
            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            return 1;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return $this->lastInsertId;
        }
    };
}
