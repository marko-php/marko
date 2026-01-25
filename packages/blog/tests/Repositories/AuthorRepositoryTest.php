<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Repositories;

use Marko\Blog\Entity\Author;
use Marko\Blog\Exceptions\AuthorHasPostsException;
use Marko\Blog\Repositories\AuthorRepository;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;
use ReflectionClass;
use RuntimeException;

it('extends the Repository base class', function (): void {
    $reflection = new ReflectionClass(AuthorRepository::class);

    expect($reflection->isSubclassOf(Repository::class))->toBeTrue();
});

it('defines ENTITY_CLASS constant pointing to Author entity', function (): void {
    $reflection = new ReflectionClass(AuthorRepository::class);

    expect($reflection->hasConstant('ENTITY_CLASS'))->toBeTrue()
        ->and($reflection->getConstant('ENTITY_CLASS'))->toBe(Author::class);
});

it('finds author by id', function (): void {
    $connection = createAuthorMockConnection([
        [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'bio' => 'A writer',
            'slug' => 'john-doe',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AuthorRepository($connection, $metadataFactory, $hydrator);

    $author = $repository->find(1);

    expect($author)->toBeInstanceOf(Author::class)
        ->and($author->id)->toBe(1)
        ->and($author->name)->toBe('John Doe')
        ->and($author->email)->toBe('john@example.com')
        ->and($author->slug)->toBe('john-doe');
});

it('finds author by slug', function (): void {
    $connection = createAuthorMockConnection([
        [
            'id' => 1,
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'bio' => 'An author',
            'slug' => 'jane-smith',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AuthorRepository($connection, $metadataFactory, $hydrator);

    $author = $repository->findBySlug('jane-smith');

    expect($author)->toBeInstanceOf(Author::class)
        ->and($author->slug)->toBe('jane-smith')
        ->and($author->name)->toBe('Jane Smith');
});

it('finds author by email', function (): void {
    $connection = createAuthorMockConnection([
        [
            'id' => 2,
            'name' => 'Bob Wilson',
            'email' => 'bob@example.com',
            'bio' => 'A blogger',
            'slug' => 'bob-wilson',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AuthorRepository($connection, $metadataFactory, $hydrator);

    $author = $repository->findByEmail('bob@example.com');

    expect($author)->toBeInstanceOf(Author::class)
        ->and($author->email)->toBe('bob@example.com')
        ->and($author->name)->toBe('Bob Wilson');
});

it('returns all authors', function (): void {
    $connection = createAuthorMockConnection([
        [
            'id' => 1,
            'name' => 'First Author',
            'email' => 'first@example.com',
            'bio' => 'First bio',
            'slug' => 'first-author',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
        [
            'id' => 2,
            'name' => 'Second Author',
            'email' => 'second@example.com',
            'bio' => 'Second bio',
            'slug' => 'second-author',
            'created_at' => '2024-01-02 00:00:00',
            'updated_at' => '2024-01-02 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AuthorRepository($connection, $metadataFactory, $hydrator);

    $authors = $repository->findAll();

    expect($authors)->toHaveCount(2)
        ->and($authors[0])->toBeInstanceOf(Author::class)
        ->and($authors[0]->name)->toBe('First Author')
        ->and($authors[1]->name)->toBe('Second Author');
});

it('checks if slug is unique via isSlugUnique method', function (): void {
    // Mock that returns an existing author with this slug
    $connection = createAuthorMockConnectionWithQueryCallback(function (string $sql, array $bindings): array {
        // Check if we're looking for 'existing-slug' in bindings
        if (in_array('existing-slug', $bindings, true)) {
            return [
                [
                    'id' => 1,
                    'name' => 'Existing Author',
                    'email' => 'existing@example.com',
                    'bio' => 'Bio',
                    'slug' => 'existing-slug',
                    'created_at' => '2024-01-01 00:00:00',
                    'updated_at' => '2024-01-01 00:00:00',
                ],
            ];
        }

        return [];
    });
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AuthorRepository($connection, $metadataFactory, $hydrator);

    // Slug exists - not unique
    expect($repository->isSlugUnique('existing-slug'))->toBeFalse();

    // Slug doesn't exist - unique
    expect($repository->isSlugUnique('new-slug'))->toBeTrue();
});

it('checks if slug is unique excluding a specific author id', function (): void {
    $connection = createAuthorMockConnectionWithQueryCallback(function (string $sql, array $bindings): array {
        // Check if we're looking for 'existing-slug'
        if (in_array('existing-slug', $bindings, true)) {
            // If SQL includes id != ? AND we're excluding id 1, return empty (slug is unique for that author)
            // The bindings will be ['existing-slug', excludeId]
            if (str_contains($sql, 'id != ?') && count($bindings) === 2 && $bindings[1] === 1) {
                return [];
            }

            // Otherwise return the existing author
            return [
                [
                    'id' => 1,
                    'name' => 'Existing Author',
                    'email' => 'existing@example.com',
                    'bio' => 'Bio',
                    'slug' => 'existing-slug',
                    'created_at' => '2024-01-01 00:00:00',
                    'updated_at' => '2024-01-01 00:00:00',
                ],
            ];
        }

        return [];
    });
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AuthorRepository($connection, $metadataFactory, $hydrator);

    // Slug exists but belongs to excluded id - should be unique
    expect($repository->isSlugUnique('existing-slug', excludeId: 1))->toBeTrue();

    // Slug exists and not excluding that id - not unique
    expect($repository->isSlugUnique('existing-slug', excludeId: 2))->toBeFalse();
});

it('prevents deletion when author has posts', function (): void {
    $connection = createAuthorMockConnectionWithQueryCallback(function (string $sql, array $bindings): array {
        // Return count of associated posts
        if (str_contains($sql, 'COUNT(*)')) {
            return [['count' => 3]];
        }

        return [];
    });
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new AuthorRepository($connection, $metadataFactory, $hydrator);

    $author = new Author();
    $author->id = 1;
    $author->name = 'Author With Posts';
    $author->email = 'author@example.com';
    $author->slug = 'author-with-posts';

    $repository->delete($author);
})->throws(
    AuthorHasPostsException::class,
    "Cannot delete author 'Author With Posts' because they have 3 associated posts"
);

// Helper function to create mock connection for Author tests

function createAuthorMockConnection(
    array $queryResult = [],
): ConnectionInterface {
    return new readonly class ($queryResult) implements ConnectionInterface
    {
        public function __construct(
            private array $queryResult,
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
            return $this->queryResult;
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
            return 1;
        }
    };
}

function createAuthorMockConnectionWithQueryCallback(
    callable $queryCallback,
): ConnectionInterface {
    return new readonly class ($queryCallback) implements ConnectionInterface
    {
        public function __construct(
            private mixed $queryCallback,
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
