<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Events\Author;

use DateTimeImmutable;
use Marko\Blog\Entity\Author;
use Marko\Blog\Entity\AuthorInterface;
use Marko\Blog\Events\Author\AuthorCreated;
use Marko\Blog\Events\Author\AuthorDeleted;
use Marko\Blog\Events\Author\AuthorUpdated;
use Marko\Blog\Repositories\AuthorRepository;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use ReflectionClass;
use RuntimeException;

it('dispatches AuthorCreated event when author is created', function (): void {
    $dispatchedEvents = [];
    $eventDispatcher = createMockEventDispatcher($dispatchedEvents);

    $connection = createEventTestMockConnection(
        queryCallback: fn () => [],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AuthorRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        eventDispatcher: $eventDispatcher,
    );

    $author = new Author();
    $author->name = 'John Doe';
    $author->email = 'john@example.com';
    $author->slug = 'john-doe';

    $repository->save($author);

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(AuthorCreated::class);
});

it('dispatches AuthorUpdated event when author is modified', function (): void {
    $dispatchedEvents = [];
    $eventDispatcher = createMockEventDispatcher($dispatchedEvents);

    // Return author data when queried (simulating an existing record)
    $authorData = [
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'bio' => null,
        'slug' => 'john-doe',
        'created_at' => '2024-01-01 00:00:00',
        'updated_at' => '2024-01-01 00:00:00',
    ];

    $connection = createEventTestMockConnection(
        queryCallback: fn () => [$authorData],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AuthorRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        eventDispatcher: $eventDispatcher,
    );

    // Fetch the author from "database" (this tracks original state)
    $author = $repository->find(1);

    // Modify the author
    $author->name = 'John Updated';

    $repository->save($author);

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(AuthorUpdated::class);
});

it('dispatches AuthorDeleted event when author is removed', function (): void {
    $dispatchedEvents = [];
    $eventDispatcher = createMockEventDispatcher($dispatchedEvents);

    $connection = createEventTestMockConnection(
        queryCallback: fn (string $sql) => str_contains($sql, 'COUNT(*)') ? [['count' => 0]] : [],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AuthorRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        eventDispatcher: $eventDispatcher,
    );

    $author = new Author();
    $author->id = 1;
    $author->name = 'John Doe';
    $author->email = 'john@example.com';
    $author->slug = 'john-doe';

    $repository->delete($author);

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(AuthorDeleted::class);
});

it('includes full author entity in event data', function (): void {
    $dispatchedEvents = [];
    $eventDispatcher = createMockEventDispatcher($dispatchedEvents);

    $connection = createEventTestMockConnection(
        queryCallback: fn () => [],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AuthorRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        eventDispatcher: $eventDispatcher,
    );

    $author = new Author();
    $author->name = 'Jane Doe';
    $author->email = 'jane@example.com';
    $author->bio = 'A writer';
    $author->slug = 'jane-doe';

    $repository->save($author);

    expect($dispatchedEvents)->toHaveCount(1);

    $event = $dispatchedEvents[0];
    $eventAuthor = $event->getAuthor();

    expect($eventAuthor)->toBeInstanceOf(AuthorInterface::class)
        ->and($eventAuthor->getName())->toBe('Jane Doe')
        ->and($eventAuthor->getEmail())->toBe('jane@example.com')
        ->and($eventAuthor->getBio())->toBe('A writer')
        ->and($eventAuthor->getSlug())->toBe('jane-doe');
});

it('includes timestamp in all events', function (): void {
    $dispatchedEvents = [];
    $eventDispatcher = createMockEventDispatcher($dispatchedEvents);

    $connection = createEventTestMockConnection(
        queryCallback: fn (string $sql) => str_contains($sql, 'COUNT(*)') ? [['count' => 0]] : [],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AuthorRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        eventDispatcher: $eventDispatcher,
    );

    // Test AuthorCreated event
    $author1 = new Author();
    $author1->name = 'Author One';
    $author1->email = 'one@example.com';
    $author1->slug = 'author-one';

    $beforeCreate = new DateTimeImmutable();
    $repository->save($author1);
    $afterCreate = new DateTimeImmutable();

    expect($dispatchedEvents[0]->getTimestamp())
        ->toBeInstanceOf(DateTimeImmutable::class)
        ->and($dispatchedEvents[0]->getTimestamp() >= $beforeCreate)->toBeTrue()
        ->and($dispatchedEvents[0]->getTimestamp() <= $afterCreate)->toBeTrue();

    // For the update test, we need to fetch an existing author first
    // Reset the connection to return author data for find(), then accept update
    $author2Data = [
        'id' => 2,
        'name' => 'Author Two',
        'email' => 'two@example.com',
        'bio' => null,
        'slug' => 'author-two',
        'created_at' => '2024-01-01 00:00:00',
        'updated_at' => '2024-01-01 00:00:00',
    ];

    $updateConnection = createEventTestMockConnection(
        queryCallback: fn () => [$author2Data],
        executeCallback: fn () => 1,
    );

    $updateRepository = new AuthorRepository(
        $updateConnection,
        $metadataFactory,
        $hydrator,
        eventDispatcher: $eventDispatcher,
    );

    $author2 = $updateRepository->find(2);
    $author2->name = 'Author Two Updated';

    $beforeUpdate = new DateTimeImmutable();
    $updateRepository->save($author2);
    $afterUpdate = new DateTimeImmutable();

    expect($dispatchedEvents[1]->getTimestamp())
        ->toBeInstanceOf(DateTimeImmutable::class)
        ->and($dispatchedEvents[1]->getTimestamp() >= $beforeUpdate)->toBeTrue()
        ->and($dispatchedEvents[1]->getTimestamp() <= $afterUpdate)->toBeTrue();

    // Test AuthorDeleted event
    $author3 = new Author();
    $author3->id = 3;
    $author3->name = 'Author Three';
    $author3->email = 'three@example.com';
    $author3->slug = 'author-three';

    $beforeDelete = new DateTimeImmutable();
    $repository->delete($author3);
    $afterDelete = new DateTimeImmutable();

    expect($dispatchedEvents[2]->getTimestamp())
        ->toBeInstanceOf(DateTimeImmutable::class)
        ->and($dispatchedEvents[2]->getTimestamp() >= $beforeDelete)->toBeTrue()
        ->and($dispatchedEvents[2]->getTimestamp() <= $afterDelete)->toBeTrue();
});

it('creates AuthorCreated event that extends Event base class', function (): void {
    $author = new Author();
    $author->id = 1;
    $author->name = 'Test Author';
    $author->email = 'test@example.com';
    $author->slug = 'test-author';

    $timestamp = new DateTimeImmutable();
    $event = new AuthorCreated($author, $timestamp);

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->getAuthor())->toBe($author)
        ->and($event->getTimestamp())->toBe($timestamp);
});

it('creates AuthorUpdated event that extends Event base class', function (): void {
    $author = new Author();
    $author->id = 1;
    $author->name = 'Test Author';
    $author->email = 'test@example.com';
    $author->slug = 'test-author';

    $timestamp = new DateTimeImmutable();
    $event = new AuthorUpdated($author, $timestamp);

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->getAuthor())->toBe($author)
        ->and($event->getTimestamp())->toBe($timestamp);
});

it('creates AuthorDeleted event that extends Event base class', function (): void {
    $author = new Author();
    $author->id = 1;
    $author->name = 'Test Author';
    $author->email = 'test@example.com';
    $author->slug = 'test-author';

    $timestamp = new DateTimeImmutable();
    $event = new AuthorDeleted($author, $timestamp);

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->getAuthor())->toBe($author)
        ->and($event->getTimestamp())->toBe($timestamp);
});

it('creates immutable event classes with readonly properties', function (): void {
    $createdReflection = new ReflectionClass(AuthorCreated::class);
    $updatedReflection = new ReflectionClass(AuthorUpdated::class);
    $deletedReflection = new ReflectionClass(AuthorDeleted::class);

    // Check that all event classes have readonly properties for their constructor params
    $createdProps = $createdReflection->getProperties();
    $createdReadonlyProps = array_filter(
        $createdProps,
        fn ($prop) => $prop->isReadOnly() && $prop->class === AuthorCreated::class,
    );

    $updatedProps = $updatedReflection->getProperties();
    $updatedReadonlyProps = array_filter(
        $updatedProps,
        fn ($prop) => $prop->isReadOnly() && $prop->class === AuthorUpdated::class,
    );

    $deletedProps = $deletedReflection->getProperties();
    $deletedReadonlyProps = array_filter(
        $deletedProps,
        fn ($prop) => $prop->isReadOnly() && $prop->class === AuthorDeleted::class,
    );

    // Each event class should have 2 readonly properties (author, timestamp)
    expect(count($createdReadonlyProps))->toBe(2)
        ->and(count($updatedReadonlyProps))->toBe(2)
        ->and(count($deletedReadonlyProps))->toBe(2);
});

// Helper functions for event tests

function createMockEventDispatcher(
    array &$dispatchedEvents,
): EventDispatcherInterface {
    return new class ($dispatchedEvents) implements EventDispatcherInterface
    {
        public function __construct(
            private array &$dispatchedEvents,
        ) {}

        public function dispatch(
            Event $event,
        ): void {
            $this->dispatchedEvents[] = $event;
        }
    };
}

function createEventTestMockConnection(
    callable $queryCallback,
    callable $executeCallback,
): ConnectionInterface {
    return new readonly class ($queryCallback, $executeCallback) implements ConnectionInterface
    {
        public function __construct(
            private mixed $queryCallback,
            private mixed $executeCallback,
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
            return ($this->queryCallback)($sql, $bindings);
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            return ($this->executeCallback)($sql, $bindings);
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
