<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Unit\Admin\Controllers;

use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Admin\Controllers\CommentAdminController;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Comment;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\CommentStatus;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Events\Comment\CommentDeleted;
use Marko\Blog\Events\Comment\CommentVerified;
use Marko\Blog\Repositories\CommentRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post as PostRoute;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\View\ViewInterface;
use ReflectionClass;

it('creates CommentAdminController with list, view, verify, delete actions', function (): void {
    $reflection = new ReflectionClass(CommentAdminController::class);

    expect($reflection->hasMethod('index'))->toBeTrue()
        ->and($reflection->hasMethod('show'))->toBeTrue()
        ->and($reflection->hasMethod('verify'))->toBeTrue()
        ->and($reflection->hasMethod('destroy'))->toBeTrue();
});

it('requires blog.comments.view permission for comment list', function (): void {
    $reflection = new ReflectionClass(CommentAdminController::class);
    $method = $reflection->getMethod('index');

    // Check route attribute
    $routeAttrs = $method->getAttributes(Get::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/comments');

    // Check permission attribute
    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.comments.view');

    // Test actual behavior - returns paginated comments
    $comments = [
        createTestCommentEntity(1, 1, 'Alice', 'alice@example.com', 'Great post!'),
        createTestCommentEntity(2, 1, 'Bob', 'bob@example.com', 'Nice article.'),
    ];
    $capturedData = [];
    $controller = createCommentController(
        comments: $comments,
        totalComments: 2,
        capturedData: $capturedData,
    );

    $request = new Request(query: ['page' => '1']);
    $response = $controller->index($request);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/comment/index')
        ->and($capturedData)->toHaveKey('comments')
        ->and($capturedData['comments'])->toBeInstanceOf(PaginatedResult::class)
        ->and($capturedData['comments']->items)->toHaveCount(2);
});

it('shows comment detail on GET /admin/blog/comments/{id} with blog.comments.view permission', function (): void {
    $reflection = new ReflectionClass(CommentAdminController::class);
    $method = $reflection->getMethod('show');

    $routeAttrs = $method->getAttributes(Get::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/comments/{id}');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.comments.view');

    $comment = createTestCommentEntity(1, 1, 'Alice', 'alice@example.com', 'Great post!');
    $capturedData = [];
    $controller = createCommentController(
        findComment: $comment,
        capturedData: $capturedData,
    );

    $response = $controller->show(1);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/comment/show')
        ->and($capturedData)->toHaveKey('comment')
        ->and($capturedData['comment']->getName())->toBe('Alice');
});

it('returns 404 when viewing non-existent comment', function (): void {
    $controller = createCommentController();

    $response = $controller->show(999);

    expect($response->statusCode())->toBe(404)
        ->and($response->body())->toContain('not found');
});

it('verifies pending comment via CommentAdminController verify action', function (): void {
    $reflection = new ReflectionClass(CommentAdminController::class);
    $method = $reflection->getMethod('verify');

    $routeAttrs = $method->getAttributes(PostRoute::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/comments/{id}/verify');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.comments.verify');

    $comment = createTestCommentEntity(1, 5, 'Alice', 'alice@example.com', 'Great post!', CommentStatus::Pending);
    $post = createTestCommentPost(5, 'Test Post', 'test-post');
    $savedEntities = [];
    $controller = createCommentController(
        findComment: $comment,
        findPost: $post,
        savedEntities: $savedEntities,
    );

    $response = $controller->verify(1);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($savedEntities)->toHaveCount(1)
        ->and($savedEntities[0]->status)->toBe(CommentStatus::Verified)
        ->and($savedEntities[0]->verifiedAt)->not->toBeNull();
});

it('returns 404 when verifying non-existent comment', function (): void {
    $controller = createCommentController();

    $response = $controller->verify(999);

    expect($response->statusCode())->toBe(404)
        ->and($response->body())->toContain('not found');
});

it('deletes comment on DELETE /admin/blog/comments/{id} with blog.comments.delete permission', function (): void {
    $reflection = new ReflectionClass(CommentAdminController::class);
    $method = $reflection->getMethod('destroy');

    $routeAttrs = $method->getAttributes(Delete::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/comments/{id}');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.comments.delete');

    $comment = createTestCommentEntity(1, 5, 'Alice', 'alice@example.com', 'Spam!');
    $post = createTestCommentPost(5, 'Test Post', 'test-post');
    $deletedEntities = [];
    $controller = createCommentController(
        findComment: $comment,
        findPost: $post,
        deletedEntities: $deletedEntities,
    );

    $response = $controller->destroy(1);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($deletedEntities)->toHaveCount(1)
        ->and($deletedEntities[0]->id)->toBe(1);
});

it('applies AdminAuthMiddleware to CommentAdminController', function (): void {
    $reflection = new ReflectionClass(CommentAdminController::class);

    $middlewareAttrs = $reflection->getAttributes(Middleware::class);
    expect($middlewareAttrs)->toHaveCount(1);

    $middleware = $middlewareAttrs[0]->newInstance();
    expect($middleware->middleware)->toContain(AdminAuthMiddleware::class);
});

it('dispatches CommentVerified and CommentDeleted events', function (): void {
    // Test CommentVerified on verify
    $comment = createTestCommentEntity(1, 5, 'Alice', 'alice@example.com', 'Great post!', CommentStatus::Pending);
    $post = createTestCommentPost(5, 'Test Post', 'test-post');
    $dispatcher = new FakeEventDispatcher();
    $savedEntities = [];
    $controller = createCommentController(
        findComment: $comment,
        findPost: $post,
        savedEntities: $savedEntities,
        eventDispatcher: $dispatcher,
    );

    $controller->verify(1);

    expect($dispatcher->dispatched)->toHaveCount(1)
        ->and($dispatcher->dispatched[0])->toBeInstanceOf(CommentVerified::class)
        ->and($dispatcher->dispatched[0]->getComment()->getName())->toBe('Alice')
        ->and($dispatcher->dispatched[0]->getVerificationMethod())->toBe('admin');

    // Test CommentDeleted on destroy
    $comment2 = createTestCommentEntity(2, 5, 'Bob', 'bob@example.com', 'Spam!');
    $dispatcher2 = new FakeEventDispatcher();
    $deletedEntities2 = [];
    $controller2 = createCommentController(
        findComment: $comment2,
        findPost: $post,
        deletedEntities: $deletedEntities2,
        eventDispatcher: $dispatcher2,
    );

    $controller2->destroy(2);

    expect($dispatcher2->dispatched)->toHaveCount(1)
        ->and($dispatcher2->dispatched[0])->toBeInstanceOf(CommentDeleted::class)
        ->and($dispatcher2->dispatched[0]->getComment()->getName())->toBe('Bob');
});

// Helper functions

function createTestCommentEntity(
    int $id,
    int $postId,
    string $name,
    string $email,
    string $content,
    CommentStatus $status = CommentStatus::Pending,
): Comment {
    $comment = new Comment();
    $comment->id = $id;
    $comment->postId = $postId;
    $comment->name = $name;
    $comment->email = $email;
    $comment->content = $content;
    $comment->status = $status;
    $comment->createdAt = '2024-01-01 12:00:00';

    return $comment;
}

function createTestCommentPost(
    int $id,
    string $title,
    string $slug,
): Post {
    $post = new Post(title: $title, content: 'Content', authorId: 1);
    $post->id = $id;
    $post->slug = $slug;
    $post->createdAt = '2024-01-01 12:00:00';

    return $post;
}

function createMockCommentAdminRepo(
    array $findAllResult = [],
    ?Comment $findResult = null,
    array &$savedEntities = [],
    array &$deletedEntities = [],
): CommentRepositoryInterface {
    return new class (
        $findAllResult,
        $findResult,
        $savedEntities,
        $deletedEntities,
    ) implements CommentRepositoryInterface
    {
        public function __construct(
            private array $findAllResult,
            private ?Comment $findResult,
            private array &$savedEntities,
            private array &$deletedEntities,
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

        public function save(
            Entity $entity,
        ): void {
            $this->savedEntities[] = $entity;
        }

        public function delete(
            Entity $entity,
        ): void {
            $this->deletedEntities[] = $entity;
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

function createMockCommentPostRepo(
    ?Post $findResult = null,
): PostRepositoryInterface {
    return new class ($findResult) implements PostRepositoryInterface
    {
        public function __construct(
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

        public function save(Entity $entity): void {}

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

function createMockCommentAdminPagination(
    array $items = [],
    int $totalItems = 0,
): PaginationServiceInterface {
    return new class ($items, $totalItems) implements PaginationServiceInterface
    {
        public function __construct(
            private array $items,
            private int $totalItems,
        ) {}

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
                perPage: $perPage ?? 10,
                totalPages: $totalItems > 0 ? (int) ceil($totalItems / ($perPage ?? 10)) : 0,
                hasPreviousPage: $currentPage > 1,
                hasNextPage: $currentPage < (int) ceil($totalItems / ($perPage ?? 10)),
                pageNumbers: range(1, max(1, (int) ceil($totalItems / ($perPage ?? 10)))),
            );
        }

        public function calculateOffset(
            int $page,
            ?int $perPage = null,
        ): int {
            return ($page - 1) * ($perPage ?? 10);
        }

        public function getPerPage(): int
        {
            return 10;
        }
    };
}

function createMockCommentAdminView(
    array &$capturedData = [],
): ViewInterface {
    return new class ($capturedData) implements ViewInterface
    {
        public function __construct(
            private array &$capturedData,
        ) {}

        public function render(
            string $template,
            array $data = [],
        ): Response {
            $this->capturedData = $data;

            return new Response("rendered: $template");
        }

        public function renderToString(
            string $template,
            array $data = [],
        ): string {
            $this->capturedData = $data;

            return "rendered: $template";
        }
    };
}

function createCommentController(
    array $comments = [],
    int $totalComments = 0,
    ?Comment $findComment = null,
    ?Post $findPost = null,
    array &$capturedData = [],
    array &$savedEntities = [],
    array &$deletedEntities = [],
    ?FakeEventDispatcher $eventDispatcher = null,
): CommentAdminController {
    return new CommentAdminController(
        commentRepository: createMockCommentAdminRepo(
            findAllResult: $comments,
            findResult: $findComment,
            savedEntities: $savedEntities,
            deletedEntities: $deletedEntities,
        ),
        postRepository: createMockCommentPostRepo(
            findResult: $findPost,
        ),
        paginationService: createMockCommentAdminPagination($comments, $totalComments),
        eventDispatcher: $eventDispatcher ?? new FakeEventDispatcher(),
        view: createMockCommentAdminView($capturedData),
    );
}
