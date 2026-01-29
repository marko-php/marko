<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Controllers\CommentController;

use Marko\Blog\Config\BlogConfigInterface;
use Marko\Blog\Controllers\CommentController;
use Marko\Blog\Dto\VerificationResult;
use Marko\Blog\Entity\Comment;
use Marko\Blog\Entity\CommentInterface;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\CommentStatus;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Events\Comment\CommentCreated;
use Marko\Blog\Repositories\CommentRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\CommentRateLimiterInterface;
use Marko\Blog\Services\CommentVerificationServiceInterface;
use Marko\Blog\Services\CsrfValidatorInterface;
use Marko\Blog\Services\HoneypotValidatorInterface;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Routing\Attributes\Post as PostRoute;
use ReflectionClass;

it('accepts comment submission at POST /blog/{slug}/comment', function (): void {
    $reflection = new ReflectionClass(CommentController::class);
    $method = $reflection->getMethod('submit');
    $attributes = $method->getAttributes(PostRoute::class);

    expect($attributes)->toHaveCount(1);

    $routeAttribute = $attributes[0]->newInstance();
    expect($routeAttribute->path)->toBe('/blog/{slug}/comment');
});

it('returns 404 when post slug not found', function (): void {
    $postRepository = createMockPostRepository();
    $commentRepository = createMockCommentRepository();
    $honeypotValidator = createMockHoneypotValidator();
    $rateLimiter = createMockRateLimiter();
    $verificationService = createMockVerificationService();
    $blogConfig = createMockBlogConfig();
    $eventDispatcher = createMockEventDispatcher();

    $controller = new CommentController(
        postRepository: $postRepository,
        commentRepository: $commentRepository,
        honeypotValidator: $honeypotValidator,
        rateLimiter: $rateLimiter,
        verificationService: $verificationService,
        blogConfig: $blogConfig,
        eventDispatcher: $eventDispatcher,
    );

    $response = $controller->submit(
        slug: 'non-existent-post',
        authorName: 'John Doe',
        authorEmail: 'john@example.com',
        content: 'This is a test comment.',
        honeypot: '',
        ipAddress: '127.0.0.1',
    );

    expect($response->statusCode())->toBe(404)
        ->and($response->body())->toContain('not found');
});

it('returns 404 when post is not published', function (): void {
    $draftPost = createPost(
        id: 1,
        title: 'Draft Post',
        slug: 'draft-post',
        status: PostStatus::Draft,
    );

    $postRepository = createMockPostRepository(findBySlugResult: $draftPost);
    $commentRepository = createMockCommentRepository();
    $honeypotValidator = createMockHoneypotValidator();
    $rateLimiter = createMockRateLimiter();
    $verificationService = createMockVerificationService();
    $blogConfig = createMockBlogConfig();
    $eventDispatcher = createMockEventDispatcher();

    $controller = new CommentController(
        postRepository: $postRepository,
        commentRepository: $commentRepository,
        honeypotValidator: $honeypotValidator,
        rateLimiter: $rateLimiter,
        verificationService: $verificationService,
        blogConfig: $blogConfig,
        eventDispatcher: $eventDispatcher,
    );

    $response = $controller->submit(
        slug: 'draft-post',
        authorName: 'John Doe',
        authorEmail: 'john@example.com',
        content: 'This is a test comment.',
        honeypot: '',
        ipAddress: '127.0.0.1',
    );

    expect($response->statusCode())->toBe(404)
        ->and($response->body())->toContain('not found');
});

it('validates author_name is required', function (): void {
    $post = createPost(id: 1, title: 'Test Post', slug: 'test-post');
    $postRepository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository();
    $honeypotValidator = createMockHoneypotValidator();
    $rateLimiter = createMockRateLimiter();
    $verificationService = createMockVerificationService();
    $blogConfig = createMockBlogConfig();
    $eventDispatcher = createMockEventDispatcher();

    $controller = new CommentController(
        postRepository: $postRepository,
        commentRepository: $commentRepository,
        honeypotValidator: $honeypotValidator,
        rateLimiter: $rateLimiter,
        verificationService: $verificationService,
        blogConfig: $blogConfig,
        eventDispatcher: $eventDispatcher,
    );

    $response = $controller->submit(
        slug: 'test-post',
        authorName: '',
        authorEmail: 'john@example.com',
        content: 'This is a valid comment content.',
        honeypot: '',
        ipAddress: '127.0.0.1',
    );

    expect($response->statusCode())->toBe(422);

    $body = json_decode($response->body(), true);
    expect($body)->toHaveKey('errors')
        ->and($body['errors'])->toHaveKey('author_name');
});

it('validates author_email is required and valid format', function (): void {
    $post = createPost(id: 1, title: 'Test Post', slug: 'test-post');
    $postRepository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository();
    $honeypotValidator = createMockHoneypotValidator();
    $rateLimiter = createMockRateLimiter();
    $verificationService = createMockVerificationService();
    $blogConfig = createMockBlogConfig();
    $eventDispatcher = createMockEventDispatcher();

    $controller = new CommentController(
        postRepository: $postRepository,
        commentRepository: $commentRepository,
        honeypotValidator: $honeypotValidator,
        rateLimiter: $rateLimiter,
        verificationService: $verificationService,
        blogConfig: $blogConfig,
        eventDispatcher: $eventDispatcher,
    );

    // Test empty email
    $response = $controller->submit(
        slug: 'test-post',
        authorName: 'John Doe',
        authorEmail: '',
        content: 'This is a valid comment content.',
        honeypot: '',
        ipAddress: '127.0.0.1',
    );

    expect($response->statusCode())->toBe(422);

    $body = json_decode($response->body(), true);
    expect($body['errors'])->toHaveKey('author_email');

    // Test invalid email format
    $response = $controller->submit(
        slug: 'test-post',
        authorName: 'John Doe',
        authorEmail: 'not-an-email',
        content: 'This is a valid comment content.',
        honeypot: '',
        ipAddress: '127.0.0.1',
    );

    expect($response->statusCode())->toBe(422);

    $body = json_decode($response->body(), true);
    expect($body['errors'])->toHaveKey('author_email');
});

it('validates content is required and has minimum length', function (): void {
    $post = createPost(id: 1, title: 'Test Post', slug: 'test-post');
    $postRepository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository();
    $honeypotValidator = createMockHoneypotValidator();
    $rateLimiter = createMockRateLimiter();
    $verificationService = createMockVerificationService();
    $blogConfig = createMockBlogConfig();
    $eventDispatcher = createMockEventDispatcher();

    $controller = new CommentController(
        postRepository: $postRepository,
        commentRepository: $commentRepository,
        honeypotValidator: $honeypotValidator,
        rateLimiter: $rateLimiter,
        verificationService: $verificationService,
        blogConfig: $blogConfig,
        eventDispatcher: $eventDispatcher,
    );

    // Test empty content
    $response = $controller->submit(
        slug: 'test-post',
        authorName: 'John Doe',
        authorEmail: 'john@example.com',
        content: '',
        honeypot: '',
        ipAddress: '127.0.0.1',
    );

    expect($response->statusCode())->toBe(422);

    $body = json_decode($response->body(), true);
    expect($body['errors'])->toHaveKey('content');

    // Test content too short (less than 10 characters)
    $response = $controller->submit(
        slug: 'test-post',
        authorName: 'John Doe',
        authorEmail: 'john@example.com',
        content: 'Hi',
        honeypot: '',
        ipAddress: '127.0.0.1',
    );

    expect($response->statusCode())->toBe(422);

    $body = json_decode($response->body(), true);
    expect($body['errors'])->toHaveKey('content');
});

it('rejects submission when honeypot field is filled', function (): void {
    $post = createPost(id: 1, title: 'Test Post', slug: 'test-post');
    $postRepository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository();
    $honeypotValidator = createMockHoneypotValidator(validateResult: false);
    $rateLimiter = createMockRateLimiter();
    $verificationService = createMockVerificationService();
    $blogConfig = createMockBlogConfig();
    $eventDispatcher = createMockEventDispatcher();

    $controller = new CommentController(
        postRepository: $postRepository,
        commentRepository: $commentRepository,
        honeypotValidator: $honeypotValidator,
        rateLimiter: $rateLimiter,
        verificationService: $verificationService,
        blogConfig: $blogConfig,
        eventDispatcher: $eventDispatcher,
    );

    $response = $controller->submit(
        slug: 'test-post',
        authorName: 'John Doe',
        authorEmail: 'john@example.com',
        content: 'This is a valid comment content.',
        honeypot: 'filled-by-bot',
        ipAddress: '127.0.0.1',
    );

    // Should silently reject with fake success to avoid giving bots feedback
    expect($response->statusCode())->toBe(200);

    $body = json_decode($response->body(), true);
    expect($body['status'])->toBe('ok');

    // But comment should NOT have been saved
    expect($commentRepository->savedComments)->toBeEmpty();
});

it('validates CSRF token when marko/csrf is installed', function (): void {
    $post = createPost(id: 1, title: 'Test Post', slug: 'test-post');
    $postRepository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository();
    $honeypotValidator = createMockHoneypotValidator();
    $rateLimiter = createMockRateLimiter();
    $verificationService = createMockVerificationService();
    $blogConfig = createMockBlogConfig();
    $eventDispatcher = createMockEventDispatcher();
    $csrfValidator = createMockCsrfValidator(validateResult: false);

    $controller = new CommentController(
        postRepository: $postRepository,
        commentRepository: $commentRepository,
        honeypotValidator: $honeypotValidator,
        rateLimiter: $rateLimiter,
        verificationService: $verificationService,
        blogConfig: $blogConfig,
        eventDispatcher: $eventDispatcher,
        csrfValidator: $csrfValidator,
    );

    $response = $controller->submit(
        slug: 'test-post',
        authorName: 'John Doe',
        authorEmail: 'john@example.com',
        content: 'This is a valid comment content.',
        honeypot: '',
        ipAddress: '127.0.0.1',
        csrfToken: 'invalid-token',
    );

    expect($response->statusCode())->toBe(422);

    $body = json_decode($response->body(), true);
    expect($body['errors'])->toHaveKey('csrf');
});

it('skips CSRF validation when marko/csrf is not installed', function (): void {
    $post = createPost(id: 1, title: 'Test Post', slug: 'test-post');
    $postRepository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository();
    $honeypotValidator = createMockHoneypotValidator();
    $rateLimiter = createMockRateLimiter();
    $verificationService = createMockVerificationService();
    $blogConfig = createMockBlogConfig();
    $eventDispatcher = createMockEventDispatcher();
    // No CSRF validator provided

    $controller = new CommentController(
        postRepository: $postRepository,
        commentRepository: $commentRepository,
        honeypotValidator: $honeypotValidator,
        rateLimiter: $rateLimiter,
        verificationService: $verificationService,
        blogConfig: $blogConfig,
        eventDispatcher: $eventDispatcher,
        // csrfValidator not provided - defaults to null
    );

    $response = $controller->submit(
        slug: 'test-post',
        authorName: 'John Doe',
        authorEmail: 'john@example.com',
        content: 'This is a valid comment content.',
        honeypot: '',
        ipAddress: '127.0.0.1',
        csrfToken: 'any-token-should-be-ignored',
    );

    // Should still succeed (not 422) because CSRF is not validated
    // 202 = pending verification, which is correct behavior
    expect($response->statusCode())->toBeIn([200, 201, 202]);
});

it('rejects submission when rate limit exceeded', function (): void {
    $post = createPost(id: 1, title: 'Test Post', slug: 'test-post');
    $postRepository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository();
    $honeypotValidator = createMockHoneypotValidator();
    $rateLimiter = createMockRateLimiter(isAllowed: false, secondsRemaining: 30);
    $verificationService = createMockVerificationService();
    $blogConfig = createMockBlogConfig();
    $eventDispatcher = createMockEventDispatcher();

    $controller = new CommentController(
        postRepository: $postRepository,
        commentRepository: $commentRepository,
        honeypotValidator: $honeypotValidator,
        rateLimiter: $rateLimiter,
        verificationService: $verificationService,
        blogConfig: $blogConfig,
        eventDispatcher: $eventDispatcher,
    );

    $response = $controller->submit(
        slug: 'test-post',
        authorName: 'John Doe',
        authorEmail: 'john@example.com',
        content: 'This is a valid comment content.',
        honeypot: '',
        ipAddress: '127.0.0.1',
    );

    expect($response->statusCode())->toBe(429);

    $body = json_decode($response->body(), true);
    expect($body)->toHaveKey('error')
        ->and(strtolower($body['error']))->toContain('rate');
});

it('returns rate limit wait time when rate limited', function (): void {
    $post = createPost(id: 1, title: 'Test Post', slug: 'test-post');
    $postRepository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository();
    $honeypotValidator = createMockHoneypotValidator();
    $rateLimiter = createMockRateLimiter(isAllowed: false, secondsRemaining: 25);
    $verificationService = createMockVerificationService();
    $blogConfig = createMockBlogConfig();
    $eventDispatcher = createMockEventDispatcher();

    $controller = new CommentController(
        postRepository: $postRepository,
        commentRepository: $commentRepository,
        honeypotValidator: $honeypotValidator,
        rateLimiter: $rateLimiter,
        verificationService: $verificationService,
        blogConfig: $blogConfig,
        eventDispatcher: $eventDispatcher,
    );

    $response = $controller->submit(
        slug: 'test-post',
        authorName: 'John Doe',
        authorEmail: 'john@example.com',
        content: 'This is a valid comment content.',
        honeypot: '',
        ipAddress: '127.0.0.1',
    );

    expect($response->statusCode())->toBe(429);

    $body = json_decode($response->body(), true);
    expect($body)->toHaveKey('retry_after')
        ->and($body['retry_after'])->toBe(25);
});

it('auto-approves comment when valid browser token exists', function (): void {
    $post = createPost(id: 1, title: 'Test Post', slug: 'test-post');
    $postRepository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository();
    $honeypotValidator = createMockHoneypotValidator();
    $rateLimiter = createMockRateLimiter();
    $verificationService = createMockVerificationService(shouldAutoApprove: true);
    $blogConfig = createMockBlogConfig();
    $eventDispatcher = createMockEventDispatcher();

    $controller = new CommentController(
        postRepository: $postRepository,
        commentRepository: $commentRepository,
        honeypotValidator: $honeypotValidator,
        rateLimiter: $rateLimiter,
        verificationService: $verificationService,
        blogConfig: $blogConfig,
        eventDispatcher: $eventDispatcher,
    );

    $response = $controller->submit(
        slug: 'test-post',
        authorName: 'John Doe',
        authorEmail: 'john@example.com',
        content: 'This is a valid comment content.',
        honeypot: '',
        ipAddress: '127.0.0.1',
        browserToken: 'valid-browser-token',
    );

    expect($response->statusCode())->toBe(201);

    // Verify comment was saved with verified status
    expect($commentRepository->savedComments)->toHaveCount(1);
    $savedComment = $commentRepository->savedComments[0];
    expect($savedComment->status)->toBe(CommentStatus::Verified)
        ->and($savedComment->authorName)->toBe('John Doe')
        ->and($savedComment->authorEmail)->toBe('john@example.com')
        ->and($savedComment->content)->toBe('This is a valid comment content.')
        ->and($savedComment->postId)->toBe(1);
});

it('creates pending comment and sends verification email when no token', function (): void {
    $post = createPost(id: 1, title: 'Test Post', slug: 'test-post');
    $postRepository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository();
    $honeypotValidator = createMockHoneypotValidator();
    $rateLimiter = createMockRateLimiter();
    $verificationService = createMockVerificationService(shouldAutoApprove: false);
    $blogConfig = createMockBlogConfig();
    $eventDispatcher = createMockEventDispatcher();

    $controller = new CommentController(
        postRepository: $postRepository,
        commentRepository: $commentRepository,
        honeypotValidator: $honeypotValidator,
        rateLimiter: $rateLimiter,
        verificationService: $verificationService,
        blogConfig: $blogConfig,
        eventDispatcher: $eventDispatcher,
    );

    $response = $controller->submit(
        slug: 'test-post',
        authorName: 'John Doe',
        authorEmail: 'john@example.com',
        content: 'This is a valid comment content.',
        honeypot: '',
        ipAddress: '127.0.0.1',
        // No browser token
    );

    expect($response->statusCode())->toBe(202);

    // Verify comment was saved with pending status
    expect($commentRepository->savedComments)->toHaveCount(1);
    $savedComment = $commentRepository->savedComments[0];
    expect($savedComment->status)->toBe(CommentStatus::Pending);

    // Verify verification email was sent
    expect($verificationService->sentVerificationEmails)->toHaveCount(1);
});

it('accepts optional parent_id for threaded replies', function (): void {
    $post = createPost(id: 1, title: 'Test Post', slug: 'test-post');
    $parentComment = new Comment();
    $parentComment->id = 5;
    $parentComment->postId = 1;
    $parentComment->authorName = 'Parent Author';
    $parentComment->authorEmail = 'parent@example.com';
    $parentComment->content = 'Parent comment content';
    $parentComment->status = CommentStatus::Verified;

    $postRepository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository(findResult: $parentComment);
    $honeypotValidator = createMockHoneypotValidator();
    $rateLimiter = createMockRateLimiter();
    $verificationService = createMockVerificationService(shouldAutoApprove: true);
    $blogConfig = createMockBlogConfig();
    $eventDispatcher = createMockEventDispatcher();

    $controller = new CommentController(
        postRepository: $postRepository,
        commentRepository: $commentRepository,
        honeypotValidator: $honeypotValidator,
        rateLimiter: $rateLimiter,
        verificationService: $verificationService,
        blogConfig: $blogConfig,
        eventDispatcher: $eventDispatcher,
    );

    $response = $controller->submit(
        slug: 'test-post',
        authorName: 'John Doe',
        authorEmail: 'john@example.com',
        content: 'This is a reply comment.',
        honeypot: '',
        ipAddress: '127.0.0.1',
        parentId: 5,
        browserToken: 'valid-token',
    );

    expect($response->statusCode())->toBe(201);

    // Verify comment was saved with parent_id
    expect($commentRepository->savedComments)->toHaveCount(1);
    $savedComment = $commentRepository->savedComments[0];
    expect($savedComment->parentId)->toBe(5);
});

it('validates parent comment belongs to same post', function (): void {
    $post = createPost(id: 1, title: 'Test Post', slug: 'test-post');
    $parentComment = new Comment();
    $parentComment->id = 5;
    $parentComment->postId = 999; // Different post!
    $parentComment->authorName = 'Parent Author';
    $parentComment->authorEmail = 'parent@example.com';
    $parentComment->content = 'Parent comment content';
    $parentComment->status = CommentStatus::Verified;

    $postRepository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository(findResult: $parentComment);
    $honeypotValidator = createMockHoneypotValidator();
    $rateLimiter = createMockRateLimiter();
    $verificationService = createMockVerificationService();
    $blogConfig = createMockBlogConfig();
    $eventDispatcher = createMockEventDispatcher();

    $controller = new CommentController(
        postRepository: $postRepository,
        commentRepository: $commentRepository,
        honeypotValidator: $honeypotValidator,
        rateLimiter: $rateLimiter,
        verificationService: $verificationService,
        blogConfig: $blogConfig,
        eventDispatcher: $eventDispatcher,
    );

    $response = $controller->submit(
        slug: 'test-post',
        authorName: 'John Doe',
        authorEmail: 'john@example.com',
        content: 'This is a reply comment.',
        honeypot: '',
        ipAddress: '127.0.0.1',
        parentId: 5,
    );

    expect($response->statusCode())->toBe(422);

    $body = json_decode($response->body(), true);
    expect($body['errors'])->toHaveKey('parent_id');
});

it('validates reply does not exceed configured max depth', function (): void {
    $post = createPost(id: 1, title: 'Test Post', slug: 'test-post');
    $parentComment = new Comment();
    $parentComment->id = 5;
    $parentComment->postId = 1;
    $parentComment->authorName = 'Parent Author';
    $parentComment->authorEmail = 'parent@example.com';
    $parentComment->content = 'Parent comment content';
    $parentComment->status = CommentStatus::Verified;

    $postRepository = createMockPostRepository(findBySlugResult: $post);
    // Mock returns depth of 5 (at max)
    $commentRepository = createMockCommentRepository(findResult: $parentComment, calculateDepthResult: 5);
    $honeypotValidator = createMockHoneypotValidator();
    $rateLimiter = createMockRateLimiter();
    $verificationService = createMockVerificationService();
    // Max depth is 5, so replying to a comment at depth 5 would exceed it
    $blogConfig = createMockBlogConfig(maxDepth: 5);
    $eventDispatcher = createMockEventDispatcher();

    $controller = new CommentController(
        postRepository: $postRepository,
        commentRepository: $commentRepository,
        honeypotValidator: $honeypotValidator,
        rateLimiter: $rateLimiter,
        verificationService: $verificationService,
        blogConfig: $blogConfig,
        eventDispatcher: $eventDispatcher,
    );

    $response = $controller->submit(
        slug: 'test-post',
        authorName: 'John Doe',
        authorEmail: 'john@example.com',
        content: 'This is a reply comment.',
        honeypot: '',
        ipAddress: '127.0.0.1',
        parentId: 5,
    );

    expect($response->statusCode())->toBe(422);

    $body = json_decode($response->body(), true);
    expect($body['errors'])->toHaveKey('parent_id')
        ->and(strtolower($body['errors']['parent_id']))->toContain('depth');
});

it('dispatches CommentCreated event', function (): void {
    $post = createPost(id: 1, title: 'Test Post', slug: 'test-post');
    $postRepository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository();
    $honeypotValidator = createMockHoneypotValidator();
    $rateLimiter = createMockRateLimiter();
    $verificationService = createMockVerificationService(shouldAutoApprove: true);
    $blogConfig = createMockBlogConfig();
    $eventDispatcher = createMockEventDispatcher();

    $controller = new CommentController(
        postRepository: $postRepository,
        commentRepository: $commentRepository,
        honeypotValidator: $honeypotValidator,
        rateLimiter: $rateLimiter,
        verificationService: $verificationService,
        blogConfig: $blogConfig,
        eventDispatcher: $eventDispatcher,
    );

    $controller->submit(
        slug: 'test-post',
        authorName: 'John Doe',
        authorEmail: 'john@example.com',
        content: 'This is a valid comment content.',
        honeypot: '',
        ipAddress: '127.0.0.1',
        browserToken: 'valid-token',
    );

    expect($eventDispatcher->dispatchedEvents)->toHaveCount(1);
    $event = $eventDispatcher->dispatchedEvents[0];
    expect($event)->toBeInstanceOf(CommentCreated::class);
});

it('returns success response with next steps', function (): void {
    $post = createPost(id: 1, title: 'Test Post', slug: 'test-post');
    $postRepository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository();
    $honeypotValidator = createMockHoneypotValidator();
    $rateLimiter = createMockRateLimiter();
    $verificationService = createMockVerificationService(shouldAutoApprove: false);
    $blogConfig = createMockBlogConfig();
    $eventDispatcher = createMockEventDispatcher();

    $controller = new CommentController(
        postRepository: $postRepository,
        commentRepository: $commentRepository,
        honeypotValidator: $honeypotValidator,
        rateLimiter: $rateLimiter,
        verificationService: $verificationService,
        blogConfig: $blogConfig,
        eventDispatcher: $eventDispatcher,
    );

    $response = $controller->submit(
        slug: 'test-post',
        authorName: 'John Doe',
        authorEmail: 'john@example.com',
        content: 'This is a valid comment content.',
        honeypot: '',
        ipAddress: '127.0.0.1',
    );

    // 202 Accepted for pending verification
    expect($response->statusCode())->toBe(202);

    $body = json_decode($response->body(), true);
    expect($body)->toHaveKey('status')
        ->and($body['status'])->toBe('pending')
        ->and($body)->toHaveKey('message');
});

// Helper functions

function createPost(
    int $id,
    string $title,
    string $slug,
    PostStatus $status = PostStatus::Published,
): Post {
    $post = new Post(title: $title, content: 'Content', authorId: 1);
    $post->id = $id;
    $post->slug = $slug;
    $post->status = $status;
    $post->publishedAt = '2024-01-01 12:00:00';

    return $post;
}

function createMockPostRepository(
    ?Post $findBySlugResult = null,
): PostRepositoryInterface {
    return new readonly class ($findBySlugResult) implements PostRepositoryInterface
    {
        public function __construct(
            private ?Post $findBySlugResult,
        ) {}

        public function findBySlug(
            string $slug,
        ): ?Post {
            return $this->findBySlugResult;
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

        public function find(
            int $id,
        ): ?Entity {
            return null;
        }

        public function findOrFail(
            int $id,
        ): Entity {
            throw RepositoryException::notFound(Post::class, $id);
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
        ): ?Entity {
            return null;
        }

        public function save(
            Entity $entity,
        ): void {}

        public function delete(
            Entity $entity,
        ): void {}
    };
}

function createMockCommentRepository(
    ?Comment $findResult = null,
    int $calculateDepthResult = 0,
): CommentRepositoryInterface {
    return new class ($findResult, $calculateDepthResult) implements CommentRepositoryInterface
    {
        /** @var array<Comment> */
        public array $savedComments = [];

        public function __construct(
            private readonly ?Comment $findResult,
            private readonly int $calculateDepthResult,
        ) {}

        public function find(
            int $id,
        ): ?Comment {
            return $this->findResult;
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
            return $this->calculateDepthResult;
        }

        public function findOrFail(
            int $id,
        ): Entity {
            throw RepositoryException::notFound(Comment::class, $id);
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
        ): ?Entity {
            return null;
        }

        public function save(
            Entity $entity,
        ): void {
            if ($entity instanceof Comment) {
                $this->savedComments[] = $entity;
            }
        }

        public function delete(
            Entity $entity,
        ): void {}
    };
}

function createMockHoneypotValidator(
    bool $validateResult = true,
): HoneypotValidatorInterface {
    return new readonly class ($validateResult) implements HoneypotValidatorInterface
    {
        public function __construct(
            private bool $validateResult,
        ) {}

        public function getFieldName(): string
        {
            return 'website';
        }

        public function validate(
            string $honeypotValue,
        ): bool {
            return $this->validateResult;
        }

        public function renderField(): string
        {
            return '<input type="text" name="website" />';
        }
    };
}

function createMockRateLimiter(
    bool $isAllowed = true,
    int $secondsRemaining = 0,
): CommentRateLimiterInterface {
    return new readonly class ($isAllowed, $secondsRemaining) implements CommentRateLimiterInterface
    {
        public function __construct(
            private bool $isAllowed,
            private int $secondsRemaining,
        ) {}

        public function isAllowed(
            string $ipAddress,
            ?string $email = null,
        ): bool {
            return $this->isAllowed;
        }

        public function recordSubmission(
            string $ipAddress,
            ?string $email = null,
        ): void {}

        public function getSecondsRemaining(
            string $ipAddress,
            ?string $email = null,
        ): int {
            return $this->secondsRemaining;
        }
    };
}

function createMockVerificationService(
    bool $shouldAutoApprove = false,
): CommentVerificationServiceInterface {
    return new class ($shouldAutoApprove) implements CommentVerificationServiceInterface
    {
        /** @var array<Comment> */
        public array $sentVerificationEmails = [];

        public function __construct(
            private readonly bool $shouldAutoApprove,
        ) {}

        public function sendVerificationEmail(
            CommentInterface $comment,
        ): string {
            $this->sentVerificationEmails[] = $comment;

            return 'test-verification-token';
        }

        public function verifyByToken(
            string $token,
        ): VerificationResult {
            return new VerificationResult(
                browserToken: 'test-browser-token',
                postSlug: 'test-post',
                commentId: 1,
            );
        }

        public function isBrowserTokenValid(
            string $browserToken,
            string $email,
        ): bool {
            return false;
        }

        public function shouldAutoApprove(
            string $email,
            ?string $browserToken,
        ): bool {
            return $this->shouldAutoApprove;
        }

        public function getCookieName(): string
        {
            return 'blog_verified';
        }

        public function getCookieLifetimeDays(): int
        {
            return 365;
        }
    };
}

function createMockBlogConfig(
    int $maxDepth = 5,
): BlogConfigInterface {
    return new readonly class ($maxDepth) implements BlogConfigInterface
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

        public function getSiteName(): string
        {
            return 'Test Blog';
        }
    };
}

function createMockEventDispatcher(): EventDispatcherInterface
{
    return new class () implements EventDispatcherInterface
    {
        /** @var array<Event> */
        public array $dispatchedEvents = [];

        public function dispatch(
            Event $event,
        ): void {
            $this->dispatchedEvents[] = $event;
        }
    };
}

function createMockCsrfValidator(
    bool $validateResult = true,
): CsrfValidatorInterface {
    return new readonly class ($validateResult) implements CsrfValidatorInterface
    {
        public function __construct(
            private bool $validateResult,
        ) {}

        public function validate(
            string $token,
        ): bool {
            return $this->validateResult;
        }

        public function generate(): string
        {
            return 'test-csrf-token';
        }
    };
}
