<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Unit\Admin\Api;

use Closure;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Admin\Api\PostApiController;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post as PostRoute;
use Marko\Routing\Attributes\Put;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use ReflectionClass;

it('creates PostApiController with list, show, create, update, delete, publish actions', function (): void {
    $reflection = new ReflectionClass(PostApiController::class);

    // Check class-level middleware
    $middlewareAttrs = $reflection->getAttributes(Middleware::class);
    expect($middlewareAttrs)->toHaveCount(1);
    $middleware = $middlewareAttrs[0]->newInstance();
    expect($middleware->middleware)->toContain(AdminAuthMiddleware::class);

    // Check index method (list)
    $index = $reflection->getMethod('index');
    $indexRoute = $index->getAttributes(Get::class);
    expect($indexRoute)->toHaveCount(1)
        ->and($indexRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/posts');

    // Check show method
    $show = $reflection->getMethod('show');
    $showRoute = $show->getAttributes(Get::class);
    expect($showRoute)->toHaveCount(1)
        ->and($showRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/posts/{id}');

    // Check store method (create)
    $store = $reflection->getMethod('store');
    $storeRoute = $store->getAttributes(PostRoute::class);
    expect($storeRoute)->toHaveCount(1)
        ->and($storeRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/posts');

    // Check update method
    $update = $reflection->getMethod('update');
    $updateRoute = $update->getAttributes(Put::class);
    expect($updateRoute)->toHaveCount(1)
        ->and($updateRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/posts/{id}');

    // Check destroy method (delete)
    $destroy = $reflection->getMethod('destroy');
    $destroyRoute = $destroy->getAttributes(Delete::class);
    expect($destroyRoute)->toHaveCount(1)
        ->and($destroyRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/posts/{id}');

    // Check publish method
    $publish = $reflection->getMethod('publish');
    $publishRoute = $publish->getAttributes(PostRoute::class);
    expect($publishRoute)->toHaveCount(1)
        ->and($publishRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/posts/{id}/publish');
});

it('returns paginated JSON list of posts with meta', function (): void {
    $posts = [
        createApiTestPost(1, 'Post 1', 'post-1'),
        createApiTestPost(2, 'Post 2', 'post-2'),
    ];
    $controller = createPostApiController(
        posts: $posts,
        totalPosts: 2,
    );

    $request = new Request(query: ['page' => '1']);
    $response = $controller->index($request);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);

    expect($body)->toHaveKey('data')
        ->and($body)->toHaveKey('meta')
        ->and($body['data'])->toHaveCount(2)
        ->and($body['data'][0]['id'])->toBe(1)
        ->and($body['data'][0]['title'])->toBe('Post 1')
        ->and($body['data'][0]['slug'])->toBe('post-1')
        ->and($body['data'][0]['status'])->toBe('draft')
        ->and($body['meta']['page'])->toBe(1)
        ->and($body['meta']['total'])->toBe(2);
});

it('creates post from JSON body and returns 201', function (): void {
    $savedEntities = [];
    $controller = createPostApiController(
        savedEntities: $savedEntities,
    );

    $request = new Request(post: [
        'title' => 'New API Post',
        'content' => 'This is the API post content.',
        'summary' => 'A short summary.',
        'author_id' => '1',
    ]);

    $response = $controller->store($request);

    expect($response->statusCode())->toBe(201)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);

    expect($body)->toHaveKey('data')
        ->and($body['data']['title'])->toBe('New API Post')
        ->and($body['data']['slug'])->toBe('new-api-post')
        ->and($body['data']['status'])->toBe('draft')
        ->and($savedEntities)->toHaveCount(1)
        ->and($savedEntities[0])->toBeInstanceOf(Post::class)
        ->and($savedEntities[0]->title)->toBe('New API Post');
});

it('returns 422 with validation errors for invalid post data', function (): void {
    $savedEntities = [];
    $controller = createPostApiController(
        savedEntities: $savedEntities,
    );

    $request = new Request(post: [
        'title' => '',
        'content' => '',
        'author_id' => '0',
    ]);

    $response = $controller->store($request);

    expect($response->statusCode())->toBe(422)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);

    expect($body)->toHaveKey('errors')
        ->and($body['errors'])->toHaveCount(3)
        ->and($body['errors'][0]['message'])->toBe('Title is required')
        ->and($body['errors'][1]['message'])->toBe('Content is required')
        ->and($body['errors'][2]['message'])->toBe('Author is required')
        ->and($savedEntities)->toBeEmpty();
});

// Helper functions

function createApiTestPost(
    int $id,
    string $title,
    string $slug,
    PostStatus $status = PostStatus::Draft,
    ?string $summary = null,
    int $authorId = 1,
): Post {
    $post = new Post(title: $title, content: 'Content', authorId: $authorId);
    $post->id = $id;
    $post->slug = $slug;
    $post->status = $status;
    $post->summary = $summary;
    $post->createdAt = '2024-01-01 12:00:00';

    return $post;
}

function createApiMockPostRepo(
    array $findAllResult = [],
    ?Post $findResult = null,
    array &$savedEntities = [],
    array &$deletedEntities = [],
): PostRepositoryInterface {
    return new class (
        $findAllResult,
        $findResult,
        $savedEntities,
        $deletedEntities,
    ) implements PostRepositoryInterface
    {
        public function __construct(
            private array $findAllResult,
            private ?Post $findResult,
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$savedEntities,
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$deletedEntities,
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

        public function existsBy(
            array $criteria,
        ): bool {
            return $this->findOneBy(criteria: $criteria) !== null;
        }

        public function save(
            Entity $entity,
        ): void {
            if ($entity instanceof Post && $entity->id === null) {
                $entity->id = 99;
            }
            $this->savedEntities[] = $entity;
        }

        public function delete(
            Entity $entity,
        ): void {
            $this->deletedEntities[] = $entity;
        }

        public function findBySlug(
            string $slug,
        ): ?Post {
            return $this->findResult;
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

function createApiMockPagination(
    int $perPage = 10,
): PaginationServiceInterface {
    return new class ($perPage) implements PaginationServiceInterface
    {
        public function __construct(
            private int $perPage,
        ) {}

        public function paginate(
            array $items,
            int $totalItems,
            int $currentPage,
            ?int $perPage = null,
        ): PaginatedResult {
            $pp = $perPage ?? $this->perPage;

            return new PaginatedResult(
                items: $items,
                currentPage: $currentPage,
                totalItems: $totalItems,
                perPage: $pp,
                totalPages: $totalItems > 0 ? (int) ceil($totalItems / $pp) : 0,
                hasPreviousPage: $currentPage > 1,
                hasNextPage: $currentPage < (int) ceil($totalItems / $pp),
                pageNumbers: range(1, max(1, (int) ceil($totalItems / $pp))),
            );
        }

        public function calculateOffset(
            int $page,
            ?int $perPage = null,
        ): int {
            return ($page - 1) * ($perPage ?? $this->perPage);
        }

        public function getPerPage(): int
        {
            return $this->perPage;
        }
    };
}

function createApiMockSlugGenerator(): SlugGeneratorInterface
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

function createPostApiController(
    array $posts = [],
    int $totalPosts = 0,
    ?Post $findPost = null,
    array &$savedEntities = [],
    array &$deletedEntities = [],
): PostApiController {
    return new PostApiController(
        postRepository: createApiMockPostRepo(
            findAllResult: $posts,
            findResult: $findPost,
            savedEntities: $savedEntities,
            deletedEntities: $deletedEntities,
        ),
        paginationService: createApiMockPagination(),
        slugGenerator: createApiMockSlugGenerator(),
    );
}
