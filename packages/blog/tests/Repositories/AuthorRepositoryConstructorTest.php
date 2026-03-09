<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Repositories;

use DateTimeImmutable;
use Marko\Blog\Entity\Author;
use Marko\Blog\Events\Author\AuthorCreated;
use Marko\Blog\Events\Author\AuthorDeleted;
use Marko\Blog\Events\Author\AuthorUpdated;
use Marko\Blog\Exceptions\AuthorHasPostsException;
use Marko\Blog\Repositories\AuthorRepository;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Events\EntityCreated;
use Marko\Database\Events\EntityCreating;
use Marko\Database\Events\EntityUpdated;
use Marko\Database\Events\EntityUpdating;
use Marko\Testing\Fake\FakeEventDispatcher;
use ReflectionClass;
use RuntimeException;

it('constructs without explicit EventDispatcherInterface parameter', function (): void {
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $connection = makeAuthorConstructorTestConnection();

    $repository = new AuthorRepository($connection, $metadataFactory, $hydrator);

    $reflection = new ReflectionClass($repository);
    expect($reflection->getConstructor()->getDeclaringClass()->getName())->not->toBe(AuthorRepository::class);
});

it('dispatches EntityCreating and EntityCreated lifecycle events via parent on new author save', function (): void {
    $dispatcher = new FakeEventDispatcher();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $connection = makeAuthorConstructorTestConnection(executeCallback: fn () => 1);

    $repository = new AuthorRepository($connection, $metadataFactory, $hydrator, eventDispatcher: $dispatcher);

    $author = new Author();
    $author->name = 'New Author';
    $author->email = 'new@example.com';
    $author->slug = 'new-author';

    $repository->save($author);

    $types = array_map(fn ($e) => $e::class, $dispatcher->dispatched);

    expect($types)->toContain(EntityCreating::class)
        ->and($types)->toContain(EntityCreated::class);
});

it('dispatches AuthorCreated domain event on new author save', function (): void {
    $dispatcher = new FakeEventDispatcher();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $connection = makeAuthorConstructorTestConnection(executeCallback: fn () => 1);

    $repository = new AuthorRepository($connection, $metadataFactory, $hydrator, eventDispatcher: $dispatcher);

    $author = new Author();
    $author->name = 'New Author';
    $author->email = 'new@example.com';
    $author->slug = 'new-author';

    $repository->save($author);

    $types = array_map(fn ($e) => $e::class, $dispatcher->dispatched);

    expect($types)->toContain(AuthorCreated::class);
});

it('dispatches EntityUpdating and EntityUpdated lifecycle events via parent on existing author save', function (): void {
    $dispatcher = new FakeEventDispatcher();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $authorData = [
        'id' => 1,
        'name' => 'Existing Author',
        'email' => 'existing@example.com',
        'bio' => null,
        'slug' => 'existing-author',
        'created_at' => '2024-01-01 00:00:00',
        'updated_at' => '2024-01-01 00:00:00',
    ];

    $connection = makeAuthorConstructorTestConnection(
        queryCallback: fn () => [$authorData],
        executeCallback: fn () => 1,
    );

    $repository = new AuthorRepository($connection, $metadataFactory, $hydrator, eventDispatcher: $dispatcher);

    $author = $repository->find(1);
    $author->name = 'Updated Name';

    $repository->save($author);

    $types = array_map(fn ($e) => $e::class, $dispatcher->dispatched);

    expect($types)->toContain(EntityUpdating::class)
        ->and($types)->toContain(EntityUpdated::class);
});

it('dispatches AuthorUpdated domain event on existing author save', function (): void {
    $dispatcher = new FakeEventDispatcher();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $authorData = [
        'id' => 1,
        'name' => 'Existing Author',
        'email' => 'existing@example.com',
        'bio' => null,
        'slug' => 'existing-author',
        'created_at' => '2024-01-01 00:00:00',
        'updated_at' => '2024-01-01 00:00:00',
    ];

    $connection = makeAuthorConstructorTestConnection(
        queryCallback: fn () => [$authorData],
        executeCallback: fn () => 1,
    );

    $repository = new AuthorRepository($connection, $metadataFactory, $hydrator, eventDispatcher: $dispatcher);

    $author = $repository->find(1);
    $author->name = 'Updated Name';

    $repository->save($author);

    $types = array_map(fn ($e) => $e::class, $dispatcher->dispatched);

    expect($types)->toContain(AuthorUpdated::class);
});

it('dispatches AuthorDeleted event on delete', function (): void {
    $dispatcher = new FakeEventDispatcher();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $connection = makeAuthorConstructorTestConnection(
        queryCallback: fn (string $sql) => str_contains($sql, 'COUNT(*)') ? [['count' => 0]] : [],
        executeCallback: fn () => 1,
    );

    $repository = new AuthorRepository($connection, $metadataFactory, $hydrator, eventDispatcher: $dispatcher);

    $author = new Author();
    $author->id = 1;
    $author->name = 'To Delete';
    $author->email = 'delete@example.com';
    $author->slug = 'to-delete';

    $repository->delete($author);

    $types = array_map(fn ($e) => $e::class, $dispatcher->dispatched);

    expect($types)->toContain(AuthorDeleted::class);
});

it('throws AuthorHasPostsException when deleting author with posts', function (): void {
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $connection = makeAuthorConstructorTestConnection(
        queryCallback: fn (string $sql) => str_contains($sql, 'COUNT(*)') ? [['count' => 2]] : [],
    );

    $repository = new AuthorRepository($connection, $metadataFactory, $hydrator);

    $author = new Author();
    $author->id = 1;
    $author->name = 'Prolific Author';
    $author->email = 'prolific@example.com';
    $author->slug = 'prolific-author';

    $repository->delete($author);
})->throws(AuthorHasPostsException::class);

// Helper

function makeAuthorConstructorTestConnection(
    ?callable $queryCallback = null,
    ?callable $executeCallback = null,
): ConnectionInterface {
    $queryCallback ??= fn () => [];
    $executeCallback ??= fn () => 1;

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
