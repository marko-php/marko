<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Repositories;

use Marko\Blog\Entity\Comment;
use Marko\Blog\Entity\Post;
use Marko\Blog\Events\Comment\CommentCreated;
use Marko\Blog\Events\Comment\CommentDeleted;
use Marko\Blog\Enum\CommentStatus;
use Marko\Blog\Repositories\CommentRepository;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Events\EntityCreated;
use Marko\Database\Events\EntityCreating;
use Marko\Database\Events\EntityDeleted;
use Marko\Database\Events\EntityDeleting;
use Marko\Testing\Fake\FakeEventDispatcher;
use ReflectionClass;
use RuntimeException;

it('constructs without explicit EventDispatcherInterface or BlogConfigInterface', function (): void {
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $connection = makeCommentConstructorTestConnection();

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator);

    $reflection = new ReflectionClass($repository);
    expect($reflection->getConstructor()->getDeclaringClass()->getName())->not->toBe(CommentRepository::class);
});

it('dispatches lifecycle events and CommentCreated domain event with post on new comment save', function (): void {
    $dispatcher = new FakeEventDispatcher();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $connection = makeCommentConstructorTestConnection(executeCallback: fn () => 1);

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator, null, $dispatcher);

    $post = new Post(title: 'Test Post', content: 'Content', authorId: 1, slug: 'test-post');
    $post->id = 1;

    $comment = new Comment();
    $comment->postId = 1;
    $comment->name = 'John Doe';
    $comment->email = 'john@example.com';
    $comment->content = 'Great post!';
    $comment->setPost($post);

    $repository->save($comment);

    $types = array_map(fn ($e) => $e::class, $dispatcher->dispatched);

    expect($types)->toContain(EntityCreating::class)
        ->and($types)->toContain(EntityCreated::class)
        ->and($types)->toContain(CommentCreated::class);

    $commentCreatedEvents = $dispatcher->dispatched(CommentCreated::class);
    expect($commentCreatedEvents[0]->getComment())->toBe($comment)
        ->and($commentCreatedEvents[0]->getPost())->toBe($post);
});

it('dispatches CommentDeleted event with post on comment delete', function (): void {
    $dispatcher = new FakeEventDispatcher();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $connection = makeCommentConstructorTestConnection(executeCallback: fn () => 1);

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator, null, $dispatcher);

    $post = new Post(title: 'Test Post', content: 'Content', authorId: 1, slug: 'test-post');
    $post->id = 1;

    $comment = new Comment();
    $comment->id = 5;
    $comment->postId = 1;
    $comment->name = 'Jane Doe';
    $comment->email = 'jane@example.com';
    $comment->content = 'Nice article!';
    $comment->setPost($post);

    $repository->delete($comment);

    $types = array_map(fn ($e) => $e::class, $dispatcher->dispatched);

    expect($types)->toContain(EntityDeleting::class)
        ->and($types)->toContain(EntityDeleted::class)
        ->and($types)->toContain(CommentDeleted::class);

    $commentDeletedEvents = $dispatcher->dispatched(CommentDeleted::class);
    expect($commentDeletedEvents[0]->getComment())->toBe($comment)
        ->and($commentDeletedEvents[0]->getPost())->toBe($post);
});

it('finds verified comments for a post', function (): void {
    $queryHistory = [];
    $connection = makeCommentConstructorTestConnectionWithHistory(
        queryResult: [
            [
                'id' => 1,
                'post_id' => 10,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'content' => 'Verified comment',
                'status' => 'verified',
                'parent_id' => null,
                'verified_at' => '2024-01-01 12:00:00',
                'created_at' => '2024-01-01 10:00:00',
            ],
        ],
        queryHistory: $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator);

    $comments = $repository->findVerifiedForPost(10);

    expect($comments)->toHaveCount(1)
        ->and($comments[0])->toBeInstanceOf(Comment::class)
        ->and($comments[0]->status)->toBe(CommentStatus::Verified)
        ->and($queryHistory[0]['sql'])->toContain('post_id = ?')
        ->and($queryHistory[0]['sql'])->toContain('status = ?')
        ->and($queryHistory[0]['bindings'])->toContain(10)
        ->and($queryHistory[0]['bindings'])->toContain('verified');
});

it('finds pending comments for a post', function (): void {
    $queryHistory = [];
    $connection = makeCommentConstructorTestConnectionWithHistory(
        queryResult: [
            [
                'id' => 2,
                'post_id' => 10,
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'content' => 'Pending comment',
                'status' => 'pending',
                'parent_id' => null,
                'verified_at' => null,
                'created_at' => '2024-01-01 10:00:00',
            ],
        ],
        queryHistory: $queryHistory,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new CommentRepository($connection, $metadataFactory, $hydrator);

    $comments = $repository->findPendingForPost(10);

    expect($comments)->toHaveCount(1)
        ->and($comments[0])->toBeInstanceOf(Comment::class)
        ->and($comments[0]->status)->toBe(CommentStatus::Pending)
        ->and($queryHistory[0]['sql'])->toContain('post_id = ?')
        ->and($queryHistory[0]['sql'])->toContain('status = ?')
        ->and($queryHistory[0]['bindings'])->toContain(10)
        ->and($queryHistory[0]['bindings'])->toContain('pending');
});

// Helpers

function makeCommentConstructorTestConnection(
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

/**
 * @param array<array<string, mixed>> $queryResult
 * @param array<array{sql: string, bindings: array<mixed>}> $queryHistory
 */
function makeCommentConstructorTestConnectionWithHistory(
    array $queryResult = [],
    array &$queryHistory = [],
): ConnectionInterface {
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

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            $this->queryHistory[] = ['sql' => $sql, 'bindings' => $bindings];

            return $this->queryResult;
        }

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
