<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Repositories;

use Marko\Blog\Entity\Tag;
use Marko\Blog\Exceptions\TagHasPostsException;
use Marko\Blog\Repositories\TagRepository;
use Marko\Blog\Repositories\TagRepositoryInterface;
use Marko\Blog\Services\SlugGenerator;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;
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
