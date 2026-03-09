<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Repository;

use DateTimeImmutable;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\AdminUserInterface;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Entity\RoleInterface;
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
use Marko\Database\Events\EntityDeleted;
use Marko\Database\Events\EntityDeleting;
use Marko\Database\Events\EntityUpdated;
use Marko\Database\Events\EntityUpdating;
use Marko\Testing\Fake\FakeEventDispatcher;
use RuntimeException;

it('dispatches RoleCreated event when role is created', function (): void {
    $eventDispatcher = new FakeEventDispatcher();
    $connection = createEventMockConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        null,
        $eventDispatcher,
    );

    $role = new Role();
    $role->name = 'Editor';
    $role->slug = 'editor';

    $repository->save($role);

    $classes = array_map(fn (object $e): string => $e::class, $eventDispatcher->dispatched);

    expect($classes)->toContain(EntityCreating::class)
        ->and($classes)->toContain(EntityCreated::class)
        ->and($classes)->toContain(RoleCreated::class);

    $domainEvent = $eventDispatcher->dispatched[array_search(RoleCreated::class, $classes)];

    expect($domainEvent)->toBeInstanceOf(RoleCreated::class)
        ->and($domainEvent->getRole())->toBeInstanceOf(RoleInterface::class)
        ->and($domainEvent->getRole()->getName())->toBe('Editor');
});

it('dispatches RoleUpdated event when role is modified', function (): void {
    $eventDispatcher = new FakeEventDispatcher();
    $connection = createEventMockConnection(isNew: false);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        null,
        $eventDispatcher,
    );

    $role = new Role();
    $role->id = 1;
    $role->name = 'Editor Updated';
    $role->slug = 'editor-updated';

    $repository->save($role);

    $classes = array_map(fn (object $e): string => $e::class, $eventDispatcher->dispatched);

    expect($classes)->toContain(EntityUpdating::class)
        ->and($classes)->toContain(EntityUpdated::class)
        ->and($classes)->toContain(RoleUpdated::class);

    $domainEvent = $eventDispatcher->dispatched[array_search(RoleUpdated::class, $classes)];

    expect($domainEvent->getRole()->getName())->toBe('Editor Updated');
});

it('dispatches RoleDeleted event when role is removed', function (): void {
    $eventDispatcher = new FakeEventDispatcher();
    $connection = createEventMockConnection(isNew: false);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        null,
        $eventDispatcher,
    );

    $role = new Role();
    $role->id = 1;
    $role->name = 'Editor';
    $role->slug = 'editor';

    $repository->delete($role);

    $classes = array_map(fn (object $e): string => $e::class, $eventDispatcher->dispatched);

    expect($classes)->toContain(EntityDeleting::class)
        ->and($classes)->toContain(EntityDeleted::class)
        ->and($classes)->toContain(RoleDeleted::class);

    $domainEvent = $eventDispatcher->dispatched[array_search(RoleDeleted::class, $classes)];

    expect($domainEvent->getRole()->getName())->toBe('Editor');
});

it('dispatches AdminUserCreated event when user is created', function (): void {
    $eventDispatcher = new FakeEventDispatcher();
    $connection = createEventMockConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AdminUserRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        null,
        $eventDispatcher,
    );

    $user = new AdminUser();
    $user->email = 'admin@example.com';
    $user->password = 'hashed_password';
    $user->name = 'Admin User';

    $repository->save($user);

    $classes = array_map(fn (object $e): string => $e::class, $eventDispatcher->dispatched);

    expect($classes)->toContain(EntityCreating::class)
        ->and($classes)->toContain(EntityCreated::class)
        ->and($classes)->toContain(AdminUserCreated::class);

    $domainEvent = $eventDispatcher->dispatched[array_search(AdminUserCreated::class, $classes)];

    expect($domainEvent)->toBeInstanceOf(AdminUserCreated::class)
        ->and($domainEvent->getUser())->toBeInstanceOf(AdminUserInterface::class)
        ->and($domainEvent->getUser()->getAuthIdentifier())->toBe(1);
});

it('dispatches AdminUserUpdated event when user is modified', function (): void {
    $eventDispatcher = new FakeEventDispatcher();
    $connection = createEventMockConnection(isNew: false);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AdminUserRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        null,
        $eventDispatcher,
    );

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

    $domainEvent = $eventDispatcher->dispatched[array_search(AdminUserUpdated::class, $classes)];

    expect($domainEvent->getUser()->getAuthIdentifier())->toBe(1);
});

it('includes timestamp in all events', function (): void {
    $eventDispatcher = new FakeEventDispatcher();
    $connection = createEventMockConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new RoleRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        null,
        $eventDispatcher,
    );

    $beforeSave = new DateTimeImmutable();

    $role = new Role();
    $role->name = 'Viewer';
    $role->slug = 'viewer';

    $repository->save($role);

    $afterSave = new DateTimeImmutable();

    $classes = array_map(fn (object $e): string => $e::class, $eventDispatcher->dispatched);
    $domainEvent = $eventDispatcher->dispatched[array_search(RoleCreated::class, $classes)];

    expect($domainEvent->getTimestamp())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($domainEvent->getTimestamp()->getTimestamp())->toBeGreaterThanOrEqual($beforeSave->getTimestamp())
        ->and($domainEvent->getTimestamp()->getTimestamp())->toBeLessThanOrEqual($afterSave->getTimestamp());
});

// Helper functions

function createEventMockConnection(
    bool $isNew = true,
): ConnectionInterface {
    return new readonly class ($isNew) implements ConnectionInterface
    {
        public function __construct(
            private bool $isNew,
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
            return $this->isNew ? 1 : 0;
        }
    };
}
