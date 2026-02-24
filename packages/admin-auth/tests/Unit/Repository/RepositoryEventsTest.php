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
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use RuntimeException;

it('dispatches RoleCreated event when role is created', function (): void {
    $dispatchedEvents = [];

    $eventDispatcher = createEventDispatcher($dispatchedEvents);
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

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(RoleCreated::class)
        ->and($dispatchedEvents[0]->getRole())->toBeInstanceOf(RoleInterface::class)
        ->and($dispatchedEvents[0]->getRole()->getName())->toBe('Editor');
});

it('dispatches RoleUpdated event when role is modified', function (): void {
    $dispatchedEvents = [];

    $eventDispatcher = createEventDispatcher($dispatchedEvents);
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

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(RoleUpdated::class)
        ->and($dispatchedEvents[0]->getRole()->getName())->toBe('Editor Updated');
});

it('dispatches RoleDeleted event when role is removed', function (): void {
    $dispatchedEvents = [];

    $eventDispatcher = createEventDispatcher($dispatchedEvents);
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

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(RoleDeleted::class)
        ->and($dispatchedEvents[0]->getRole()->getName())->toBe('Editor');
});

it('dispatches AdminUserCreated event when user is created', function (): void {
    $dispatchedEvents = [];

    $eventDispatcher = createEventDispatcher($dispatchedEvents);
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

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(AdminUserCreated::class)
        ->and($dispatchedEvents[0]->getUser())->toBeInstanceOf(AdminUserInterface::class)
        ->and($dispatchedEvents[0]->getUser()->getAuthIdentifier())->toBe(1);
});

it('dispatches AdminUserUpdated event when user is modified', function (): void {
    $dispatchedEvents = [];

    $eventDispatcher = createEventDispatcher($dispatchedEvents);
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

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(AdminUserUpdated::class)
        ->and($dispatchedEvents[0]->getUser()->getAuthIdentifier())->toBe(1);
});

it('includes timestamp in all events', function (): void {
    $dispatchedEvents = [];

    $eventDispatcher = createEventDispatcher($dispatchedEvents);
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

    /** @var RoleCreated $event */
    $event = $dispatchedEvents[0];

    expect($event->getTimestamp())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($event->getTimestamp()->getTimestamp())->toBeGreaterThanOrEqual($beforeSave->getTimestamp())
        ->and($event->getTimestamp()->getTimestamp())->toBeLessThanOrEqual($afterSave->getTimestamp());
});

// Helper functions

function createEventDispatcher(
    array &$events,
): EventDispatcherInterface {
    return new class ($events) implements EventDispatcherInterface
    {
        public function __construct(
            private array &$events,
        ) {}

        public function dispatch(
            Event $event,
        ): void {
            $this->events[] = $event;
        }
    };
}

function createEventMockConnection(
    bool $isNew = true,
): ConnectionInterface {
    return new class ($isNew) implements ConnectionInterface
    {
        public function __construct(
            private readonly bool $isNew,
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
