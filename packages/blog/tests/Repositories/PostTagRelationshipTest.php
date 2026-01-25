<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Repositories;

use Marko\Blog\Entity\Post;
use Marko\Blog\Entity\Tag;
use Marko\Blog\Repositories\PostRepository;
use Marko\Blog\Repositories\TagRepository;
use Marko\Blog\Services\SlugGenerator;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use RuntimeException;

it('attaches tag to post', function (): void {
    $executedQueries = new class ()
    {
        public array $queries = [];
    };

    $connection = createMockConnectionWithCapture($executedQueries);

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);

    $repository->attachTag(1, 5);

    $insertQuery = array_filter(
        $executedQueries->queries,
        fn ($q) => str_contains($q['sql'], 'INSERT') && str_contains($q['sql'], 'post_tags'),
    );

    expect($insertQuery)->not->toBeEmpty();

    $query = array_values($insertQuery)[0];
    expect($query['bindings'])->toContain(1)
        ->and($query['bindings'])->toContain(5);
});

it('detaches tag from post', function (): void {
    $executedQueries = new class ()
    {
        public array $queries = [];
    };

    $connection = createMockConnectionWithCapture($executedQueries);

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);

    $repository->detachTag(1, 5);

    $deleteQuery = array_filter(
        $executedQueries->queries,
        fn ($q) => str_contains($q['sql'], 'DELETE') && str_contains($q['sql'], 'post_tags'),
    );

    expect($deleteQuery)->not->toBeEmpty();

    $query = array_values($deleteQuery)[0];
    expect($query['bindings'])->toContain(1)
        ->and($query['bindings'])->toContain(5);
});

it('returns all tags for a post', function (): void {
    $tagRows = [
        [
            'id' => 1,
            'name' => 'PHP',
            'slug' => 'php',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
        [
            'id' => 2,
            'name' => 'Laravel',
            'slug' => 'laravel',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ];

    $connection = createMockConnectionWithQueryResult($tagRows);

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);

    $tags = $repository->getTagsForPost(1);

    expect($tags)->toHaveCount(2)
        ->and($tags[0])->toBeInstanceOf(Tag::class)
        ->and($tags[0]->name)->toBe('PHP')
        ->and($tags[1]->name)->toBe('Laravel');
});

it('returns all posts for a tag', function (): void {
    $postRows = [
        [
            'id' => 1,
            'title' => 'First Post',
            'slug' => 'first-post',
            'content' => 'Content 1',
            'summary' => null,
            'status' => 'published',
            'author_id' => 1,
            'scheduled_at' => null,
            'published_at' => '2024-01-10 12:00:00',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
        [
            'id' => 2,
            'title' => 'Second Post',
            'slug' => 'second-post',
            'content' => 'Content 2',
            'summary' => null,
            'status' => 'published',
            'author_id' => 1,
            'scheduled_at' => null,
            'published_at' => '2024-01-15 12:00:00',
            'created_at' => '2024-01-02 00:00:00',
            'updated_at' => '2024-01-02 00:00:00',
        ],
    ];

    $connection = createMockConnectionWithQueryResult($postRows);

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository($connection, $metadataFactory, $hydrator, $slugGenerator);

    $posts = $repository->getPostsForTag(5);

    expect($posts)->toHaveCount(2)
        ->and($posts[0])->toBeInstanceOf(Post::class)
        ->and($posts[0]->title)->toBe('First Post')
        ->and($posts[1]->title)->toBe('Second Post');
});

it('syncs tags for a post replacing existing', function (): void {
    $executedQueries = new class ()
    {
        public array $queries = [];
    };

    $connection = createMockConnectionWithCapture($executedQueries);

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);

    // Sync tags 1, 2, 3 for post 5
    $repository->syncTags(5, [1, 2, 3]);

    // First, existing tags should be deleted
    $deleteQuery = array_filter(
        $executedQueries->queries,
        fn ($q) => str_contains($q['sql'], 'DELETE') && str_contains($q['sql'], 'post_tags'),
    );
    expect($deleteQuery)->not->toBeEmpty();

    $deleteQueryValues = array_values($deleteQuery)[0];
    expect($deleteQueryValues['bindings'])->toContain(5); // post_id

    // Then, new tags should be inserted
    $insertQueries = array_filter(
        $executedQueries->queries,
        fn ($q) => str_contains($q['sql'], 'INSERT') && str_contains($q['sql'], 'post_tags'),
    );
    expect($insertQueries)->toHaveCount(3); // 3 tags to insert

    // Verify each tag ID is inserted
    $insertedTagIds = array_map(
        fn ($q) => $q['bindings'][1], // tag_id is second binding
        array_values($insertQueries),
    );
    expect($insertedTagIds)->toContain(1)
        ->and($insertedTagIds)->toContain(2)
        ->and($insertedTagIds)->toContain(3);
});

/**
 * Helper function to create mock connection with query capture.
 */
function createMockConnectionWithCapture(
    object $capture,
): ConnectionInterface {
    return new class ($capture) implements ConnectionInterface
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
            $this->capture->queries[] = ['sql' => $sql, 'bindings' => $bindings, 'type' => 'query'];

            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            $this->capture->queries[] = ['sql' => $sql, 'bindings' => $bindings, 'type' => 'execute'];

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

/**
 * Helper function to create mock connection with query result.
 */
function createMockConnectionWithQueryResult(
    array $queryResult,
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
