<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Repositories;

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
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'content' => 'This is a test comment',
            'status' => 'verified',
            'parent_id' => null,
            'verified_at' => '2024-01-01 12:00:00',
            'created_at' => '2024-01-01 10:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator);

    $comment = $repository->find(1);

    expect($comment)->toBeInstanceOf(Comment::class)
        ->and($comment->id)->toBe(1)
        ->and($comment->postId)->toBe(10)
        ->and($comment->name)->toBe('John Doe')
        ->and($comment->email)->toBe('john@example.com')
        ->and($comment->content)->toBe('This is a test comment');
});

it('finds all verified comments for a post', function (): void {
    $queryHistory = [];
    $connection = createCommentMockConnectionWithHistory(
        [
            [
                'id' => 1,
                'post_id' => 10,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'content' => 'First verified comment',
                'status' => 'verified',
                'parent_id' => null,
                'verified_at' => '2024-01-01 12:00:00',
                'created_at' => '2024-01-01 10:00:00',
            ],
            [
                'id' => 2,
                'post_id' => 10,
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
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

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator);

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
                'name' => 'John Doe',
                'email' => 'john@example.com',
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

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator);

    $comments = $repository->findPendingForPost(10);

    expect($comments)->toHaveCount(1)
        ->and($comments[0])->toBeInstanceOf(Comment::class)
        ->and($comments[0]->content)->toBe('Pending comment')
        ->and($queryHistory[0]['sql'])->toContain('post_id = ?')
        ->and($queryHistory[0]['sql'])->toContain('status = ?')
        ->and($queryHistory[0]['bindings'])->toContain(10)
        ->and($queryHistory[0]['bindings'])->toContain('pending');
});

it('orders comments by created_at ascending', function (): void {
    $queryHistory = [];
    $connection = createCommentMockConnectionWithHistory(
        [],
        $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator);

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

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator);

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

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator);

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
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'content' => 'First comment',
                'status' => 'verified',
                'parent_id' => null,
                'verified_at' => '2024-01-01 12:00:00',
                'created_at' => '2024-01-01 10:00:00',
            ],
            [
                'id' => 5,
                'post_id' => 20,
                'name' => 'John Doe',
                'email' => 'john@example.com',
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

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator);

    $comments = $repository->findByEmail('john@example.com');

    expect($comments)->toHaveCount(2)
        ->and($comments[0]->email)->toBe('john@example.com')
        ->and($comments[1]->email)->toBe('john@example.com')
        ->and($queryHistory[0]['sql'])->toContain('email = ?')
        ->and($queryHistory[0]['bindings'])->toContain('john@example.com');
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
