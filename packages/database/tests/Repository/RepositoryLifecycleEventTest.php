<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Repository;

use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Events\EntityCreated;
use Marko\Database\Events\EntityCreating;
use Marko\Database\Events\EntityDeleted;
use Marko\Database\Events\EntityDeleting;
use Marko\Database\Events\EntityUpdated;
use Marko\Database\Events\EntityUpdating;
use Marko\Database\Repository\Repository;
use ReflectionClass;
use RuntimeException;

#[Table('lifecycle_test_items')]
class LifecycleTestItem extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $name;
}

class LifecycleTestRepository extends Repository
{
    protected const string ENTITY_CLASS = LifecycleTestItem::class;
}

/**
 * Fake event dispatcher for testing.
 */
class FakeDispatcher implements EventDispatcherInterface
{
    /** @var array<Event> */
    public array $dispatched = [];

    public function dispatch(Event $event): void
    {
        $this->dispatched[] = $event;
    }
}

function makeInsertConnection(): ConnectionInterface
{
    return new class () implements ConnectionInterface
    {
        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(string $sql, array $bindings = []): array
        {
            return [];
        }

        public function execute(string $sql, array $bindings = []): int
        {
            return 1;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 42;
        }
    };
}

function makeUpdateConnection(): ConnectionInterface
{
    return new class () implements ConnectionInterface
    {
        private bool $firstQuery = true;

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(string $sql, array $bindings = []): array
        {
            if ($this->firstQuery) {
                $this->firstQuery = false;

                return [
                    ['id' => 1, 'name' => 'Original'],
                ];
            }

            return [];
        }

        public function execute(string $sql, array $bindings = []): int
        {
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
    };
}

function makeDeleteConnection(): ConnectionInterface
{
    return new class () implements ConnectionInterface
    {
        private bool $firstQuery = true;

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(string $sql, array $bindings = []): array
        {
            if ($this->firstQuery) {
                $this->firstQuery = false;

                return [
                    ['id' => 5, 'name' => 'ToDelete'],
                ];
            }

            return [];
        }

        public function execute(string $sql, array $bindings = []): int
        {
            return 1;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 0;
        }
    };
}

// Constructor tests

it('accepts optional EventDispatcherInterface as fifth constructor parameter', function (): void {
    $connection = makeInsertConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $dispatcher = new FakeDispatcher();

    $repository = new LifecycleTestRepository($connection, $metadataFactory, $hydrator, null, $dispatcher);

    $reflection = new ReflectionClass($repository);
    $property = $reflection->getProperty('eventDispatcher');

    expect($property->getValue($repository))->toBe($dispatcher);
});

it('works without EventDispatcherInterface (null by default)', function (): void {
    $connection = makeInsertConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new LifecycleTestRepository($connection, $metadataFactory, $hydrator);

    $reflection = new ReflectionClass($repository);
    $property = $reflection->getProperty('eventDispatcher');

    expect($property->getValue($repository))->toBeNull();
});

// save() dispatch tests

it('dispatches EntityCreating before insert when dispatcher provided', function (): void {
    $connection = makeInsertConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $dispatcher = new FakeDispatcher();

    $repository = new LifecycleTestRepository($connection, $metadataFactory, $hydrator, null, $dispatcher);

    $item = new LifecycleTestItem();
    $item->name = 'New Item';

    $repository->save($item);

    $creating = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof EntityCreating);
    expect(count($creating))->toBe(1);
    $creatingEvent = array_values($creating)[0];
    expect($creatingEvent->entity)->toBe($item)
        ->and($creatingEvent->entityClass)->toBe(LifecycleTestItem::class);
});

it('dispatches EntityCreated after insert when dispatcher provided', function (): void {
    $connection = makeInsertConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $dispatcher = new FakeDispatcher();

    $repository = new LifecycleTestRepository($connection, $metadataFactory, $hydrator, null, $dispatcher);

    $item = new LifecycleTestItem();
    $item->name = 'New Item';

    $repository->save($item);

    $created = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof EntityCreated);
    expect(count($created))->toBe(1);
    $createdEvent = array_values($created)[0];
    expect($createdEvent->entity)->toBe($item)
        ->and($createdEvent->entityClass)->toBe(LifecycleTestItem::class);
});

it('dispatches EntityUpdating before update when dispatcher provided', function (): void {
    $connection = makeUpdateConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $dispatcher = new FakeDispatcher();

    $repository = new LifecycleTestRepository($connection, $metadataFactory, $hydrator, null, $dispatcher);

    $item = $repository->find(1);
    $item->name = 'Updated';

    $repository->save($item);

    $updating = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof EntityUpdating);
    expect(count($updating))->toBe(1);
    $updatingEvent = array_values($updating)[0];
    expect($updatingEvent->entity)->toBe($item)
        ->and($updatingEvent->entityClass)->toBe(LifecycleTestItem::class);
});

it('dispatches EntityUpdated after update when dispatcher provided', function (): void {
    $connection = makeUpdateConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $dispatcher = new FakeDispatcher();

    $repository = new LifecycleTestRepository($connection, $metadataFactory, $hydrator, null, $dispatcher);

    $item = $repository->find(1);
    $item->name = 'Updated';

    $repository->save($item);

    $updated = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof EntityUpdated);
    expect(count($updated))->toBe(1);
    $updatedEvent = array_values($updated)[0];
    expect($updatedEvent->entity)->toBe($item)
        ->and($updatedEvent->entityClass)->toBe(LifecycleTestItem::class);
});

it('does not dispatch events when no dispatcher is provided', function (): void {
    $connection = makeInsertConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    // No dispatcher passed - bare minimum: no error thrown
    $repository = new LifecycleTestRepository($connection, $metadataFactory, $hydrator);

    $item = new LifecycleTestItem();
    $item->name = 'No Dispatcher';

    // Should not throw
    $repository->save($item);

    expect(true)->toBeTrue();
});

// delete() dispatch tests

it('dispatches EntityDeleting before delete when dispatcher provided', function (): void {
    $connection = makeDeleteConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $dispatcher = new FakeDispatcher();

    $repository = new LifecycleTestRepository($connection, $metadataFactory, $hydrator, null, $dispatcher);

    $item = $repository->find(5);
    $repository->delete($item);

    $deleting = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof EntityDeleting);
    expect(count($deleting))->toBe(1);
    $deletingEvent = array_values($deleting)[0];
    expect($deletingEvent->entity)->toBe($item)
        ->and($deletingEvent->entityClass)->toBe(LifecycleTestItem::class);
});

it('dispatches EntityDeleted after delete when dispatcher provided', function (): void {
    $connection = makeDeleteConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $dispatcher = new FakeDispatcher();

    $repository = new LifecycleTestRepository($connection, $metadataFactory, $hydrator, null, $dispatcher);

    $item = $repository->find(5);
    $repository->delete($item);

    $deleted = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof EntityDeleted);
    expect(count($deleted))->toBe(1);
    $deletedEvent = array_values($deleted)[0];
    expect($deletedEvent->entity)->toBe($item)
        ->and($deletedEvent->entityClass)->toBe(LifecycleTestItem::class);
});

it('does not dispatch delete events when no dispatcher is provided', function (): void {
    $connection = makeDeleteConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new LifecycleTestRepository($connection, $metadataFactory, $hydrator);

    $item = $repository->find(5);

    // Should not throw
    $repository->delete($item);

    expect(true)->toBeTrue();
});
