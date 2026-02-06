<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Unit\Admin\Api;

use Closure;
use Marko\Blog\Admin\Api\CommentApiController;
use Marko\Blog\Admin\Api\PostApiController;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Comment;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\CommentStatus;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Repositories\CommentRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Routing\Http\Request;

it('returns ApiResponse format for all responses', function (): void {
    // PostApiController - success response has data and meta
    $post = new Post(title: 'Test', content: 'Content', authorId: 1);
    $post->id = 1;
    $post->slug = 'test';
    $post->createdAt = '2024-01-01 12:00:00';

    $postRepo = createResponseTestPostRepo(findResult: $post);
    $controller = new PostApiController(
        postRepository: $postRepo,
        paginationService: createResponseTestPagination(),
        slugGenerator: createResponseTestSlugGenerator(),
    );

    // Index (paginated) has data and meta
    $indexResponse = $controller->index(new Request(query: ['page' => '1']));
    $indexBody = json_decode($indexResponse->body(), true);
    expect($indexBody)->toHaveKey('data')
        ->and($indexBody)->toHaveKey('meta')
        ->and($indexResponse->headers()['Content-Type'])->toBe('application/json');

    // Show has data and meta
    $showResponse = $controller->show(1);
    $showBody = json_decode($showResponse->body(), true);
    expect($showBody)->toHaveKey('data')
        ->and($showBody)->toHaveKey('meta');

    // Store (success) has data and meta
    $storeResponse = $controller->store(new Request(post: [
        'title' => 'New Post',
        'content' => 'Some content',
        'author_id' => '1',
    ]));
    $storeBody = json_decode($storeResponse->body(), true);
    expect($storeBody)->toHaveKey('data')
        ->and($storeBody)->toHaveKey('meta');

    // Store (validation) has errors
    $errorResponse = $controller->store(new Request(post: [
        'title' => '',
        'content' => '',
        'author_id' => '0',
    ]));
    $errorBody = json_decode($errorResponse->body(), true);
    expect($errorBody)->toHaveKey('errors');

    // Not found has errors
    $notFoundRepo = createResponseTestPostRepo();
    $notFoundController = new PostApiController(
        postRepository: $notFoundRepo,
        paginationService: createResponseTestPagination(),
        slugGenerator: createResponseTestSlugGenerator(),
    );
    $notFoundResponse = $notFoundController->show(999);
    $notFoundBody = json_decode($notFoundResponse->body(), true);
    expect($notFoundBody)->toHaveKey('errors')
        ->and($notFoundResponse->statusCode())->toBe(404);

    // CommentApiController - verify has data and meta
    $comment = new Comment();
    $comment->id = 1;
    $comment->postId = 1;
    $comment->name = 'John';
    $comment->email = 'john@test.com';
    $comment->content = 'Great!';
    $comment->status = CommentStatus::Pending;
    $comment->createdAt = '2024-01-01 12:00:00';

    $commentRepo = createResponseTestCommentRepo(findResult: $comment);
    $commentController = new CommentApiController(
        commentRepository: $commentRepo,
    );

    $verifyResponse = $commentController->verify(1);
    $verifyBody = json_decode($verifyResponse->body(), true);
    expect($verifyBody)->toHaveKey('data')
        ->and($verifyBody)->toHaveKey('meta');

    // Delete has data and meta
    $deleteResponse = $commentController->destroy(1);
    $deleteBody = json_decode($deleteResponse->body(), true);
    expect($deleteBody)->toHaveKey('data')
        ->and($deleteBody)->toHaveKey('meta');
});

// Helper functions for this test file

function createResponseTestPostRepo(
    array $findAllResult = [],
    ?Post $findResult = null,
): PostRepositoryInterface {
    return new class ($findAllResult, $findResult) implements PostRepositoryInterface
    {
        private array $savedEntities = [];

        public function __construct(
            private array $findAllResult,
            private ?Post $findResult,
        ) {}

        public function find(
            int $id,
        ): ?Entity {
            return $this->findResult;
        }

        public function findOrFail(
            int $id,
        ): Entity {
            if ($this->findResult === null) {
                throw RepositoryException::entityNotFound(Post::class, $id);
            }

            return $this->findResult;
        }

        public function findAll(): array
        {
            return $this->findAllResult;
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
            if ($entity instanceof Post && $entity->id === null) {
                $entity->id = 99;
            }
            $this->savedEntities[] = $entity;
        }

        public function delete(Entity $entity): void {}

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
    };
}

function createResponseTestCommentRepo(
    array $findAllResult = [],
    ?Comment $findResult = null,
): CommentRepositoryInterface {
    return new class ($findAllResult, $findResult) implements CommentRepositoryInterface
    {
        public function __construct(
            private array $findAllResult,
            private ?Comment $findResult,
        ) {}

        public function find(
            int $id,
        ): ?Comment {
            return $this->findResult;
        }

        public function findOrFail(
            int $id,
        ): Entity {
            if ($this->findResult === null) {
                throw RepositoryException::entityNotFound(Comment::class, $id);
            }

            return $this->findResult;
        }

        public function findAll(): array
        {
            return $this->findAllResult;
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

        public function save(Entity $entity): void {}

        public function delete(Entity $entity): void {}

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

        public function findByEmail(
            string $email,
        ): array {
            return [];
        }

        public function calculateDepth(
            int $commentId,
        ): int {
            return 0;
        }
    };
}

function createResponseTestPagination(): PaginationServiceInterface
{
    return new class () implements PaginationServiceInterface
    {
        public function paginate(
            array $items,
            int $totalItems,
            int $currentPage,
            ?int $perPage = null,
        ): PaginatedResult {
            return new PaginatedResult(
                items: $items,
                currentPage: $currentPage,
                totalItems: $totalItems,
                perPage: 10,
                totalPages: 0,
                hasPreviousPage: false,
                hasNextPage: false,
                pageNumbers: [1],
            );
        }

        public function calculateOffset(
            int $page,
            ?int $perPage = null,
        ): int {
            return 0;
        }

        public function getPerPage(): int
        {
            return 10;
        }
    };
}

function createResponseTestSlugGenerator(): SlugGeneratorInterface
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
