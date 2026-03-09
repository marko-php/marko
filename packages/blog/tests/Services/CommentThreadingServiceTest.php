<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Services;

use Marko\Blog\Config\BlogConfigInterface;
use Marko\Blog\Entity\Comment;
use Marko\Blog\Repositories\CommentRepositoryInterface;
use Marko\Blog\Services\CommentThreadingService;
use Marko\Blog\Services\CommentThreadingServiceInterface;
use Marko\Database\Entity\Entity;
use ReflectionClass;
use RuntimeException;

it('defines getThreadedComments method accepting post id', function (): void {
    $reflection = new ReflectionClass(CommentThreadingServiceInterface::class);

    expect($reflection->hasMethod('getThreadedComments'))->toBeTrue();

    $method = $reflection->getMethod('getThreadedComments');
    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('postId')
        ->and($parameters[0]->getType()->getName())->toBe('int');
});

it('defines calculateDepth method accepting comment id', function (): void {
    $reflection = new ReflectionClass(CommentThreadingServiceInterface::class);

    expect($reflection->hasMethod('calculateDepth'))->toBeTrue();

    $method = $reflection->getMethod('calculateDepth');
    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('commentId')
        ->and($parameters[0]->getType()->getName())->toBe('int');
});

it('returns empty array when no comments exist for post', function (): void {
    $commentRepository = createThreadingMockCommentRepository([]);
    $blogConfig = createThreadingMockBlogConfig();

    $service = new CommentThreadingService($commentRepository, $blogConfig);

    expect($service->getThreadedComments(1))->toBeEmpty();
});

it('returns root comments with no children as flat list', function (): void {
    $comments = [
        createThreadingComment(1, null, 'First root'),
        createThreadingComment(2, null, 'Second root'),
    ];
    $commentRepository = createThreadingMockCommentRepository($comments);
    $blogConfig = createThreadingMockBlogConfig();

    $service = new CommentThreadingService($commentRepository, $blogConfig);
    $result = $service->getThreadedComments(1);

    expect($result)->toHaveCount(2)
        ->and($result[0]->content)->toBe('First root')
        ->and($result[1]->content)->toBe('Second root');
});

it('nests child comments under their parent', function (): void {
    $comments = [
        createThreadingComment(1, null, 'Root comment'),
        createThreadingComment(2, 1, 'Child comment'),
        createThreadingComment(3, null, 'Another root'),
    ];
    $commentRepository = createThreadingMockCommentRepository($comments);
    $blogConfig = createThreadingMockBlogConfig();

    $service = new CommentThreadingService($commentRepository, $blogConfig);
    $result = $service->getThreadedComments(1);

    expect($result)->toHaveCount(2)
        ->and($result[0]->content)->toBe('Root comment')
        ->and($result[0]->getChildren())->toHaveCount(1)
        ->and($result[0]->getChildren()[0]->content)->toBe('Child comment')
        ->and($result[1]->content)->toBe('Another root')
        ->and($result[1]->getChildren())->toBeEmpty();
});

it('respects max depth configuration from BlogConfig', function (): void {
    // Structure (maxDepth = 1):
    // - Comment 1 (depth 0)
    //   - Comment 2 (depth 1) - max
    //     - Comment 3 (would be depth 2, flattened to depth 1 under comment 1)
    $comments = [
        createThreadingComment(1, null, 'Root'),
        createThreadingComment(2, 1, 'Depth 1'),
        createThreadingComment(3, 2, 'Would be depth 2, flattened'),
    ];
    $commentRepository = createThreadingMockCommentRepository($comments);
    $blogConfig = createThreadingMockBlogConfig(maxDepth: 1);

    $service = new CommentThreadingService($commentRepository, $blogConfig);
    $result = $service->getThreadedComments(1);

    expect($result)->toHaveCount(1)
        ->and($result[0]->content)->toBe('Root');

    $depth1 = $result[0]->getChildren();
    expect($depth1)->toHaveCount(2)
        ->and($depth1[0]->content)->toBe('Depth 1')
        ->and($depth1[1]->content)->toBe('Would be depth 2, flattened');
});

it('flattens comments exceeding max depth to the max depth level', function (): void {
    // maxDepth = 2: root=0, child=1, grandchild=2
    $comments = [
        createThreadingComment(1, null, 'Root (depth 0)'),
        createThreadingComment(2, 1, 'Depth 1'),
        createThreadingComment(3, 2, 'Depth 2 (max)'),
        createThreadingComment(4, 3, 'Would be depth 3, flattened to depth 2'),
    ];
    $commentRepository = createThreadingMockCommentRepository($comments);
    $blogConfig = createThreadingMockBlogConfig(maxDepth: 2);

    $service = new CommentThreadingService($commentRepository, $blogConfig);
    $result = $service->getThreadedComments(1);

    expect($result)->toHaveCount(1);

    $depth1 = $result[0]->getChildren();
    expect($depth1)->toHaveCount(1);

    $depth2 = $depth1[0]->getChildren();
    expect($depth2)->toHaveCount(2)
        ->and($depth2[0]->content)->toBe('Depth 2 (max)')
        ->and($depth2[1]->content)->toBe('Would be depth 3, flattened to depth 2');

    expect($depth2[0]->getChildren())->toBeEmpty();
});

it('calculates depth 0 for root comments', function (): void {
    $comments = [1 => createThreadingCommentWithId(1, null, 'Root')];
    $commentRepository = createThreadingMockCommentRepositoryForFindById($comments);
    $blogConfig = createThreadingMockBlogConfig();

    $service = new CommentThreadingService($commentRepository, $blogConfig);

    expect($service->calculateDepth(1))->toBe(0);
});

it('calculates depth 1 for direct replies', function (): void {
    $comments = [
        1 => createThreadingCommentWithId(1, null, 'Root'),
        2 => createThreadingCommentWithId(2, 1, 'Reply'),
    ];
    $commentRepository = createThreadingMockCommentRepositoryForFindById($comments);
    $blogConfig = createThreadingMockBlogConfig();

    $service = new CommentThreadingService($commentRepository, $blogConfig);

    expect($service->calculateDepth(2))->toBe(1);
});

it('calculates depth correctly for deeply nested comments', function (): void {
    $comments = [
        1 => createThreadingCommentWithId(1, null, 'Root'),
        2 => createThreadingCommentWithId(2, 1, 'Depth 1'),
        3 => createThreadingCommentWithId(3, 2, 'Depth 2'),
    ];
    $commentRepository = createThreadingMockCommentRepositoryForFindById($comments);
    $blogConfig = createThreadingMockBlogConfig();

    $service = new CommentThreadingService($commentRepository, $blogConfig);

    expect($service->calculateDepth(3))->toBe(2)
        ->and($service->calculateDepth(2))->toBe(1)
        ->and($service->calculateDepth(1))->toBe(0);
});

it('returns 0 when comment does not exist', function (): void {
    $commentRepository = createThreadingMockCommentRepositoryForFindById([]);
    $blogConfig = createThreadingMockBlogConfig();

    $service = new CommentThreadingService($commentRepository, $blogConfig);

    expect($service->calculateDepth(999))->toBe(0);
});

// Helper functions

function createThreadingComment(
    int $id,
    ?int $parentId,
    string $content,
): Comment {
    $comment = new Comment();
    $comment->id = $id;
    $comment->parentId = $parentId;
    $comment->postId = 1;
    $comment->name = 'Test Author';
    $comment->email = 'test@example.com';
    $comment->content = $content;
    $comment->createdAt = '2024-01-01 10:00:00';

    return $comment;
}

function createThreadingCommentWithId(
    int $id,
    ?int $parentId,
    string $content,
): Comment {
    return createThreadingComment($id, $parentId, $content);
}

/**
 * @param array<Comment> $verifiedComments
 */
function createThreadingMockCommentRepository(
    array $verifiedComments,
): CommentRepositoryInterface {
    return new class ($verifiedComments) implements CommentRepositoryInterface
    {
        /**
         * @param array<Comment> $verifiedComments
         */
        public function __construct(
            private readonly array $verifiedComments,
        ) {}

        public function find(int $id): ?Comment
        {
            return null;
        }

        public function findVerifiedForPost(int $postId): array
        {
            return $this->verifiedComments;
        }

        public function findPendingForPost(int $postId): array
        {
            return [];
        }

        public function countForPost(int $postId): int
        {
            return 0;
        }

        public function countVerifiedForPost(int $postId): int
        {
            return count($this->verifiedComments);
        }

        public function findByEmail(string $email): array
        {
            return [];
        }

        public function findOrFail(int $id): Comment
        {
            throw new RuntimeException('Not found');
        }

        public function findAll(): array
        {
            return [];
        }

        public function findBy(array $criteria): array
        {
            return [];
        }

        public function findOneBy(array $criteria): ?Comment
        {
            return null;
        }

        public function existsBy(array $criteria): bool
        {
            return false;
        }

        public function save(Entity $entity): void {}

        public function delete(Entity $entity): void {}
    };
}

/**
 * @param array<int, Comment> $commentsById
 */
function createThreadingMockCommentRepositoryForFindById(
    array $commentsById,
): CommentRepositoryInterface {
    return new class ($commentsById) implements CommentRepositoryInterface
    {
        /**
         * @param array<int, Comment> $commentsById
         */
        public function __construct(
            private readonly array $commentsById,
        ) {}

        public function find(int $id): ?Comment
        {
            return $this->commentsById[$id] ?? null;
        }

        public function findVerifiedForPost(int $postId): array
        {
            return array_values($this->commentsById);
        }

        public function findPendingForPost(int $postId): array
        {
            return [];
        }

        public function countForPost(int $postId): int
        {
            return 0;
        }

        public function countVerifiedForPost(int $postId): int
        {
            return count($this->commentsById);
        }

        public function findByEmail(string $email): array
        {
            return [];
        }

        public function findOrFail(int $id): Comment
        {
            throw new RuntimeException('Not found');
        }

        public function findAll(): array
        {
            return [];
        }

        public function findBy(array $criteria): array
        {
            return [];
        }

        public function findOneBy(array $criteria): ?Comment
        {
            return null;
        }

        public function existsBy(array $criteria): bool
        {
            return false;
        }

        public function save(Entity $entity): void {}

        public function delete(Entity $entity): void {}
    };
}

function createThreadingMockBlogConfig(
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
