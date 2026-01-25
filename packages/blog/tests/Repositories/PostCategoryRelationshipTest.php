<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Repositories;

use Closure;
use Marko\Blog\Entity\Category;
use Marko\Blog\Entity\Post;
use Marko\Blog\Repositories\CategoryRepository;
use Marko\Blog\Repositories\PostRepository;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use RuntimeException;

it('attaches category to post', function (): void {
    $executedQueries = new class ()
    {
        /** @var array<array{sql: string, bindings: array<mixed>}> */
        public array $queries = [];
    };

    $connection = createPostCategoryMockConnection([], $executedQueries);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);

    $repository->attachCategory(postId: 1, categoryId: 2);

    expect($executedQueries->queries)->toHaveCount(1)
        ->and($executedQueries->queries[0]['sql'])->toContain('INSERT INTO post_categories')
        ->and($executedQueries->queries[0]['sql'])->toContain('post_id')
        ->and($executedQueries->queries[0]['sql'])->toContain('category_id')
        ->and($executedQueries->queries[0]['bindings'])->toBe([1, 2]);
});

it('detaches category from post', function (): void {
    $executedQueries = new class ()
    {
        /** @var array<array{sql: string, bindings: array<mixed>}> */
        public array $queries = [];
    };

    $connection = createPostCategoryMockConnection([], $executedQueries);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);

    $repository->detachCategory(postId: 1, categoryId: 2);

    expect($executedQueries->queries)->toHaveCount(1)
        ->and($executedQueries->queries[0]['sql'])->toContain('DELETE FROM post_categories')
        ->and($executedQueries->queries[0]['sql'])->toContain('post_id = ?')
        ->and($executedQueries->queries[0]['sql'])->toContain('category_id = ?')
        ->and($executedQueries->queries[0]['bindings'])->toBe([1, 2]);
});

it('returns all categories for a post', function (): void {
    $executedQueries = new class ()
    {
        /** @var array<array{sql: string, bindings: array<mixed>}> */
        public array $queries = [];
    };

    $categoryData = [
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
            'name' => 'Programming',
            'slug' => 'programming',
            'parent_id' => null,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ];

    $connection = createPostCategoryMockConnection($categoryData, $executedQueries);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);

    $categories = $repository->getCategoriesForPost(postId: 1);

    expect($categories)->toHaveCount(2)
        ->and($categories[0])->toBeInstanceOf(Category::class)
        ->and($categories[0]->name)->toBe('Technology')
        ->and($categories[1]->name)->toBe('Programming')
        ->and($executedQueries->queries)->toHaveCount(1)
        ->and($executedQueries->queries[0]['sql'])->toContain('JOIN post_categories')
        ->and($executedQueries->queries[0]['bindings'])->toBe([1]);
});

it('returns all posts for a category', function (): void {
    $executedQueries = new class ()
    {
        /** @var array<array{sql: string, bindings: array<mixed>}> */
        public array $queries = [];
    };

    $postData = [
        [
            'id' => 1,
            'title' => 'First Post',
            'slug' => 'first-post',
            'content' => 'First content',
            'summary' => null,
            'status' => 'published',
            'author_id' => 1,
            'scheduled_at' => null,
            'published_at' => '2024-01-01 00:00:00',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
        [
            'id' => 2,
            'title' => 'Second Post',
            'slug' => 'second-post',
            'content' => 'Second content',
            'summary' => null,
            'status' => 'published',
            'author_id' => 1,
            'scheduled_at' => null,
            'published_at' => '2024-01-02 00:00:00',
            'created_at' => '2024-01-02 00:00:00',
            'updated_at' => '2024-01-02 00:00:00',
        ],
    ];

    $connection = createPostCategoryMockConnection($postData, $executedQueries);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = createTestSlugGenerator();

    $repository = new CategoryRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $slugGenerator,
    );

    $posts = $repository->getPostsForCategory(categoryId: 1);

    expect($posts)->toHaveCount(2)
        ->and($posts[0])->toBeInstanceOf(Post::class)
        ->and($posts[0]->title)->toBe('First Post')
        ->and($posts[1]->title)->toBe('Second Post')
        ->and($executedQueries->queries)->toHaveCount(1)
        ->and($executedQueries->queries[0]['sql'])->toContain('JOIN post_categories')
        ->and($executedQueries->queries[0]['bindings'])->toBe([1]);
});

it('syncs categories for a post replacing existing', function (): void {
    $executedQueries = new class ()
    {
        /** @var array<array{sql: string, bindings: array<mixed>}> */
        public array $queries = [];
    };

    $connection = createPostCategoryMockConnection([], $executedQueries);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);

    $repository->syncCategories(postId: 1, categoryIds: [2, 3, 4]);

    // First query should be DELETE to remove all existing
    expect($executedQueries->queries)->toHaveCount(4)
        ->and($executedQueries->queries[0]['sql'])->toContain('DELETE FROM post_categories')
        ->and($executedQueries->queries[0]['sql'])->toContain('WHERE post_id = ?')
        ->and($executedQueries->queries[0]['bindings'])->toBe([1])
        // Then INSERT for each new category
        ->and($executedQueries->queries[1]['sql'])->toContain('INSERT INTO post_categories')
        ->and($executedQueries->queries[1]['bindings'])->toBe([1, 2])
        ->and($executedQueries->queries[2]['bindings'])->toBe([1, 3])
        ->and($executedQueries->queries[3]['bindings'])->toBe([1, 4]);
});

/**
 * @param array<array<string, mixed>> $queryResult
 */
function createPostCategoryMockConnection(
    array $queryResult = [],
    ?object $executedQueries = null,
): ConnectionInterface {
    return new class ($queryResult, $executedQueries) implements ConnectionInterface
    {
        /**
         * @param array<array<string, mixed>> $queryResult
         */
        public function __construct(
            private array $queryResult,
            private ?object $executedQueries = null,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        /**
         * @param array<mixed> $bindings
         * @return array<array<string, mixed>>
         */
        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            if ($this->executedQueries !== null) {
                $this->executedQueries->queries[] = ['sql' => $sql, 'bindings' => $bindings];
            }

            return $this->queryResult;
        }

        /**
         * @param array<mixed> $bindings
         */
        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            if ($this->executedQueries !== null) {
                $this->executedQueries->queries[] = ['sql' => $sql, 'bindings' => $bindings];
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
}

function createTestSlugGenerator(): SlugGeneratorInterface
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
