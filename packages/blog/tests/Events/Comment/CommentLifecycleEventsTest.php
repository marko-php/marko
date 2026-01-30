<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Events\Comment;

use DateTimeImmutable;
use Marko\Blog\Config\BlogConfigInterface;
use Marko\Blog\Entity\Comment;
use Marko\Blog\Entity\CommentInterface;
use Marko\Blog\Entity\Post;
use Marko\Blog\Entity\PostInterface;
use Marko\Blog\Entity\VerificationToken;
use Marko\Blog\Events\Comment\CommentCreated;
use Marko\Blog\Events\Comment\CommentDeleted;
use Marko\Blog\Events\Comment\CommentVerified;
use Marko\Blog\Repositories\CommentRepository;
use Marko\Blog\Services\CommentVerificationService;
use Marko\Blog\Services\TokenRepositoryInterface;
use Marko\Blog\Tests\Mocks\MockCommentRepository;
use Marko\Blog\Tests\Mocks\MockPostRepository;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Message;
use RuntimeException;

it('dispatches CommentCreated event when comment is submitted', function (): void {
    $dispatchedEvents = [];
    $eventDispatcher = createCommentMockEventDispatcher($dispatchedEvents);

    $connection = createCommentEventTestMockConnection(
        queryCallback: fn () => [],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $blogConfig = createCommentMockBlogConfig();

    $repository = new CommentRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $blogConfig,
        null,
        $eventDispatcher,
    );

    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slug: 'test-post',
    );
    $post->id = 1;

    $comment = new Comment();
    $comment->postId = 1;
    $comment->name = 'John Doe';
    $comment->email = 'john@example.com';
    $comment->content = 'Great post!';
    $comment->setPost($post);

    $repository->save($comment);

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(CommentCreated::class);
});

it('dispatches CommentVerified event when email is verified', function (): void {
    $dispatchedEvents = [];
    $eventDispatcher = createCommentMockEventDispatcher($dispatchedEvents);

    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slug: 'test-post',
    );
    $post->id = 1;

    $comment = new Comment();
    $comment->id = 1;
    $comment->postId = 1;
    $comment->name = 'John Doe';
    $comment->email = 'john@example.com';
    $comment->content = 'Great post!';
    $comment->setPost($post);

    $verificationService = createCommentEventVerificationService($eventDispatcher);

    $verificationService->markAsVerified($comment, 'email');

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(CommentVerified::class);
});

it('dispatches CommentDeleted event when comment is removed', function (): void {
    $dispatchedEvents = [];
    $eventDispatcher = createCommentMockEventDispatcher($dispatchedEvents);

    $connection = createCommentEventTestMockConnection(
        queryCallback: fn () => [],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $blogConfig = createCommentMockBlogConfig();

    $repository = new CommentRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $blogConfig,
        null,
        $eventDispatcher,
    );

    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slug: 'test-post',
    );
    $post->id = 1;

    $comment = new Comment();
    $comment->id = 1;
    $comment->postId = 1;
    $comment->name = 'John Doe';
    $comment->email = 'john@example.com';
    $comment->content = 'Great post!';
    $comment->setPost($post);

    $repository->delete($comment);

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(CommentDeleted::class);
});

it('includes full comment entity in event data', function (): void {
    $dispatchedEvents = [];
    $eventDispatcher = createCommentMockEventDispatcher($dispatchedEvents);

    $connection = createCommentEventTestMockConnection(
        queryCallback: fn () => [],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $blogConfig = createCommentMockBlogConfig();

    $repository = new CommentRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $blogConfig,
        null,
        $eventDispatcher,
    );

    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slug: 'test-post',
    );
    $post->id = 1;

    $comment = new Comment();
    $comment->postId = 1;
    $comment->name = 'Jane Smith';
    $comment->email = 'jane@example.com';
    $comment->content = 'This is my full comment content with all details.';
    $comment->setPost($post);

    $repository->save($comment);

    expect($dispatchedEvents)->toHaveCount(1);

    /** @var CommentCreated $event */
    $event = $dispatchedEvents[0];
    $eventComment = $event->getComment();

    expect($eventComment)->toBeInstanceOf(CommentInterface::class)
        ->and($eventComment->getName())->toBe('Jane Smith')
        ->and($eventComment->email)->toBe('jane@example.com')
        ->and($eventComment->content)->toBe('This is my full comment content with all details.');
});

it('includes associated post in event data', function (): void {
    $dispatchedEvents = [];
    $eventDispatcher = createCommentMockEventDispatcher($dispatchedEvents);

    $connection = createCommentEventTestMockConnection(
        queryCallback: fn () => [],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $blogConfig = createCommentMockBlogConfig();

    $repository = new CommentRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $blogConfig,
        null,
        $eventDispatcher,
    );

    $post = new Post(
        title: 'Associated Post Title',
        content: 'Post content for testing association',
        authorId: 42,
        slug: 'associated-post-title',
    );
    $post->id = 5;

    $comment = new Comment();
    $comment->postId = 5;
    $comment->name = 'John Doe';
    $comment->email = 'john@example.com';
    $comment->content = 'Comment on post';
    $comment->setPost($post);

    $repository->save($comment);

    expect($dispatchedEvents)->toHaveCount(1);

    /** @var CommentCreated $event */
    $event = $dispatchedEvents[0];
    $eventPost = $event->getPost();

    expect($eventPost)->toBeInstanceOf(PostInterface::class)
        ->and($eventPost->getTitle())->toBe('Associated Post Title')
        ->and($eventPost->getSlug())->toBe('associated-post-title')
        ->and($eventPost->getAuthorId())->toBe(42);
});

it('includes verification method in CommentVerified event', function (): void {
    $dispatchedEvents = [];
    $eventDispatcher = createCommentMockEventDispatcher($dispatchedEvents);

    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slug: 'test-post',
    );
    $post->id = 1;

    $comment = new Comment();
    $comment->id = 1;
    $comment->postId = 1;
    $comment->name = 'John Doe';
    $comment->email = 'john@example.com';
    $comment->content = 'Great post!';
    $comment->setPost($post);

    $verificationService = createCommentEventVerificationService($eventDispatcher);

    $verificationService->markAsVerified($comment, 'browser_cookie');

    expect($dispatchedEvents)->toHaveCount(1);

    /** @var CommentVerified $event */
    $event = $dispatchedEvents[0];

    expect($event->getVerificationMethod())->toBe('browser_cookie');
});

it('includes timestamp in all events', function (): void {
    $dispatchedEvents = [];
    $eventDispatcher = createCommentMockEventDispatcher($dispatchedEvents);

    $connection = createCommentEventTestMockConnection(
        queryCallback: fn () => [],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $blogConfig = createCommentMockBlogConfig();

    $repository = new CommentRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $blogConfig,
        null,
        $eventDispatcher,
    );

    $verificationService = createCommentEventVerificationService($eventDispatcher);

    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slug: 'test-post',
    );
    $post->id = 1;

    $beforeAll = new DateTimeImmutable();

    // Create a new comment (CommentCreated event)
    $newComment = new Comment();
    $newComment->postId = 1;
    $newComment->name = 'New Commenter';
    $newComment->email = 'new@example.com';
    $newComment->content = 'New comment';
    $newComment->setPost($post);

    $repository->save($newComment);

    // Verify a comment (CommentVerified event)
    $verifyComment = new Comment();
    $verifyComment->id = 2;
    $verifyComment->postId = 1;
    $verifyComment->name = 'Verify Me';
    $verifyComment->email = 'verify@example.com';
    $verifyComment->content = 'Verifiable comment';
    $verifyComment->setPost($post);

    $verificationService->markAsVerified($verifyComment, 'email');

    // Delete a comment (CommentDeleted event)
    $deleteComment = new Comment();
    $deleteComment->id = 3;
    $deleteComment->postId = 1;
    $deleteComment->name = 'Delete Me';
    $deleteComment->email = 'delete@example.com';
    $deleteComment->content = 'Comment to delete';
    $deleteComment->setPost($post);

    $repository->delete($deleteComment);

    $afterAll = new DateTimeImmutable();

    // All events should have timestamps within the test window
    expect($dispatchedEvents)->toHaveCount(3);

    foreach ($dispatchedEvents as $event) {
        expect($event->getTimestamp())->toBeInstanceOf(DateTimeImmutable::class)
            ->and($event->getTimestamp() >= $beforeAll)->toBeTrue()
            ->and($event->getTimestamp() <= $afterAll)->toBeTrue();
    }
});

// Helper functions for comment event tests

function createCommentEventVerificationService(
    EventDispatcherInterface $eventDispatcher,
): CommentVerificationService {
    $tokenRepository = new class () implements TokenRepositoryInterface
    {
        public function save(VerificationToken $token): void {}

        public function findByToken(
            string $token,
        ): ?VerificationToken {
            return null;
        }

        public function findByCommentId(
            int $commentId,
        ): ?VerificationToken {
            return null;
        }

        public function findBrowserTokenForEmail(
            string $email,
        ): ?VerificationToken {
            return null;
        }

        public function delete(VerificationToken $token): void {}

        public function deleteExpiredEmailTokens(
            int $expiryDays,
        ): int {
            return 0;
        }

        public function deleteExpiredBrowserTokens(
            int $cookieDays,
        ): int {
            return 0;
        }
    };

    $commentRepository = new MockCommentRepository();

    $postRepository = new MockPostRepository();

    $mailer = new class () implements MailerInterface
    {
        public function send(
            Message $message,
        ): bool {
            return true;
        }

        public function sendRaw(
            string $to,
            string $raw,
        ): bool {
            return true;
        }
    };

    $config = createCommentMockBlogConfig();

    return new CommentVerificationService(
        tokenRepository: $tokenRepository,
        commentRepository: $commentRepository,
        postRepository: $postRepository,
        mailer: $mailer,
        config: $config,
        eventDispatcher: $eventDispatcher,
    );
}

function createCommentMockBlogConfig(): BlogConfigInterface
{
    return new class () implements BlogConfigInterface
    {
        public function getPostsPerPage(): int
        {
            return 10;
        }

        public function getCommentMaxDepth(): int
        {
            return 3;
        }

        public function getCommentRateLimitSeconds(): int
        {
            return 60;
        }

        public function getVerificationTokenExpiryDays(): int
        {
            return 7;
        }

        public function getVerificationCookieDays(): int
        {
            return 30;
        }

        public function getRoutePrefix(): string
        {
            return '/blog';
        }

        public function getVerificationCookieName(): string
        {
            return 'comment_verified';
        }

        public function getSiteName(): string
        {
            return 'Test Blog';
        }
    };
}

function createCommentMockEventDispatcher(
    array &$dispatchedEvents,
): EventDispatcherInterface {
    return new class ($dispatchedEvents) implements EventDispatcherInterface
    {
        public function __construct(
            private array &$dispatchedEvents,
        ) {}

        public function dispatch(
            Event $event,
        ): void {
            $this->dispatchedEvents[] = $event;
        }
    };
}

function createCommentEventTestMockConnection(
    callable $queryCallback,
    callable $executeCallback,
): ConnectionInterface {
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
