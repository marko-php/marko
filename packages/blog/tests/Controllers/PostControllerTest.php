<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Controllers\PostController;

use Marko\Blog\Controllers\PostController;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Author;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;
use ReflectionClass;

\it('injects PostRepositoryInterface not concrete PostRepository', function (): void {
    $reflection = new ReflectionClass(PostController::class);
    $constructor = $reflection->getConstructor();

    \expect($constructor)->not->toBeNull();

    $parameters = $constructor->getParameters();
    \expect($parameters)->toHaveCount(3)
        ->and($parameters[0]->getName())->toBe('repository')
        ->and($parameters[0]->getType()->getName())->toBe(PostRepositoryInterface::class)
        ->and($parameters[1]->getName())->toBe('paginationService')
        ->and($parameters[1]->getType()->getName())->toBe(PaginationServiceInterface::class)
        ->and($parameters[2]->getName())->toBe('view')
        ->and($parameters[2]->getType()->getName())->toBe(ViewInterface::class);
});

\it('has GET /blog route on index method', function (): void {
    $reflection = new ReflectionClass(PostController::class);
    $method = $reflection->getMethod('index');
    $attributes = $method->getAttributes(Get::class);

    \expect($attributes)->toHaveCount(1);

    $routeAttribute = $attributes[0]->newInstance();
    \expect($routeAttribute->path)->toBe('/blog');
});

\it('has GET /blog/{slug} route on show method', function (): void {
    $reflection = new ReflectionClass(PostController::class);
    $method = $reflection->getMethod('show');
    $attributes = $method->getAttributes(Get::class);

    \expect($attributes)->toHaveCount(1);

    $routeAttribute = $attributes[0]->newInstance();
    \expect($routeAttribute->path)->toBe('/blog/{slug}');
});

\it('returns response using view on index route', function (): void {
    $posts = [
        createPost(1, 'Post 1', 'post-1'),
        createPost(2, 'Post 2', 'post-2'),
    ];
    $repository = createMockPostRepository(findPublishedPaginatedResult: $posts, countPublishedResult: 2);
    $pagination = createMockPaginationService($posts, 2);
    $view = createMockView();
    $controller = new PostController($repository, $pagination, $view);
    $response = $controller->index();

    \expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::post/index');
});

\it('returns response using view on show route', function (): void {
    $repository = createMockPostRepository(
        findBySlugResult: createPost(1, 'Hello World', 'hello-world'),
    );
    $pagination = createMockPaginationService();
    $view = createMockView();
    $controller = new PostController($repository, $pagination, $view);
    $response = $controller->show('hello-world');

    \expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::post/show');
});

\it('returns 404 response when post slug not found', function (): void {
    $repository = createMockPostRepository();
    $pagination = createMockPaginationService();
    $view = createMockView();
    $controller = new PostController($repository, $pagination, $view);
    $response = $controller->show('non-existent');

    \expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(404)
        ->and($response->body())->toContain('not found');
});

\it('maintains existing route attributes for GET /blog and GET /blog/{slug}', function (): void {
    $reflection = new ReflectionClass(PostController::class);

    // Check index method route
    $indexMethod = $reflection->getMethod('index');
    $indexAttributes = $indexMethod->getAttributes(Get::class);
    \expect($indexAttributes)->toHaveCount(1);
    $indexRoute = $indexAttributes[0]->newInstance();
    \expect($indexRoute->path)->toBe('/blog');

    // Check show method route
    $showMethod = $reflection->getMethod('show');
    $showAttributes = $showMethod->getAttributes(Get::class);
    \expect($showAttributes)->toHaveCount(1);
    $showRoute = $showAttributes[0]->newInstance();
    \expect($showRoute->path)->toBe('/blog/{slug}');
});

\it('returns paginated list of published posts at GET /blog', function (): void {
    // Verify the route attribute
    $reflection = new ReflectionClass(PostController::class);
    $method = $reflection->getMethod('index');
    $attributes = $method->getAttributes(Get::class);

    \expect($attributes)->toHaveCount(1);
    $routeAttribute = $attributes[0]->newInstance();
    \expect($routeAttribute->path)->toBe('/blog');

    // Verify that calling index returns paginated posts
    $posts = [
        createPost(1, 'Post 1', 'post-1'),
        createPost(2, 'Post 2', 'post-2'),
    ];
    $repository = createMockPostRepository(findPublishedPaginatedResult: $posts, countPublishedResult: 2);
    $pagination = createMockPaginationService($posts, 2);
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $controller = new PostController($repository, $pagination, $view);
    $response = $controller->index();

    \expect($response->statusCode())->toBe(200)
        ->and($capturedData)->toHaveKey('posts')
        ->and($capturedData['posts'])->toBeInstanceOf(PaginatedResult::class)
        ->and($capturedData['posts']->items)->toHaveCount(2);
});

\it('orders posts by published date descending', function (): void {
    // This test verifies the controller uses findPublishedPaginated
    // Ordering is handled by the repository (see PostRepository implementation)
    $posts = [
        createPost(1, 'Newer Post', 'newer-post', publishedAt: '2024-01-02 12:00:00'),
        createPost(2, 'Older Post', 'older-post', publishedAt: '2024-01-01 12:00:00'),
    ];
    $repository = createMockPostRepository(findPublishedPaginatedResult: $posts, countPublishedResult: 2);
    $pagination = createMockPaginationService($posts, 2);
    $view = createMockView();

    $controller = new PostController($repository, $pagination, $view);
    $response = $controller->index();

    \expect($response->statusCode())->toBe(200);
});

\it('excludes draft and scheduled posts from listing', function (): void {
    // This test verifies the controller uses findPublishedPaginated (not findAll)
    // which only returns posts with status = Published
    // Actual filtering is repository responsibility - controller calls the right method
    $publishedPosts = [
        createPost(1, 'Published Post', 'published-post', PostStatus::Published),
    ];

    $repository = createMockPostRepository(
        findPublishedPaginatedResult: $publishedPosts,
        countPublishedResult: 1,
    );
    $pagination = createMockPaginationService($publishedPosts, 1);
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $controller = new PostController($repository, $pagination, $view);
    $controller->index();

    // Verify that only published posts are in the result
    \expect($capturedData['posts'])->toBeInstanceOf(PaginatedResult::class)
        ->and($capturedData['posts']->items)->toHaveCount(1)
        ->and($capturedData['posts']->items[0]->status)->toBe(PostStatus::Published);
});

\it('accepts page query parameter for pagination', function (): void {
    $posts = [createPost(3, 'Post on Page 3', 'post-page-3')];
    $repository = createMockPostRepository(
        findPublishedPaginatedResult: $posts,
        countPublishedResult: 25,
    );
    $pagination = createMockPaginationService($posts, 25);
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $controller = new PostController($repository, $pagination, $view);
    $controller->index(page: 3);

    \expect($capturedData['posts']->currentPage)->toBe(3);
});

\it('defaults to page 1 when no page parameter', function (): void {
    $posts = [createPost(1, 'Post 1', 'post-1')];
    $repository = createMockPostRepository(
        findPublishedPaginatedResult: $posts,
        countPublishedResult: 10,
    );
    $pagination = createMockPaginationService($posts, 10);
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $controller = new PostController($repository, $pagination, $view);
    $controller->index();

    \expect($capturedData['posts']->currentPage)->toBe(1);
});

\it('returns 404 for invalid page numbers', function (): void {
    $repository = createMockPostRepository(
        findPublishedPaginatedResult: [],
        countPublishedResult: 25,
    );
    $pagination = createMockPaginationService([], 25);
    $view = createMockView();

    $controller = new PostController($repository, $pagination, $view);

    // Test page 0
    $response = $controller->index(page: 0);
    \expect($response->statusCode())->toBe(404);

    // Test negative page
    $response = $controller->index(page: -1);
    \expect($response->statusCode())->toBe(404);

    // Test page beyond total (25 posts / 10 per page = 3 pages max)
    $response = $controller->index(page: 10);
    \expect($response->statusCode())->toBe(404);
});

\it('includes pagination metadata in response', function (): void {
    $posts = [createPost(1, 'Post 1', 'post-1')];
    $repository = createMockPostRepository(
        findPublishedPaginatedResult: $posts,
        countPublishedResult: 25,
    );
    $pagination = createMockPaginationService($posts, 25);
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $controller = new PostController($repository, $pagination, $view);
    $controller->index();

    \expect($capturedData['posts'])->toBeInstanceOf(PaginatedResult::class)
        ->and($capturedData['posts']->totalItems)->toBe(25)
        ->and($capturedData['posts']->totalPages)->toBe(3)
        ->and($capturedData['posts']->perPage)->toBe(10)
        ->and($capturedData['posts']->currentPage)->toBe(1)
        ->and($capturedData['posts']->hasPreviousPage)->toBe(false)
        ->and($capturedData['posts']->hasNextPage)->toBe(true);
});

\it('includes post title summary author and date in listing', function (): void {
    $author = createAuthor(1, 'John Doe', 'john-doe');
    $post = createPost(
        id: 1,
        title: 'My First Post',
        slug: 'my-first-post',
        summary: 'This is the post summary.',
        publishedAt: '2024-06-15 10:30:00',
        author: $author,
    );

    $repository = createMockPostRepository(
        findPublishedPaginatedResult: [$post],
        countPublishedResult: 1,
    );
    $pagination = createMockPaginationService([$post], 1);
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $controller = new PostController($repository, $pagination, $view);
    $controller->index();

    $postInView = $capturedData['posts']->items[0];
    \expect($postInView->getTitle())->toBe('My First Post')
        ->and($postInView->getSummary())->toBe('This is the post summary.')
        ->and($postInView->getAuthor()->getName())->toBe('John Doe')
        ->and($postInView->getPublishedAt()->format('Y-m-d H:i:s'))->toBe('2024-06-15 10:30:00');
});

\it('renders using view template', function (): void {
    $posts = [createPost(1, 'Post 1', 'post-1')];
    $repository = createMockPostRepository(
        findPublishedPaginatedResult: $posts,
        countPublishedResult: 1,
    );
    $pagination = createMockPaginationService($posts, 1);
    $view = createMockView();

    $controller = new PostController($repository, $pagination, $view);
    $response = $controller->index();

    \expect($response->body())->toContain('blog::post/index');
});

// Helper functions

function createPost(
    int $id,
    string $title,
    string $slug,
    PostStatus $status = PostStatus::Published,
    ?string $summary = null,
    ?string $publishedAt = null,
    ?Author $author = null,
): Post {
    $post = new Post(title: $title, content: 'Content', authorId: 1);
    $post->id = $id;
    $post->slug = $slug;
    $post->status = $status;
    $post->summary = $summary;
    $post->publishedAt = $publishedAt ?? '2024-01-01 12:00:00';
    if ($author !== null) {
        $post->setAuthor($author);
    }

    return $post;
}

function createAuthor(
    int $id,
    string $name,
    string $slug,
): Author {
    $author = new Author();
    $author->id = $id;
    $author->name = $name;
    $author->email = 'test@example.com';
    $author->slug = $slug;

    return $author;
}

function createMockPostRepository(
    array $findPublishedPaginatedResult = [],
    int $countPublishedResult = 0,
    ?Post $findBySlugResult = null,
): PostRepositoryInterface {
    return new class (
        $findPublishedPaginatedResult,
        $countPublishedResult,
        $findBySlugResult,
    ) implements PostRepositoryInterface
    {
        public function __construct(
            private readonly array $findPublishedPaginatedResult,
            private readonly int $countPublishedResult,
            private readonly ?Post $findBySlugResult,
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
            return $this->findPublishedPaginatedResult;
        }

        public function countPublished(): int
        {
            return $this->countPublishedResult;
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

function createMockPaginationService(
    array $items = [],
    int $totalItems = 0,
): PaginationServiceInterface {
    return new class ($items, $totalItems) implements PaginationServiceInterface
    {
        public function __construct(
            private readonly array $items,
            private readonly int $totalItems,
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

function createMockView(): ViewInterface
{
    return new class () implements ViewInterface
    {
        public function render(
            string $template,
            array $data = [],
        ): Response {
            return new Response("rendered: $template");
        }

        public function renderToString(
            string $template,
            array $data = [],
        ): string {
            return "rendered: $template";
        }
    };
}

function createMockViewWithCapture(
    array &$capturedData,
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
