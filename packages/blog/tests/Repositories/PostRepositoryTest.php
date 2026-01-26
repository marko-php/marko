<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Repositories;

use Marko\Blog\Entity\Post;
use Marko\Blog\Entity\PostInterface;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Repositories\PostRepository;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;
use ReflectionClass;
use RuntimeException;

it('extends the Repository base class', function (): void {
    $reflection = new ReflectionClass(PostRepository::class);

    expect($reflection->isSubclassOf(Repository::class))->toBeTrue();
});

it('implements PostRepositoryInterface', function (): void {
    $reflection = new ReflectionClass(PostRepository::class);

    expect($reflection->implementsInterface(PostRepositoryInterface::class))->toBeTrue();
});

it('defines ENTITY_CLASS constant pointing to Post entity', function (): void {
    $reflection = new ReflectionClass(PostRepository::class);

    expect($reflection->hasConstant('ENTITY_CLASS'))->toBeTrue()
        ->and($reflection->getConstant('ENTITY_CLASS'))->toBe(Post::class);
});

it('can find a post by id using inherited find method', function (): void {
    $connection = createMockConnection([
        [
            'id' => 1,
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'This is test content',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);

    $post = $repository->find(1);

    expect($post)->toBeInstanceOf(Post::class)
        ->and($post->id)->toBe(1)
        ->and($post->title)->toBe('Test Post')
        ->and($post->slug)->toBe('test-post')
        ->and($post->content)->toBe('This is test content');
});

it('can find all posts using inherited findAll method', function (): void {
    $connection = createMockConnection([
        [
            'id' => 1,
            'title' => 'First Post',
            'slug' => 'first-post',
            'content' => 'First content',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
        [
            'id' => 2,
            'title' => 'Second Post',
            'slug' => 'second-post',
            'content' => 'Second content',
            'created_at' => '2024-01-02 00:00:00',
            'updated_at' => '2024-01-02 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);

    $posts = $repository->findAll();

    expect($posts)->toHaveCount(2)
        ->and($posts[0])->toBeInstanceOf(Post::class)
        ->and($posts[0]->title)->toBe('First Post')
        ->and($posts[1]->title)->toBe('Second Post');
});

it('can find posts by criteria using inherited findBy method', function (): void {
    $connection = createMockConnection([
        [
            'id' => 1,
            'title' => 'Matching Post',
            'slug' => 'matching-post',
            'content' => 'Content here',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);

    $posts = $repository->findBy(['title' => 'Matching Post']);

    expect($posts)->toHaveCount(1)
        ->and($posts[0])->toBeInstanceOf(Post::class)
        ->and($posts[0]->title)->toBe('Matching Post');
});

it('provides findBySlug convenience method for slug lookups', function (): void {
    $connection = createMockConnection([
        [
            'id' => 1,
            'title' => 'My Post',
            'slug' => 'my-post',
            'content' => 'Content here',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);

    $post = $repository->findBySlug('my-post');

    expect($post)->toBeInstanceOf(Post::class)
        ->and($post->slug)->toBe('my-post')
        ->and($post->title)->toBe('My Post');
});

it('finds posts by status', function (): void {
    $queryHistory = [];
    $connection = createMockConnectionWithHistory(
        [
            [
                'id' => 1,
                'title' => 'Draft Post',
                'slug' => 'draft-post',
                'content' => 'Draft content',
                'summary' => null,
                'status' => 'draft',
                'author_id' => 1,
                'scheduled_at' => null,
                'published_at' => null,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
        ],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);
    $posts = $repository->findByStatus(PostStatus::Draft);

    expect($posts)->toHaveCount(1)
        ->and($posts[0])->toBeInstanceOf(PostInterface::class)
        ->and($queryHistory[0]['sql'])->toContain('status = ?')
        ->and($queryHistory[0]['bindings'])->toContain('draft');
});

it('finds published posts only', function (): void {
    $queryHistory = [];
    $connection = createMockConnectionWithHistory(
        [
            [
                'id' => 2,
                'title' => 'Published Post',
                'slug' => 'published-post',
                'content' => 'Published content',
                'summary' => 'Summary here',
                'status' => 'published',
                'author_id' => 1,
                'scheduled_at' => null,
                'published_at' => '2024-01-10 12:00:00',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-10 12:00:00',
            ],
        ],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);
    $posts = $repository->findPublished();

    expect($posts)->toHaveCount(1)
        ->and($posts[0])->toBeInstanceOf(PostInterface::class)
        ->and($queryHistory[0]['sql'])->toContain('status = ?')
        ->and($queryHistory[0]['bindings'])->toContain('published');
});

it('finds posts by author', function (): void {
    $queryHistory = [];
    $connection = createMockConnectionWithHistory(
        [
            [
                'id' => 1,
                'title' => 'Author Post 1',
                'slug' => 'author-post-1',
                'content' => 'Content',
                'summary' => null,
                'status' => 'published',
                'author_id' => 42,
                'scheduled_at' => null,
                'published_at' => '2024-01-10 12:00:00',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-10 12:00:00',
            ],
            [
                'id' => 2,
                'title' => 'Author Post 2',
                'slug' => 'author-post-2',
                'content' => 'Content 2',
                'summary' => null,
                'status' => 'draft',
                'author_id' => 42,
                'scheduled_at' => null,
                'published_at' => null,
                'created_at' => '2024-01-02 00:00:00',
                'updated_at' => '2024-01-02 00:00:00',
            ],
        ],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);
    $posts = $repository->findByAuthor(42);

    expect($posts)->toHaveCount(2)
        ->and($posts[0])->toBeInstanceOf(PostInterface::class)
        ->and($queryHistory[0]['sql'])->toContain('author_id = ?')
        ->and($queryHistory[0]['bindings'])->toContain(42);
});

it('finds scheduled posts due for publishing', function (): void {
    $queryHistory = [];
    $connection = createMockConnectionWithHistory(
        [
            [
                'id' => 1,
                'title' => 'Scheduled Post',
                'slug' => 'scheduled-post',
                'content' => 'Scheduled content',
                'summary' => null,
                'status' => 'scheduled',
                'author_id' => 1,
                'scheduled_at' => '2024-01-15 08:00:00',
                'published_at' => null,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
        ],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);
    $posts = $repository->findScheduledPostsDue();

    expect($posts)->toHaveCount(1)
        ->and($posts[0])->toBeInstanceOf(PostInterface::class)
        ->and($queryHistory[0]['sql'])->toContain('status = ?')
        ->and($queryHistory[0]['sql'])->toContain('scheduled_at <= ?')
        ->and($queryHistory[0]['bindings'][0])->toBe('scheduled');
});

it('counts posts by author', function (): void {
    $queryHistory = [];
    $connection = createMockConnectionWithHistory(
        [
            ['count' => 5],
        ],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);
    $count = $repository->countByAuthor(42);

    expect($count)->toBe(5)
        ->and($queryHistory[0]['sql'])->toContain('COUNT(*)')
        ->and($queryHistory[0]['sql'])->toContain('author_id = ?')
        ->and($queryHistory[0]['bindings'])->toContain(42);
});

it('checks if slug is unique via isSlugUnique method', function (): void {
    $queryHistory = [];
    // Return empty to indicate slug is unique
    $connection = createMockConnectionWithHistory(
        [],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);
    $isUnique = $repository->isSlugUnique('new-unique-slug');

    expect($isUnique)->toBeTrue()
        ->and($queryHistory[0]['sql'])->toContain('slug = ?')
        ->and($queryHistory[0]['bindings'])->toContain('new-unique-slug');
});

it('checks slug uniqueness excludes given id', function (): void {
    $queryHistory = [];
    $connection = createMockConnectionWithHistory(
        [],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);
    $isUnique = $repository->isSlugUnique('existing-slug', 5);

    expect($isUnique)->toBeTrue()
        ->and($queryHistory[0]['sql'])->toContain('slug = ?')
        ->and($queryHistory[0]['sql'])->toContain('id != ?')
        ->and($queryHistory[0]['bindings'])->toBe(['existing-slug', 5]);
});

it('returns false when slug is not unique', function (): void {
    $queryHistory = [];
    // Return a row to indicate slug exists
    $connection = createMockConnectionWithHistory(
        [
            [
                'id' => 1,
                'title' => 'Existing Post',
                'slug' => 'existing-slug',
                'content' => 'Content',
                'summary' => null,
                'status' => 'draft',
                'author_id' => 1,
                'scheduled_at' => null,
                'published_at' => null,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
        ],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);
    $isUnique = $repository->isSlugUnique('existing-slug');

    expect($isUnique)->toBeFalse();
});

it('finds published posts by multiple category IDs', function (): void {
    $queryHistory = [];
    $connection = createMockConnectionWithHistory(
        [
            [
                'id' => 1,
                'title' => 'Post in PHP',
                'slug' => 'post-in-php',
                'content' => 'Content',
                'summary' => null,
                'status' => 'published',
                'author_id' => 1,
                'scheduled_at' => null,
                'published_at' => '2024-01-15 12:00:00',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-15 12:00:00',
            ],
            [
                'id' => 2,
                'title' => 'Post in JavaScript',
                'slug' => 'post-in-javascript',
                'content' => 'Content 2',
                'summary' => null,
                'status' => 'published',
                'author_id' => 1,
                'scheduled_at' => null,
                'published_at' => '2024-01-14 12:00:00',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-14 12:00:00',
            ],
        ],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);
    $posts = $repository->findPublishedByCategories([3, 4, 5], 10, 0);

    expect($posts)->toHaveCount(2)
        ->and($posts[0])->toBeInstanceOf(PostInterface::class)
        ->and($queryHistory[0]['sql'])->toContain('DISTINCT')
        ->and($queryHistory[0]['sql'])->toContain('post_categories')
        ->and($queryHistory[0]['sql'])->toContain('category_id IN (?,?,?)')
        ->and($queryHistory[0]['sql'])->toContain('status = ?')
        ->and($queryHistory[0]['bindings'])->toBe([3, 4, 5, 'published']);
});

it('returns empty array when finding posts with empty category IDs', function (): void {
    $queryHistory = [];
    $connection = createMockConnectionWithHistory([], $queryHistory);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);
    $posts = $repository->findPublishedByCategories([], 10, 0);

    expect($posts)->toBe([])
        ->and($queryHistory)->toBe([]);
});

it('counts published posts by multiple category IDs', function (): void {
    $queryHistory = [];
    $connection = createMockConnectionWithHistory(
        [
            ['count' => 15],
        ],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);
    $count = $repository->countPublishedByCategories([3, 4, 5]);

    expect($count)->toBe(15)
        ->and($queryHistory[0]['sql'])->toContain('COUNT(DISTINCT')
        ->and($queryHistory[0]['sql'])->toContain('post_categories')
        ->and($queryHistory[0]['sql'])->toContain('category_id IN (?,?,?)')
        ->and($queryHistory[0]['sql'])->toContain('status = ?')
        ->and($queryHistory[0]['bindings'])->toBe([3, 4, 5, 'published']);
});

it('returns zero when counting posts with empty category IDs', function (): void {
    $queryHistory = [];
    $connection = createMockConnectionWithHistory([], $queryHistory);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);
    $count = $repository->countPublishedByCategories([]);

    expect($count)->toBe(0)
        ->and($queryHistory)->toBe([]);
});

// Helper function to create mock connection

function createMockConnection(
    array $queryResult = [],
): ConnectionInterface {
    return createMockConnectionWithHistory($queryResult, $unused);
}

/**
 * @param array<array<string, mixed>> $queryResult
 * @param array<array{sql: string, bindings: array<mixed>}>|null $queryHistory
 */
function createMockConnectionWithHistory(
    array $queryResult = [],
    ?array &$queryHistory = null,
): ConnectionInterface {
    $queryHistory ??= [];

    return new class ($queryResult, $queryHistory) implements ConnectionInterface
    {
        /**
         * @param array<array<string, mixed>> $queryResult
         * @param array<array{sql: string, bindings: array<mixed>}> $queryHistory
         */
        public function __construct(
            private array $queryResult,
            private array &$queryHistory,
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
            $this->queryHistory[] = ['sql' => $sql, 'bindings' => $bindings];

            return $this->queryResult;
        }

        /**
         * @param array<mixed> $bindings
         */
        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            $this->queryHistory[] = ['sql' => $sql, 'bindings' => $bindings];

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
