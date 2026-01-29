<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use Marko\Blog\Config\BlogConfigInterface;
use Marko\Blog\Entity\Comment;
use Marko\Blog\Entity\Post;
use Marko\Blog\Entity\VerificationToken;
use Marko\Blog\Enum\CommentStatus;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Repositories\CommentRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\CommentVerificationService;
use Marko\Blog\Services\TokenRepositoryInterface;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Message;
use RuntimeException;

it('generates unique verification token for comment', function (): void {
    $comment = createVerificationTestComment();
    $tokenRepository = new MockTokenRepository();
    $commentRepository = new MockCommentRepository();
    $mailer = new MockMailer();
    $config = new MockBlogConfig();
    $eventDispatcher = new MockEventDispatcher();

    $service = new CommentVerificationService(
        tokenRepository: $tokenRepository,
        commentRepository: $commentRepository,
        postRepository: new MockPostRepository(),
        mailer: $mailer,
        config: $config,
        eventDispatcher: $eventDispatcher,
    );

    $token1 = $service->sendVerificationEmail($comment);
    $token2 = $service->sendVerificationEmail($comment);

    expect($token1)->toBeString()
        ->and($token1)->not->toBeEmpty()
        ->and(strlen($token1))->toBeGreaterThanOrEqual(32)
        ->and($token1)->not->toBe($token2);
});

it('sends verification email with link to commenter', function (): void {
    $comment = createVerificationTestComment(
        authorEmail: 'john@example.com',
        authorName: 'John Doe',
    );
    $tokenRepository = new MockTokenRepository();
    $mailer = new MockMailer();

    $service = new CommentVerificationService(
        tokenRepository: $tokenRepository,
        commentRepository: new MockCommentRepository(),
        postRepository: new MockPostRepository(),
        mailer: $mailer,
        config: new MockBlogConfig(),
        eventDispatcher: new MockEventDispatcher(),
    );

    $token = $service->sendVerificationEmail($comment);

    expect($mailer->sentMessages)->toHaveCount(1);

    $message = $mailer->sentMessages[0];
    expect($message->to[0]->email)->toBe('john@example.com')
        ->and($message->to[0]->name)->toBe('John Doe')
        ->and($message->subject)->toContain('Verify')
        ->and($message->text)->toContain($token);
});

it('verifies comment when valid token provided', function (): void {
    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slug: 'test-post',
    );
    $post->id = 1;

    $comment = createVerificationTestComment();
    $commentRepository = new MockCommentRepository();
    $commentRepository->findResult = $comment;

    $postRepository = new MockPostRepository();
    $postRepository->findResult = $post;

    $verificationToken = VerificationToken::create(
        email: 'commenter@example.com',
        type: 'email',
        commentId: 1,
        expiresAt: new DateTimeImmutable('+1 day'),
    );

    $tokenRepository = new MockTokenRepository();
    $tokenRepository->findByTokenResult = $verificationToken;

    $eventDispatcher = new MockEventDispatcher();

    $service = new CommentVerificationService(
        tokenRepository: $tokenRepository,
        commentRepository: $commentRepository,
        postRepository: $postRepository,
        mailer: new MockMailer(),
        config: new MockBlogConfig(),
        eventDispatcher: $eventDispatcher,
    );

    $result = $service->verifyByToken($verificationToken->token);

    // Should return a VerificationResult with browser token
    expect($result->browserToken)->toBeString()
        ->and($result->browserToken)->not->toBeEmpty()
        ->and(strlen($result->browserToken))->toBeGreaterThanOrEqual(32)
        ->and($result->postSlug)->toBe('test-post')
        ->and($result->commentId)->toBe(1);

    // Should update comment status to verified
    expect($commentRepository->savedComments)->toHaveCount(1)
        ->and($commentRepository->savedComments[0]->status)->toBe(CommentStatus::Verified);

    // Should dispatch CommentVerified event
    expect($eventDispatcher->dispatchedEvents)->toHaveCount(1);
});

it('rejects expired verification tokens', function (): void {
    $expiredToken = VerificationToken::create(
        email: 'commenter@example.com',
        type: 'email',
        commentId: 1,
        expiresAt: new DateTimeImmutable('-1 day'),
    );

    $tokenRepository = new MockTokenRepository();
    $tokenRepository->findByTokenResult = $expiredToken;

    $service = new CommentVerificationService(
        tokenRepository: $tokenRepository,
        commentRepository: new MockCommentRepository(),
        postRepository: new MockPostRepository(),
        mailer: new MockMailer(),
        config: new MockBlogConfig(),
        eventDispatcher: new MockEventDispatcher(),
    );

    expect(fn () => $service->verifyByToken($expiredToken->token))
        ->toThrow(InvalidArgumentException::class, 'expired');
});

it('rejects invalid verification tokens', function (): void {
    $tokenRepository = new MockTokenRepository();
    // findByTokenResult is null by default

    $service = new CommentVerificationService(
        tokenRepository: $tokenRepository,
        commentRepository: new MockCommentRepository(),
        postRepository: new MockPostRepository(),
        mailer: new MockMailer(),
        config: new MockBlogConfig(),
        eventDispatcher: new MockEventDispatcher(),
    );

    expect(fn () => $service->verifyByToken('invalid-token-value'))
        ->toThrow(InvalidArgumentException::class, 'Invalid');
});

it('creates browser token after successful verification', function (): void {
    $comment = createVerificationTestComment();
    $commentRepository = new MockCommentRepository();
    $commentRepository->findResult = $comment;

    $verificationToken = VerificationToken::create(
        email: 'commenter@example.com',
        type: 'email',
        commentId: 1,
        expiresAt: new DateTimeImmutable('+1 day'),
    );

    $tokenRepository = new MockTokenRepository();
    $tokenRepository->findByTokenResult = $verificationToken;

    $postRepository = new MockPostRepository();
    $postRepository->findResult = $comment->getPost();

    $service = new CommentVerificationService(
        tokenRepository: $tokenRepository,
        commentRepository: $commentRepository,
        postRepository: $postRepository,
        mailer: new MockMailer(),
        config: new MockBlogConfig(),
        eventDispatcher: new MockEventDispatcher(),
    );

    $result = $service->verifyByToken($verificationToken->token);

    // Browser token should be saved
    $savedBrowserTokens = array_filter(
        $tokenRepository->savedTokens,
        fn ($t) => $t->type === 'browser',
    );

    expect($savedBrowserTokens)->toHaveCount(1);
    $savedToken = array_values($savedBrowserTokens)[0];
    expect($savedToken->token)->toBe($result->browserToken)
        ->and($savedToken->email)->toBe('commenter@example.com')
        ->and($savedToken->type)->toBe('browser');
});

it('checks if browser token is valid for email', function (): void {
    $browserToken = VerificationToken::create(
        email: 'commenter@example.com',
        type: 'browser',
    );

    $tokenRepository = new MockTokenRepository();
    $tokenRepository->findByTokenResult = $browserToken;

    $service = new CommentVerificationService(
        tokenRepository: $tokenRepository,
        commentRepository: new MockCommentRepository(),
        postRepository: new MockPostRepository(),
        mailer: new MockMailer(),
        config: new MockBlogConfig(),
        eventDispatcher: new MockEventDispatcher(),
    );

    // Valid token for matching email
    expect($service->isBrowserTokenValid($browserToken->token, 'commenter@example.com'))
        ->toBeTrue();

    // Valid token but wrong email
    expect($service->isBrowserTokenValid($browserToken->token, 'other@example.com'))
        ->toBeFalse();

    // Invalid token (not found)
    $tokenRepository->findByTokenResult = null;
    expect($service->isBrowserTokenValid('invalid-token', 'commenter@example.com'))
        ->toBeFalse();
});

it('auto-approves comment when valid browser token exists', function (): void {
    $browserToken = VerificationToken::create(
        email: 'commenter@example.com',
        type: 'browser',
    );

    $tokenRepository = new MockTokenRepository();
    $tokenRepository->findByTokenResult = $browserToken;

    $service = new CommentVerificationService(
        tokenRepository: $tokenRepository,
        commentRepository: new MockCommentRepository(),
        postRepository: new MockPostRepository(),
        mailer: new MockMailer(),
        config: new MockBlogConfig(),
        eventDispatcher: new MockEventDispatcher(),
    );

    // Should auto-approve when valid browser token exists for email
    expect($service->shouldAutoApprove('commenter@example.com', $browserToken->token))
        ->toBeTrue();

    // Should not auto-approve when browser token doesn't match email
    expect($service->shouldAutoApprove('other@example.com', $browserToken->token))
        ->toBeFalse();

    // Should not auto-approve when no browser token provided
    expect($service->shouldAutoApprove('commenter@example.com', null))
        ->toBeFalse();
});

it('returns verification token cookie value after verification', function (): void {
    // This test verifies the flow: after verifying by token, we get a browser
    // token that can be stored in a cookie for auto-approval
    $comment = createVerificationTestComment();
    $commentRepository = new MockCommentRepository();
    $commentRepository->findResult = $comment;

    $verificationToken = VerificationToken::create(
        email: 'commenter@example.com',
        type: 'email',
        commentId: 1,
        expiresAt: new DateTimeImmutable('+1 day'),
    );

    $tokenRepository = new MockTokenRepository();
    $tokenRepository->findByTokenResult = $verificationToken;

    $postRepository = new MockPostRepository();
    $postRepository->findResult = $comment->getPost();

    $service = new CommentVerificationService(
        tokenRepository: $tokenRepository,
        commentRepository: $commentRepository,
        postRepository: $postRepository,
        mailer: new MockMailer(),
        config: new MockBlogConfig(),
        eventDispatcher: new MockEventDispatcher(),
    );

    // verifyByToken returns a VerificationResult with the browser token value to store in cookie
    $result = $service->verifyByToken($verificationToken->token);

    expect($result->browserToken)->toBeString()
        ->and(strlen($result->browserToken))->toBeGreaterThanOrEqual(32)
        ->and($result->postSlug)->toBe('test-post')
        ->and($result->commentId)->toBe(1);

    // The returned value should match the saved browser token
    $savedBrowserTokens = array_filter(
        $tokenRepository->savedTokens,
        fn ($t) => $t->type === 'browser',
    );
    $savedBrowserToken = array_values($savedBrowserTokens)[0];

    expect($result->browserToken)->toBe($savedBrowserToken->token);
});

it('uses configured token expiry days', function (): void {
    $comment = createVerificationTestComment();
    $tokenRepository = new MockTokenRepository();
    $config = new MockBlogConfig(tokenExpiryDays: 14); // 14 days instead of default 7

    $service = new CommentVerificationService(
        tokenRepository: $tokenRepository,
        commentRepository: new MockCommentRepository(),
        postRepository: new MockPostRepository(),
        mailer: new MockMailer(),
        config: $config,
        eventDispatcher: new MockEventDispatcher(),
    );

    $beforeCreate = new DateTimeImmutable();
    $service->sendVerificationEmail($comment);
    $afterCreate = new DateTimeImmutable();

    expect($tokenRepository->savedTokens)->toHaveCount(1);

    $savedToken = $tokenRepository->savedTokens[0];
    $expectedExpiry = $beforeCreate->modify('+14 days');
    $expectedExpiryMax = $afterCreate->modify('+14 days');

    expect($savedToken->expiresAt)->toBeGreaterThanOrEqual($expectedExpiry)
        ->and($savedToken->expiresAt)->toBeLessThanOrEqual($expectedExpiryMax);
});

it('uses configured cookie name from BlogConfig', function (): void {
    $config = new MockBlogConfig(cookieName: 'custom_blog_token');

    $service = new CommentVerificationService(
        tokenRepository: new MockTokenRepository(),
        commentRepository: new MockCommentRepository(),
        postRepository: new MockPostRepository(),
        mailer: new MockMailer(),
        config: $config,
        eventDispatcher: new MockEventDispatcher(),
    );

    expect($service->getCookieName())->toBe('custom_blog_token');
});

it('returns cookie lifetime days from config', function (): void {
    $config = new MockBlogConfig(cookieDays: 180);

    $service = new CommentVerificationService(
        tokenRepository: new MockTokenRepository(),
        commentRepository: new MockCommentRepository(),
        postRepository: new MockPostRepository(),
        mailer: new MockMailer(),
        config: $config,
        eventDispatcher: new MockEventDispatcher(),
    );

    expect($service->getCookieLifetimeDays())->toBe(180);
});

it('allows resending verification email for pending comment', function (): void {
    $comment = createVerificationTestComment();
    $tokenRepository = new MockTokenRepository();
    $mailer = new MockMailer();

    $service = new CommentVerificationService(
        tokenRepository: $tokenRepository,
        commentRepository: new MockCommentRepository(),
        postRepository: new MockPostRepository(),
        mailer: $mailer,
        config: new MockBlogConfig(),
        eventDispatcher: new MockEventDispatcher(),
    );

    // First send
    $token1 = $service->sendVerificationEmail($comment);

    // Second send (resend)
    $token2 = $service->sendVerificationEmail($comment);

    // Both should create new tokens
    expect($tokenRepository->savedTokens)->toHaveCount(2)
        ->and($token1)->not->toBe($token2);

    // Both should send emails
    expect($mailer->sentMessages)->toHaveCount(2);
});

it('invalidates old token when resending verification email', function (): void {
    $comment = createVerificationTestComment();

    // Create existing token for the comment
    $existingToken = VerificationToken::create(
        email: 'commenter@example.com',
        type: 'email',
        commentId: 1,
        expiresAt: new DateTimeImmutable('+1 day'),
    );

    $tokenRepository = new MockTokenRepository();
    $tokenRepository->findByCommentIdResult = $existingToken;

    $service = new CommentVerificationService(
        tokenRepository: $tokenRepository,
        commentRepository: new MockCommentRepository(),
        postRepository: new MockPostRepository(),
        mailer: new MockMailer(),
        config: new MockBlogConfig(),
        eventDispatcher: new MockEventDispatcher(),
    );

    // Resend verification email
    $newToken = $service->sendVerificationEmail($comment);

    // Old token should be deleted
    expect($tokenRepository->deletedTokens)->toHaveCount(1)
        ->and($tokenRepository->deletedTokens[0])->toBe($existingToken);

    // New token should be different
    expect($newToken)->not->toBe($existingToken->token);
});

// Helper functions

function createVerificationTestComment(
    int $id = 1,
    string $authorEmail = 'commenter@example.com',
    string $authorName = 'Test Commenter',
    CommentStatus $status = CommentStatus::Pending,
): Comment {
    $comment = new Comment();
    $comment->id = $id;
    $comment->postId = 10;
    $comment->authorEmail = $authorEmail;
    $comment->authorName = $authorName;
    $comment->content = 'Test comment content';
    $comment->status = $status;
    $comment->createdAt = '2024-01-01 10:00:00';

    $post = new Post();
    $post->id = 10;
    $post->title = 'Test Post';
    $post->slug = 'test-post';
    $comment->setPost($post);

    return $comment;
}

// Mock classes

class MockTokenRepository implements TokenRepositoryInterface
{
    /** @var array<VerificationToken> */
    public array $savedTokens = [];

    /** @var array<VerificationToken> */
    public array $deletedTokens = [];

    public ?VerificationToken $findByTokenResult = null;

    public ?VerificationToken $findByCommentIdResult = null;

    public ?VerificationToken $findBrowserTokenResult = null;

    public function save(
        VerificationToken $token,
    ): void {
        $this->savedTokens[] = $token;
    }

    public function findByToken(
        string $token,
    ): ?VerificationToken {
        return $this->findByTokenResult;
    }

    public function findByCommentId(
        int $commentId,
    ): ?VerificationToken {
        return $this->findByCommentIdResult;
    }

    public function findBrowserTokenForEmail(
        string $email,
    ): ?VerificationToken {
        return $this->findBrowserTokenResult;
    }

    public function delete(
        VerificationToken $token,
    ): void {
        $this->deletedTokens[] = $token;
    }

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
}

class MockCommentRepository implements CommentRepositoryInterface
{
    /** @var array<Comment> */
    public array $savedComments = [];

    public ?Comment $findResult = null;

    public function find(
        int $id,
    ): ?Comment {
        return $this->findResult;
    }

    public function findOrFail(
        int $id,
    ): Comment {
        return $this->findResult ?? throw new RuntimeException('Not found');
    }

    public function findAll(): array
    {
        return [];
    }

    public function findBy(
        array $criteria,
    ): array {
        return [];
    }

    public function findOneBy(
        array $criteria,
    ): ?Comment {
        return null;
    }

    public function findVerifiedForPost(
        int $postId,
    ): array {
        return [];
    }

    public function findPendingForPost(
        int $postId,
    ): array {
        return [];
    }

    public function getThreadedCommentsForPost(
        int $postId,
    ): array {
        return [];
    }

    public function countForPost(
        int $postId,
    ): int {
        return 0;
    }

    public function countVerifiedForPost(
        int $postId,
    ): int {
        return 0;
    }

    public function findByAuthorEmail(
        string $email,
    ): array {
        return [];
    }

    public function calculateDepth(
        int $commentId,
    ): int {
        return 0;
    }

    public function save(
        object $entity,
    ): void {
        $this->savedComments[] = $entity;
    }

    public function delete(
        object $entity,
    ): void {}
}

class MockMailer implements MailerInterface
{
    /** @var array<Message> */
    public array $sentMessages = [];

    public function send(
        Message $message,
    ): bool {
        $this->sentMessages[] = $message;

        return true;
    }

    public function sendRaw(
        string $to,
        string $raw,
    ): bool {
        return true;
    }
}

class MockBlogConfig implements BlogConfigInterface
{
    public function __construct(
        public int $tokenExpiryDays = 7,
        public int $cookieDays = 365,
        public string $cookieName = 'blog_verified',
    ) {}

    public function getPostsPerPage(): int
    {
        return 10;
    }

    public function getCommentMaxDepth(): int
    {
        return 5;
    }

    public function getCommentRateLimitSeconds(): int
    {
        return 30;
    }

    public function getVerificationTokenExpiryDays(): int
    {
        return $this->tokenExpiryDays;
    }

    public function getVerificationCookieDays(): int
    {
        return $this->cookieDays;
    }

    public function getRoutePrefix(): string
    {
        return '/blog';
    }

    public function getVerificationCookieName(): string
    {
        return $this->cookieName;
    }

    public function getSiteName(): string
    {
        return 'Test Blog';
    }
}

class MockEventDispatcher implements EventDispatcherInterface
{
    /** @var array<Event> */
    public array $dispatchedEvents = [];

    public function dispatch(
        Event $event,
    ): void {
        $this->dispatchedEvents[] = $event;
    }
}

class MockPostRepository implements PostRepositoryInterface
{
    public ?Post $findResult = null;

    public function find(
        int $id,
    ): ?Post {
        return $this->findResult;
    }

    public function findOrFail(
        int $id,
    ): Post {
        return $this->findResult ?? throw new RuntimeException('Not found');
    }

    public function findAll(): array
    {
        return [];
    }

    public function findBy(
        array $criteria,
    ): array {
        return [];
    }

    public function findOneBy(
        array $criteria,
    ): ?Post {
        return null;
    }

    public function save(object $entity): void {}

    public function delete(object $entity): void {}

    public function findBySlug(
        string $slug,
    ): ?Post {
        return null;
    }

    public function findPublished(): array
    {
        return [];
    }

    public function findPublishedPaginated(
        int $limit,
        int $offset,
    ): array {
        return [];
    }

    public function countPublished(): int
    {
        return 0;
    }

    public function findByStatus(
        PostStatus $status,
    ): array {
        return [];
    }

    public function findByAuthor(
        int $authorId,
    ): array {
        return [];
    }

    public function findScheduledPostsDue(): array
    {
        return [];
    }

    public function countByAuthor(
        int $authorId,
    ): int {
        return 0;
    }

    public function findPublishedByAuthor(
        int $authorId,
        int $limit,
        int $offset,
    ): array {
        return [];
    }

    public function countPublishedByAuthor(
        int $authorId,
    ): int {
        return 0;
    }

    public function isSlugUnique(
        string $slug,
        ?int $excludeId = null,
    ): bool {
        return true;
    }

    public function findPublishedByTag(
        int $tagId,
        int $limit,
        int $offset,
    ): array {
        return [];
    }

    public function countPublishedByTag(
        int $tagId,
    ): int {
        return 0;
    }

    public function findPublishedByCategory(
        int $categoryId,
        int $limit,
        int $offset,
    ): array {
        return [];
    }

    public function countPublishedByCategory(
        int $categoryId,
    ): int {
        return 0;
    }

    public function attachCategory(
        int $postId,
        int $categoryId,
    ): void {}

    public function detachCategory(
        int $postId,
        int $categoryId,
    ): void {}

    public function attachTag(
        int $postId,
        int $tagId,
    ): void {}

    public function detachTag(
        int $postId,
        int $tagId,
    ): void {}

    public function getCategoriesForPost(
        int $postId,
    ): array {
        return [];
    }

    public function getTagsForPost(
        int $postId,
    ): array {
        return [];
    }

    public function syncCategories(
        int $postId,
        array $categoryIds,
    ): void {}

    public function syncTags(
        int $postId,
        array $tagIds,
    ): void {}

    public function findPublishedByCategories(
        array $categoryIds,
        int $limit,
        int $offset,
    ): array {
        return [];
    }

    public function countPublishedByCategories(
        array $categoryIds,
    ): int {
        return 0;
    }
}
