<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Repositories;

use Marko\Blog\Config\BlogConfigInterface;
use Marko\Blog\Entity\Comment;
use Marko\Blog\Repositories\CommentRepository;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use RuntimeException;

it('finds comment by id', function (): void {
    $connection = createCommentMockConnection([
        [
            'id' => 1,
            'post_id' => 10,
            'author_name' => 'John Doe',
            'author_email' => 'john@example.com',
            'content' => 'This is a test comment',
            'status' => 'verified',
            'parent_id' => null,
            'verified_at' => '2024-01-01 12:00:00',
            'created_at' => '2024-01-01 10:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $blogConfig = createMockBlogConfig();

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator, $blogConfig);

    $comment = $repository->find(1);

    expect($comment)->toBeInstanceOf(Comment::class)
        ->and($comment->id)->toBe(1)
        ->and($comment->postId)->toBe(10)
        ->and($comment->authorName)->toBe('John Doe')
        ->and($comment->authorEmail)->toBe('john@example.com')
        ->and($comment->content)->toBe('This is a test comment');
});

it('finds all verified comments for a post', function (): void {
    $queryHistory = [];
    $connection = createCommentMockConnectionWithHistory(
        [
            [
                'id' => 1,
                'post_id' => 10,
                'author_name' => 'John Doe',
                'author_email' => 'john@example.com',
                'content' => 'First verified comment',
                'status' => 'verified',
                'parent_id' => null,
                'verified_at' => '2024-01-01 12:00:00',
                'created_at' => '2024-01-01 10:00:00',
            ],
            [
                'id' => 2,
                'post_id' => 10,
                'author_name' => 'Jane Doe',
                'author_email' => 'jane@example.com',
                'content' => 'Second verified comment',
                'status' => 'verified',
                'parent_id' => null,
                'verified_at' => '2024-01-02 12:00:00',
                'created_at' => '2024-01-02 10:00:00',
            ],
        ],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $blogConfig = createMockBlogConfig();

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator, $blogConfig);

    $comments = $repository->findVerifiedForPost(10);

    expect($comments)->toHaveCount(2)
        ->and($comments[0])->toBeInstanceOf(Comment::class)
        ->and($comments[0]->content)->toBe('First verified comment')
        ->and($comments[1]->content)->toBe('Second verified comment')
        ->and($queryHistory[0]['sql'])->toContain('post_id = ?')
        ->and($queryHistory[0]['sql'])->toContain('status = ?')
        ->and($queryHistory[0]['bindings'])->toContain(10)
        ->and($queryHistory[0]['bindings'])->toContain('verified');
});

it('finds pending comments for a post', function (): void {
    $queryHistory = [];
    $connection = createCommentMockConnectionWithHistory(
        [
            [
                'id' => 1,
                'post_id' => 10,
                'author_name' => 'John Doe',
                'author_email' => 'john@example.com',
                'content' => 'Pending comment',
                'status' => 'pending',
                'parent_id' => null,
                'verified_at' => null,
                'created_at' => '2024-01-01 10:00:00',
            ],
        ],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $blogConfig = createMockBlogConfig();

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator, $blogConfig);

    $comments = $repository->findPendingForPost(10);

    expect($comments)->toHaveCount(1)
        ->and($comments[0])->toBeInstanceOf(Comment::class)
        ->and($comments[0]->content)->toBe('Pending comment')
        ->and($queryHistory[0]['sql'])->toContain('post_id = ?')
        ->and($queryHistory[0]['sql'])->toContain('status = ?')
        ->and($queryHistory[0]['bindings'])->toContain(10)
        ->and($queryHistory[0]['bindings'])->toContain('pending');
});

it('returns comments as threaded tree structure', function (): void {
    // Structure:
    // - Comment 1 (root)
    //   - Comment 2 (reply to 1)
    //     - Comment 3 (reply to 2)
    // - Comment 4 (root)
    $connection = createCommentMockConnection([
        [
            'id' => 1,
            'post_id' => 10,
            'author_name' => 'John Doe',
            'author_email' => 'john@example.com',
            'content' => 'Root comment 1',
            'status' => 'verified',
            'parent_id' => null,
            'verified_at' => '2024-01-01 12:00:00',
            'created_at' => '2024-01-01 10:00:00',
        ],
        [
            'id' => 2,
            'post_id' => 10,
            'author_name' => 'Jane Doe',
            'author_email' => 'jane@example.com',
            'content' => 'Reply to comment 1',
            'status' => 'verified',
            'parent_id' => 1,
            'verified_at' => '2024-01-01 13:00:00',
            'created_at' => '2024-01-01 11:00:00',
        ],
        [
            'id' => 3,
            'post_id' => 10,
            'author_name' => 'Bob Smith',
            'author_email' => 'bob@example.com',
            'content' => 'Reply to comment 2',
            'status' => 'verified',
            'parent_id' => 2,
            'verified_at' => '2024-01-01 14:00:00',
            'created_at' => '2024-01-01 12:00:00',
        ],
        [
            'id' => 4,
            'post_id' => 10,
            'author_name' => 'Alice Jones',
            'author_email' => 'alice@example.com',
            'content' => 'Root comment 2',
            'status' => 'verified',
            'parent_id' => null,
            'verified_at' => '2024-01-01 15:00:00',
            'created_at' => '2024-01-01 13:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $blogConfig = createMockBlogConfig();

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator, $blogConfig);

    $tree = $repository->getThreadedCommentsForPost(10);

    // Should only have 2 root comments
    expect($tree)->toHaveCount(2)
        ->and($tree[0]->content)->toBe('Root comment 1')
        ->and($tree[1]->content)->toBe('Root comment 2');

    // First root has children
    $children = $tree[0]->getChildren();
    expect($children)->toHaveCount(1)
        ->and($children[0]->content)->toBe('Reply to comment 1');

    // Nested child
    $nestedChildren = $children[0]->getChildren();
    expect($nestedChildren)->toHaveCount(1)
        ->and($nestedChildren[0]->content)->toBe('Reply to comment 2');

    // Second root has no children
    expect($tree[1]->getChildren())->toHaveCount(0);
});

it('respects max depth configuration when building tree', function (): void {
    // Structure (maxDepth = 2):
    // - Comment 1 (depth 0)
    //   - Comment 2 (depth 1)
    //     - Comment 3 (depth 2) - should be cut off as max depth
    //       - Comment 4 (depth 3) - would exceed max depth, should be flat at depth 2
    $connection = createCommentMockConnection([
        [
            'id' => 1,
            'post_id' => 10,
            'author_name' => 'John Doe',
            'author_email' => 'john@example.com',
            'content' => 'Root comment (depth 0)',
            'status' => 'verified',
            'parent_id' => null,
            'verified_at' => '2024-01-01 12:00:00',
            'created_at' => '2024-01-01 10:00:00',
        ],
        [
            'id' => 2,
            'post_id' => 10,
            'author_name' => 'Jane Doe',
            'author_email' => 'jane@example.com',
            'content' => 'Reply depth 1',
            'status' => 'verified',
            'parent_id' => 1,
            'verified_at' => '2024-01-01 13:00:00',
            'created_at' => '2024-01-01 11:00:00',
        ],
        [
            'id' => 3,
            'post_id' => 10,
            'author_name' => 'Bob Smith',
            'author_email' => 'bob@example.com',
            'content' => 'Reply depth 2 (max)',
            'status' => 'verified',
            'parent_id' => 2,
            'verified_at' => '2024-01-01 14:00:00',
            'created_at' => '2024-01-01 12:00:00',
        ],
        [
            'id' => 4,
            'post_id' => 10,
            'author_name' => 'Alice Jones',
            'author_email' => 'alice@example.com',
            'content' => 'Would be depth 3 but flattened to max',
            'status' => 'verified',
            'parent_id' => 3,
            'verified_at' => '2024-01-01 15:00:00',
            'created_at' => '2024-01-01 13:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    // Set max depth to 2 (root=0, child=1, grandchild=2)
    $blogConfig = createMockBlogConfig(2);

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator, $blogConfig);

    $tree = $repository->getThreadedCommentsForPost(10);

    // Root comment
    expect($tree)->toHaveCount(1)
        ->and($tree[0]->content)->toBe('Root comment (depth 0)');

    // Depth 1
    $depth1 = $tree[0]->getChildren();
    expect($depth1)->toHaveCount(1)
        ->and($depth1[0]->content)->toBe('Reply depth 1');

    // Depth 2 (max) - should have both comment 3 and flattened comment 4
    $depth2 = $depth1[0]->getChildren();
    expect($depth2)->toHaveCount(2)
        ->and($depth2[0]->content)->toBe('Reply depth 2 (max)')
        ->and($depth2[1]->content)->toBe('Would be depth 3 but flattened to max');

    // Comment 3 should have no children as they were moved up
    expect($depth2[0]->getChildren())->toHaveCount(0);
});

it('orders comments by created_at ascending', function (): void {
    $queryHistory = [];
    $connection = createCommentMockConnectionWithHistory(
        [],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $blogConfig = createMockBlogConfig();

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator, $blogConfig);

    $repository->findVerifiedForPost(10);

    expect($queryHistory[0]['sql'])->toContain('ORDER BY created_at ASC');
});

it('counts total comments for a post', function (): void {
    $queryHistory = [];
    $connection = createCommentMockConnectionWithHistory(
        [['count' => 15]],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $blogConfig = createMockBlogConfig();

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator, $blogConfig);

    $count = $repository->countForPost(10);

    expect($count)->toBe(15)
        ->and($queryHistory[0]['sql'])->toContain('COUNT(*)')
        ->and($queryHistory[0]['sql'])->toContain('post_id = ?')
        ->and($queryHistory[0]['bindings'])->toContain(10);
});

it('counts verified comments for a post', function (): void {
    $queryHistory = [];
    $connection = createCommentMockConnectionWithHistory(
        [['count' => 8]],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $blogConfig = createMockBlogConfig();

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator, $blogConfig);

    $count = $repository->countVerifiedForPost(10);

    expect($count)->toBe(8)
        ->and($queryHistory[0]['sql'])->toContain('COUNT(*)')
        ->and($queryHistory[0]['sql'])->toContain('post_id = ?')
        ->and($queryHistory[0]['sql'])->toContain('status = ?')
        ->and($queryHistory[0]['bindings'])->toContain(10)
        ->and($queryHistory[0]['bindings'])->toContain('verified');
});

it('finds comments by author email', function (): void {
    $queryHistory = [];
    $connection = createCommentMockConnectionWithHistory(
        [
            [
                'id' => 1,
                'post_id' => 10,
                'author_name' => 'John Doe',
                'author_email' => 'john@example.com',
                'content' => 'First comment',
                'status' => 'verified',
                'parent_id' => null,
                'verified_at' => '2024-01-01 12:00:00',
                'created_at' => '2024-01-01 10:00:00',
            ],
            [
                'id' => 5,
                'post_id' => 20,
                'author_name' => 'John Doe',
                'author_email' => 'john@example.com',
                'content' => 'Second comment',
                'status' => 'verified',
                'parent_id' => null,
                'verified_at' => '2024-01-02 12:00:00',
                'created_at' => '2024-01-02 10:00:00',
            ],
        ],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $blogConfig = createMockBlogConfig();

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator, $blogConfig);

    $comments = $repository->findByAuthorEmail('john@example.com');

    expect($comments)->toHaveCount(2)
        ->and($comments[0]->authorEmail)->toBe('john@example.com')
        ->and($comments[1]->authorEmail)->toBe('john@example.com')
        ->and($queryHistory[0]['sql'])->toContain('author_email = ?')
        ->and($queryHistory[0]['bindings'])->toContain('john@example.com');
});

it('calculates depth of a comment in thread', function (): void {
    // Structure:
    // - Comment 1 (root, depth 0)
    //   - Comment 2 (reply to 1, depth 1)
    //     - Comment 3 (reply to 2, depth 2)
    $commentsById = [
        1 => [
            'id' => 1,
            'post_id' => 10,
            'author_name' => 'John Doe',
            'author_email' => 'john@example.com',
            'content' => 'Root comment',
            'status' => 'verified',
            'parent_id' => null,
            'verified_at' => '2024-01-01 12:00:00',
            'created_at' => '2024-01-01 10:00:00',
        ],
        2 => [
            'id' => 2,
            'post_id' => 10,
            'author_name' => 'Jane Doe',
            'author_email' => 'jane@example.com',
            'content' => 'Reply',
            'status' => 'verified',
            'parent_id' => 1,
            'verified_at' => '2024-01-01 13:00:00',
            'created_at' => '2024-01-01 11:00:00',
        ],
        3 => [
            'id' => 3,
            'post_id' => 10,
            'author_name' => 'Bob Smith',
            'author_email' => 'bob@example.com',
            'content' => 'Nested reply',
            'status' => 'verified',
            'parent_id' => 2,
            'verified_at' => '2024-01-01 14:00:00',
            'created_at' => '2024-01-01 12:00:00',
        ],
    ];
    $connection = createCommentMockConnectionForFindById($commentsById);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $blogConfig = createMockBlogConfig();

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator, $blogConfig);

    // Calculate depth for comment 3 (should be 2)
    expect($repository->calculateDepth(3))->toBe(2)
        // Root comment should have depth 0
        ->and($repository->calculateDepth(1))->toBe(0)
        // Comment 2 should have depth 1
        ->and($repository->calculateDepth(2))->toBe(1);
});

// Helper function to create mock connection

function createCommentMockConnection(
    array $queryResult = [],
): ConnectionInterface {
    return createCommentMockConnectionWithHistory($queryResult, $unused);
}

/**
 * Create a mock connection that returns comments by ID.
 * Used for testing depth calculation which needs to find individual comments.
 *
 * @param array<int, array<string, mixed>> $commentsById
 */
function createCommentMockConnectionForFindById(
    array $commentsById,
): ConnectionInterface {
    return new class ($commentsById) implements ConnectionInterface
    {
        /**
         * @param array<int, array<string, mixed>> $commentsById
         */
        public function __construct(
            private array $commentsById,
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
            // Check if this is a find by ID query
            if (str_contains($sql, 'WHERE id = ?') && isset($bindings[0])) {
                $id = (int) $bindings[0];
                if (isset($this->commentsById[$id])) {
                    return [$this->commentsById[$id]];
                }

                return [];
            }

            // Return all comments for other queries
            return array_values($this->commentsById);
        }

        /**
         * @param array<mixed> $bindings
         */
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

/**
 * @param array<array<string, mixed>> $queryResult
 * @param array<array{sql: string, bindings: array<mixed>}>|null $queryHistory
 */
function createCommentMockConnectionWithHistory(
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

function createMockBlogConfig(
    int $maxDepth = 5,
): BlogConfigInterface {
    return new class ($maxDepth) implements BlogConfigInterface
    {
        public function __construct(
            private int $maxDepth,
        ) {}

        public function getPostsPerPage(): int
        {
            return 10;
        }

        public function getCommentMaxDepth(): int
        {
            return $this->maxDepth;
        }

        public function getCommentRateLimitSeconds(): int
        {
            return 30;
        }

        public function getVerificationTokenExpiryDays(): int
        {
            return 7;
        }

        public function getVerificationCookieDays(): int
        {
            return 365;
        }

        public function getRoutePrefix(): string
        {
            return '/blog';
        }

        public function getVerificationCookieName(): string
        {
            return 'blog_verified';
        }
    };
}
