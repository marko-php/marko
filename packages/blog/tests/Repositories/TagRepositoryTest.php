<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Repositories;

use Marko\Blog\Entity\Tag;
use Marko\Blog\Events\Tag\TagCreated;
use Marko\Blog\Events\Tag\TagDeleted;
use Marko\Blog\Events\Tag\TagUpdated;
use Marko\Blog\Exceptions\TagHasPostsException;
use Marko\Blog\Repositories\TagRepository;
use Marko\Blog\Repositories\TagRepositoryInterface;
use Marko\Blog\Services\SlugGenerator;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;
use Marko\Testing\Fake\FakeEventDispatcher;
use ReflectionClass;
use RuntimeException;

it('extends the Repository base class', function (): void {
    $reflection = new ReflectionClass(TagRepository::class);

    expect($reflection->isSubclassOf(Repository::class))->toBeTrue();
});

it('implements TagRepositoryInterface', function (): void {
    $connection = createMockTagConnection([]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    expect($repository)->toBeInstanceOf(TagRepositoryInterface::class);
});

it('defines ENTITY_CLASS constant pointing to Tag entity', function (): void {
    $reflection = new ReflectionClass(TagRepository::class);

    expect($reflection->hasConstant('ENTITY_CLASS'))->toBeTrue()
        ->and($reflection->getConstant('ENTITY_CLASS'))->toBe(Tag::class);
});

it('finds tag by id', function (): void {
    $connection = createMockTagConnection([
        [
            'id' => 1,
            'name' => 'PHP',
            'slug' => 'php',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $tag = $repository->find(1);

    expect($tag)->toBeInstanceOf(Tag::class)
        ->and($tag->id)->toBe(1)
        ->and($tag->name)->toBe('PHP')
        ->and($tag->slug)->toBe('php');
});

it('finds tag by slug', function (): void {
    $connection = createMockTagConnection([
        [
            'id' => 1,
            'name' => 'Laravel',
            'slug' => 'laravel',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $tag = $repository->findBySlug('laravel');

    expect($tag)->toBeInstanceOf(Tag::class)
        ->and($tag->slug)->toBe('laravel')
        ->and($tag->name)->toBe('Laravel');
});

it('returns all tags', function (): void {
    $connection = createMockTagConnection([
        [
            'id' => 1,
            'name' => 'PHP',
            'slug' => 'php',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
        [
            'id' => 2,
            'name' => 'JavaScript',
            'slug' => 'javascript',
            'created_at' => '2024-01-02 00:00:00',
            'updated_at' => '2024-01-02 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $tags = $repository->findAll();

    expect($tags)->toHaveCount(2)
        ->and($tags[0])->toBeInstanceOf(Tag::class)
        ->and($tags[0]->name)->toBe('PHP')
        ->and($tags[1]->name)->toBe('JavaScript');
});

it('finds tags by partial name match', function (): void {
    $connection = createMockTagConnection([
        [
            'id' => 1,
            'name' => 'PHP Development',
            'slug' => 'php-development',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
        [
            'id' => 2,
            'name' => 'PHP Frameworks',
            'slug' => 'php-frameworks',
            'created_at' => '2024-01-02 00:00:00',
            'updated_at' => '2024-01-02 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $tags = $repository->findByNameLike('PHP');

    expect($tags)->toHaveCount(2)
        ->and($tags[0]->name)->toBe('PHP Development')
        ->and($tags[1]->name)->toBe('PHP Frameworks');
});

it('checks if slug is unique via isSlugUnique method', function (): void {
    $existingSlug = 'php';

    $connection = new readonly class ($existingSlug) implements ConnectionInterface
    {
        public function __construct(
            private string $existingSlug,
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
            // Return result based on whether slug matches existing
            if (isset($bindings[0]) && $bindings[0] === $this->existingSlug) {
                // If excludeId is provided and matches, return empty (slug is unique for this entity)
                if (isset($bindings[1]) && $bindings[1] === 1) {
                    return [];
                }

                return [
                    [
                        'id' => 1,
                        'name' => 'PHP',
                        'slug' => 'php',
                        'created_at' => '2024-01-01 00:00:00',
                        'updated_at' => '2024-01-01 00:00:00',
                    ],
                ];
            }

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
            return 1;
        }
    };

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    // Existing slug should not be unique
    expect($repository->isSlugUnique('php'))->toBeFalse();

    // New slug should be unique
    expect($repository->isSlugUnique('new-tag'))->toBeTrue();

    // Existing slug should be unique when excludeId matches the entity that has it
    expect($repository->isSlugUnique('php', excludeId: 1))->toBeTrue();
});

it('prevents deletion when tag has posts', function (): void {
    $connection = new readonly class () implements ConnectionInterface
    {
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
            // Check if this is a count query for post_tags
            if (str_contains($sql, 'COUNT') && str_contains($sql, 'post_tags')) {
                return [['count' => 2]]; // Tag has 2 posts
            }

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
            return 1;
        }
    };

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $tag = new Tag();
    $tag->id = 1;
    $tag->name = 'PHP';
    $tag->slug = 'php';

    expect(fn () => $repository->delete($tag))->toThrow(
        TagHasPostsException::class,
        "Cannot delete tag 'PHP' because it has 2 associated posts",
    );
});

it('allows deletion when tag has no posts', function (): void {
    $deleteExecuted = new class ()
    {
        public bool $wasExecuted = false;

        public ?int $deletedId = null;
    };

    $connection = new class ($deleteExecuted) implements ConnectionInterface
    {
        public function __construct(
            private object $deleteExecuted,
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
            // Check if this is a count query for post_tags
            if (str_contains($sql, 'COUNT') && str_contains($sql, 'post_tags')) {
                return [['count' => 0]]; // Tag has no posts
            }

            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            if (str_contains($sql, 'DELETE')) {
                $this->deleteExecuted->wasExecuted = true;
                $this->deleteExecuted->deletedId = $bindings[0] ?? null;
            }

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

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $tag = new Tag();
    $tag->id = 1;
    $tag->name = 'PHP';
    $tag->slug = 'php';

    $repository->delete($tag);

    expect($deleteExecuted->wasExecuted)->toBeTrue()
        ->and($deleteExecuted->deletedId)->toBe(1);
});

it('auto-generates slug from name using SlugGenerator when saving new tag', function (): void {
    $connection = new readonly class () implements ConnectionInterface
    {
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
            // For uniqueness check, return empty (slug is unique)
            if (str_contains($sql, 'slug')) {
                return [];
            }

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
            return 1;
        }
    };

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $tag = new Tag();
    $tag->name = 'PHP Development';

    $repository->save($tag);

    // The slug should have been auto-generated
    expect($tag->slug)->toBe('php-development');
});

it('constructs with SlugGeneratorInterface plus parent params forwarded', function (): void {
    $connection = createMockTagConnection([]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();
    $queryBuilderFactory = fn () => null;
    $dispatcher = new FakeEventDispatcher();

    $repository = new TagRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $slugGenerator,
        $queryBuilderFactory,
        $dispatcher,
    );

    $reflection = new ReflectionClass($repository);

    // SlugGeneratorInterface is promoted (private) in TagRepository
    $slugProp = $reflection->getProperty('slugGenerator');
    expect($slugProp->getDeclaringClass()->getName())->toBe(TagRepository::class);

    // eventDispatcher is inherited from parent (not declared in TagRepository)
    $dispatcherProp = $reflection->getProperty('eventDispatcher');
    expect($dispatcherProp->getDeclaringClass()->getName())->toBe(Repository::class);

    // queryBuilderFactory is inherited from parent
    $qbfProp = $reflection->getProperty('queryBuilderFactory');
    expect($qbfProp->getDeclaringClass()->getName())->toBe(Repository::class);

    expect($repository)->toBeInstanceOf(TagRepository::class);
});

it('auto-generates slug on save', function (): void {
    $connection = createTagSaveConnection(isNew: true);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $tag = new Tag();
    $tag->name = 'PHP Framework';

    $repository->save($tag);

    expect($tag->slug)->toBe('php-framework');
});

it('dispatches TagCreated event on new tag', function (): void {
    $dispatcher = new FakeEventDispatcher();
    $connection = createTagSaveConnection(isNew: true);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $slugGenerator,
        null,
        $dispatcher,
    );

    $tag = new Tag();
    $tag->name = 'PHP';
    $tag->slug = 'php';

    $repository->save($tag);

    // Parent dispatches EntityCreating + EntityCreated; TagRepository adds TagCreated
    expect($dispatcher->dispatched)->toHaveCount(3)
        ->and($dispatcher->dispatched[2])->toBeInstanceOf(TagCreated::class);
});

it('dispatches TagUpdated event on existing tag', function (): void {
    $dispatcher = new FakeEventDispatcher();
    $connection = createTagSaveConnection(isNew: false);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $slugGenerator,
        null,
        $dispatcher,
    );

    $tag = new Tag();
    $tag->id = 1;
    $tag->name = 'PHP Updated';
    $tag->slug = 'php-updated';

    $repository->save($tag);

    // Parent dispatches EntityUpdating + EntityUpdated; TagRepository adds TagUpdated
    expect($dispatcher->dispatched)->toHaveCount(3)
        ->and($dispatcher->dispatched[2])->toBeInstanceOf(TagUpdated::class);
});

it('dispatches TagDeleted event on delete', function (): void {
    $dispatcher = new FakeEventDispatcher();
    $connection = createTagDeleteConnection(hasAssociatedPosts: false);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $slugGenerator,
        null,
        $dispatcher,
    );

    $tag = new Tag();
    $tag->id = 1;
    $tag->name = 'PHP';
    $tag->slug = 'php';

    $repository->delete($tag);

    // Parent dispatches EntityDeleting + EntityDeleted; TagRepository adds TagDeleted
    expect($dispatcher->dispatched)->toHaveCount(3)
        ->and($dispatcher->dispatched[2])->toBeInstanceOf(TagDeleted::class);
});

it('throws TagHasPostsException when deleting tag with posts', function (): void {
    $connection = createTagDeleteConnection(hasAssociatedPosts: true);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $tag = new Tag();
    $tag->id = 1;
    $tag->name = 'PHP';
    $tag->slug = 'php';

    expect(fn () => $repository->delete($tag))->toThrow(TagHasPostsException::class);
});

// Helper function to create mock connection for save tests
function createTagSaveConnection(bool $isNew): ConnectionInterface
{
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
            return $this->isNew ? 1 : 0;
        }
    };
}

// Helper function to create mock connection for delete tests
function createTagDeleteConnection(bool $hasAssociatedPosts): ConnectionInterface
{
    return new class ($hasAssociatedPosts) implements ConnectionInterface
    {
        public function __construct(
            private readonly bool $hasAssociatedPosts,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(string $sql, array $bindings = []): array
        {
            if (str_contains($sql, 'COUNT') && str_contains($sql, 'post_tags')) {
                return [['count' => $this->hasAssociatedPosts ? 1 : 0]];
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

// Helper function to create mock connection
function createMockTagConnection(
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
