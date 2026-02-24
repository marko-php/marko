<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Repositories;

use Closure;
use Marko\Blog\Entity\Category;
use Marko\Blog\Repositories\CategoryRepository;
use Marko\Blog\Repositories\CategoryRepositoryInterface;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\Repository;
use ReflectionClass;
use RuntimeException;

it('extends the Repository base class', function (): void {
    $reflection = new ReflectionClass(CategoryRepository::class);

    expect($reflection->isSubclassOf(Repository::class))->toBeTrue();
});

it('implements CategoryRepositoryInterface', function (): void {
    $reflection = new ReflectionClass(CategoryRepository::class);

    expect($reflection->implementsInterface(CategoryRepositoryInterface::class))->toBeTrue();
});

it('defines ENTITY_CLASS constant pointing to Category entity', function (): void {
    $reflection = new ReflectionClass(CategoryRepository::class);

    expect($reflection->hasConstant('ENTITY_CLASS'))->toBeTrue()
        ->and($reflection->getConstant('ENTITY_CLASS'))->toBe(Category::class);
});

it('finds category by id', function (): void {
    $connection = createMockCategoryConnection([
        [
            'id' => 1,
            'name' => 'Technology',
            'slug' => 'technology',
            'parent_id' => null,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = createMockSlugGenerator();

    $repository = new CategoryRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $category = $repository->find(1);

    expect($category)->toBeInstanceOf(Category::class)
        ->and($category->id)->toBe(1)
        ->and($category->name)->toBe('Technology')
        ->and($category->slug)->toBe('technology');
});

it('finds category by slug', function (): void {
    $connection = createMockCategoryConnection([
        [
            'id' => 1,
            'name' => 'Programming',
            'slug' => 'programming',
            'parent_id' => null,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = createMockSlugGenerator();

    $repository = new CategoryRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $category = $repository->findBySlug('programming');

    expect($category)->toBeInstanceOf(Category::class)
        ->and($category->slug)->toBe('programming')
        ->and($category->name)->toBe('Programming');
});

it('auto-generates slug from name using SlugGenerator', function (): void {
    $capturedCheckers = new class ()
    {
        public array $checkers = [];
    };

    $slugGenerator = new class ($capturedCheckers) implements SlugGeneratorInterface
    {
        public function __construct(
            private object $capture,
        ) {}

        public function generate(
            string $title,
            ?Closure $uniquenessChecker = null,
        ): string {
            $this->capture->checkers[] = $uniquenessChecker;

            return strtolower(str_replace(' ', '-', $title));
        }
    };

    $connection = createMockCategoryConnection([]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new CategoryRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $category = new Category();
    $category->name = 'Web Development';

    $repository->save($category);

    expect($category->slug)->toBe('web-development')
        ->and($capturedCheckers->checkers)->toHaveCount(1)
        ->and($capturedCheckers->checkers[0])->toBeInstanceOf(Closure::class);
});

it('allows manual slug override', function (): void {
    $slugGeneratorCalled = new class ()
    {
        public bool $called = false;
    };

    $slugGenerator = new class ($slugGeneratorCalled) implements SlugGeneratorInterface
    {
        public function __construct(
            private object $flag,
        ) {}

        public function generate(
            string $title,
            ?Closure $uniquenessChecker = null,
        ): string {
            $this->flag->called = true;

            return strtolower(str_replace(' ', '-', $title));
        }
    };

    $connection = createMockCategoryConnection([]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new CategoryRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $category = new Category();
    $category->name = 'Web Development';
    $category->slug = 'custom-slug';

    $repository->save($category);

    expect($category->slug)->toBe('custom-slug')
        ->and($slugGeneratorCalled->called)->toBeFalse();
});

it('ensures slug uniqueness within categories table via isSlugUnique', function (): void {
    $queryResults = [
        // First query: slug exists
        [['id' => 1, 'slug' => 'existing-slug']],
        // Second query: slug does not exist
        [],
    ];

    $connection = new class ($queryResults) implements ConnectionInterface
    {
        private int $queryIndex = 0;

        public function __construct(
            private array $queryResults,
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
            return $this->queryResults[$this->queryIndex++] ?? [];
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
    $slugGenerator = createMockSlugGenerator();

    $repository = new CategoryRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    expect($repository->isSlugUnique('existing-slug'))->toBeFalse()
        ->and($repository->isSlugUnique('new-slug'))->toBeTrue();
});

it('checks slug uniqueness excluding specific id', function (): void {
    $capturedBindings = new class ()
    {
        public array $bindings = [];
    };

    $connection = new class ($capturedBindings) implements ConnectionInterface
    {
        public function __construct(
            private object $capture,
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
            $this->capture->bindings[] = $bindings;

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
    $slugGenerator = createMockSlugGenerator();

    $repository = new CategoryRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $repository->isSlugUnique('test-slug', 5);

    expect($capturedBindings->bindings)->toHaveCount(1)
        ->and($capturedBindings->bindings[0])->toContain('test-slug')
        ->and($capturedBindings->bindings[0])->toContain(5);
});

it('returns child categories for a parent', function (): void {
    $connection = createMockCategoryConnection([
        [
            'id' => 2,
            'name' => 'PHP',
            'slug' => 'php',
            'parent_id' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
        [
            'id' => 3,
            'name' => 'JavaScript',
            'slug' => 'javascript',
            'parent_id' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = createMockSlugGenerator();

    $repository = new CategoryRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $parent = new Category();
    $parent->id = 1;
    $parent->name = 'Programming';
    $parent->slug = 'programming';

    $children = $repository->findChildren($parent);

    expect($children)->toHaveCount(2)
        ->and($children[0]->name)->toBe('PHP')
        ->and($children[1]->name)->toBe('JavaScript');
});

it('returns full path from root to category', function (): void {
    // When getPath is called with a category that has parentId=1,
    // it will call find(1) to get the parent category.
    // The mock should return the parent (Programming) for that query.
    $connection = createMockCategoryConnection([
        [
            'id' => 1,
            'name' => 'Programming',
            'slug' => 'programming',
            'parent_id' => null,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = createMockSlugGenerator();

    $repository = new CategoryRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $category = new Category();
    $category->id = 2;
    $category->name = 'PHP';
    $category->slug = 'php';
    $category->parentId = 1;

    $path = $repository->getPath($category);

    expect($path)->toHaveCount(2)
        ->and($path[0]->name)->toBe('Programming')
        ->and($path[1]->name)->toBe('PHP');
});

it('returns root categories with no parent', function (): void {
    $connection = createMockCategoryConnection([
        [
            'id' => 1,
            'name' => 'Technology',
            'slug' => 'technology',
            'parent_id' => null,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
        [
            'id' => 2,
            'name' => 'Lifestyle',
            'slug' => 'lifestyle',
            'parent_id' => null,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = createMockSlugGenerator();

    $repository = new CategoryRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $roots = $repository->findRoots();

    expect($roots)->toHaveCount(2)
        ->and($roots[0]->name)->toBe('Technology')
        ->and($roots[1]->name)->toBe('Lifestyle');
});

it('prevents deletion when category has posts', function (): void {
    $connection = new class () implements ConnectionInterface
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
            // Return count of posts with this category
            if (str_contains($sql, 'post_categories')) {
                return [['count' => 3]];
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
    $slugGenerator = createMockSlugGenerator();

    $repository = new CategoryRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $category = new Category();
    $category->id = 1;
    $category->name = 'Technology';
    $category->slug = 'technology';

    expect(fn () => $repository->delete($category))
        ->toThrow(RepositoryException::class, 'Cannot delete category with associated posts');
});

it('prevents deletion when category has children', function (): void {
    $connection = new class () implements ConnectionInterface
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
            // No posts, but has children
            if (str_contains($sql, 'post_categories')) {
                return [['count' => 0]];
            }
            if (str_contains($sql, 'parent_id')) {
                return [['count' => 2]];
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
    $slugGenerator = createMockSlugGenerator();

    $repository = new CategoryRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $category = new Category();
    $category->id = 1;
    $category->name = 'Technology';
    $category->slug = 'technology';

    expect(fn () => $repository->delete($category))
        ->toThrow(RepositoryException::class, 'Cannot delete category with child categories');
});

it('has created_at timestamp', function (): void {
    $connection = createMockCategoryConnection([
        [
            'id' => 1,
            'name' => 'Test',
            'slug' => 'test',
            'parent_id' => null,
            'created_at' => '2024-03-15 14:30:00',
            'updated_at' => '2024-03-15 14:30:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = createMockSlugGenerator();

    $repository = new CategoryRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $category = $repository->find(1);

    expect($category->createdAt)->toBe('2024-03-15 14:30:00');
});

it('has updated_at timestamp', function (): void {
    $connection = createMockCategoryConnection([
        [
            'id' => 1,
            'name' => 'Test',
            'slug' => 'test',
            'parent_id' => null,
            'created_at' => '2024-03-15 14:30:00',
            'updated_at' => '2024-03-20 09:15:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = createMockSlugGenerator();

    $repository = new CategoryRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $category = $repository->find(1);

    expect($category->updatedAt)->toBe('2024-03-20 09:15:00');
});

it('returns all descendant IDs for a category', function (): void {
    // Mock connection that returns children based on parent_id
    // Category 1 (Technology) has children 2 (Programming) and 3 (DevOps)
    // Category 2 (Programming) has children 4 (PHP) and 5 (JavaScript)
    // Category 4 (PHP) has no children
    $connection = createMockCategoryConnectionForDescendants([
        1 => [['id' => 2], ['id' => 3]],  // Technology -> Programming, DevOps
        2 => [['id' => 4], ['id' => 5]],  // Programming -> PHP, JavaScript
        3 => [],                           // DevOps -> (none)
        4 => [],                           // PHP -> (none)
        5 => [],                           // JavaScript -> (none)
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = createMockSlugGenerator();

    $repository = new CategoryRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $descendantIds = $repository->getDescendantIds(1);

    // Should return [2, 4, 5, 3] - all descendants of Technology
    expect($descendantIds)->toContain(2)
        ->and($descendantIds)->toContain(3)
        ->and($descendantIds)->toContain(4)
        ->and($descendantIds)->toContain(5)
        ->and($descendantIds)->toHaveCount(4);
});

it('returns empty array when category has no descendants', function (): void {
    $connection = createMockCategoryConnectionForDescendants([
        1 => [],  // Category has no children
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = createMockSlugGenerator();

    $repository = new CategoryRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $descendantIds = $repository->getDescendantIds(1);

    expect($descendantIds)->toBeEmpty();
});

it('returns only direct children IDs when no grandchildren exist', function (): void {
    $connection = createMockCategoryConnectionForDescendants([
        1 => [['id' => 2], ['id' => 3]],  // Parent has two children
        2 => [],                           // No grandchildren
        3 => [],                           // No grandchildren
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = createMockSlugGenerator();

    $repository = new CategoryRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $descendantIds = $repository->getDescendantIds(1);

    expect($descendantIds)->toHaveCount(2)
        ->and($descendantIds)->toContain(2)
        ->and($descendantIds)->toContain(3);
});

// Helper functions

function createMockCategoryConnection(
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

function createMockSlugGenerator(): SlugGeneratorInterface
{
    return new class () implements SlugGeneratorInterface
    {
        public function generate(
            string $title,
            ?Closure $uniquenessChecker = null,
        ): string {
            return strtolower(str_replace(' ', '-', $title));
        }
    };
}

/**
 * Creates a mock connection for testing getDescendantIds.
 * Takes a map of parent_id => array of child rows.
 *
 * @param array<int, array<array{id: int}>> $childrenByParentId
 */
function createMockCategoryConnectionForDescendants(
    array $childrenByParentId,
): ConnectionInterface {
    return new class ($childrenByParentId) implements ConnectionInterface
    {
        public function __construct(
            private array $childrenByParentId,
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
            // Extract parent_id from bindings for descendant queries
            if (str_contains($sql, 'parent_id') && !empty($bindings)) {
                $parentId = $bindings[0];

                return $this->childrenByParentId[$parentId] ?? [];
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
}
