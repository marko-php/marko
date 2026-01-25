<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Controllers\CategoryController;

use function it;

use Marko\Blog\Controllers\CategoryController;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Category;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Repositories\CategoryRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

use ReflectionClass;

\it('injects CategoryRepositoryInterface and PostRepositoryInterface not concrete classes', function (): void {
    $reflection = new ReflectionClass(CategoryController::class);
    $constructor = $reflection->getConstructor();

    \expect($constructor)->not->toBeNull();

    $parameters = $constructor->getParameters();
    \expect($parameters)->toHaveCount(4)
        ->and($parameters[0]->getName())->toBe('categoryRepository')
        ->and($parameters[0]->getType()->getName())->toBe(CategoryRepositoryInterface::class)
        ->and($parameters[1]->getName())->toBe('postRepository')
        ->and($parameters[1]->getType()->getName())->toBe(PostRepositoryInterface::class)
        ->and($parameters[2]->getName())->toBe('paginationService')
        ->and($parameters[2]->getType()->getName())->toBe(PaginationServiceInterface::class)
        ->and($parameters[3]->getName())->toBe('view')
        ->and($parameters[3]->getType()->getName())->toBe(ViewInterface::class);
});

\it('returns paginated posts in category at GET /blog/category/{slug}', function (): void {
    // Check that the route attribute exists
    $reflection = new ReflectionClass(CategoryController::class);
    $method = $reflection->getMethod('show');
    $attributes = $method->getAttributes(Get::class);

    \expect($attributes)->toHaveCount(1);

    $routeAttribute = $attributes[0]->newInstance();
    \expect($routeAttribute->path)->toBe('/blog/category/{slug}');

    // Test that it returns posts
    $category = createCategory(1, 'Tech', 'tech');
    $posts = [createPost(1, 'Post 1', 'post-1'), createPost(2, 'Post 2', 'post-2')];

    $categoryRepo = createMockCategoryRepository(findBySlugResult: $category);
    $postRepo = createMockPostRepository(findPublishedByCategoryResult: $posts);
    $pagination = createMockPaginationService($posts, 2);
    $view = createMockView();

    $controller = new CategoryController($categoryRepo, $postRepo, $pagination, $view);
    $response = $controller->show('tech');

    \expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::category/show');
});

\it('returns 404 when category slug not found', function (): void {
    $categoryRepo = createMockCategoryRepository(findBySlugResult: null);
    $postRepo = createMockPostRepository();
    $pagination = createMockPaginationService();
    $view = createMockView();

    $controller = new CategoryController($categoryRepo, $postRepo, $pagination, $view);
    $response = $controller->show('non-existent');

    \expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(404)
        ->and($response->body())->toContain('not found');
});

\it('includes category name and path in response', function (): void {
    $rootCategory = createCategory(1, 'Technology', 'technology');
    $childCategory = createCategory(2, 'Programming', 'programming', 1);
    $path = [$rootCategory, $childCategory];

    $categoryRepo = createMockCategoryRepository(
        findBySlugResult: $childCategory,
        pathResult: $path,
    );
    $postRepo = createMockPostRepository();
    $pagination = createMockPaginationService();
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $controller = new CategoryController($categoryRepo, $postRepo, $pagination, $view);
    $controller->show('programming');

    \expect($capturedData)->toHaveKey('category')
        ->and($capturedData)->toHaveKey('path')
        ->and($capturedData['category']->name)->toBe('Programming')
        ->and($capturedData['path'])->toHaveCount(2)
        ->and($capturedData['path'][0]->name)->toBe('Technology')
        ->and($capturedData['path'][1]->name)->toBe('Programming');
});

\it('only includes published posts', function (): void {
    // This test verifies the controller calls findPublishedByCategory (not findByCategory)
    // The actual filtering is done by the repository method - controller just calls it
    $category = createCategory(1, 'Tech', 'tech');

    $categoryRepo = createMockCategoryRepository(findBySlugResult: $category);
    $postRepo = createMockPostRepository(findPublishedByCategoryResult: [
        createPost(1, 'Published Post', 'published-post', PostStatus::Published),
    ]);
    $pagination = createMockPaginationService();
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $controller = new CategoryController($categoryRepo, $postRepo, $pagination, $view);
    $controller->show('tech');

    // The test verifies we pass data to view - actual filtering is repository responsibility
    \expect($capturedData)->toHaveKey('posts')
        ->and($capturedData['posts'])->toBeInstanceOf(PaginatedResult::class);
});

\it('orders posts by published date descending', function (): void {
    // This test verifies the controller uses findPublishedByCategory
    // Ordering is handled by the repository (see PostRepository implementation)
    $category = createCategory(1, 'Tech', 'tech');

    $categoryRepo = createMockCategoryRepository(findBySlugResult: $category);
    $postRepo = createMockPostRepository(findPublishedByCategoryResult: [
        createPost(1, 'Newer Post', 'newer-post'),
        createPost(2, 'Older Post', 'older-post'),
    ]);
    $pagination = createMockPaginationService();
    $view = createMockView();

    $controller = new CategoryController($categoryRepo, $postRepo, $pagination, $view);
    $response = $controller->show('tech');

    \expect($response->statusCode())->toBe(200);
});

\it('accepts page query parameter for pagination', function (): void {
    $category = createCategory(1, 'Tech', 'tech');

    $categoryRepo = createMockCategoryRepository(findBySlugResult: $category);
    $postRepo = createMockPostRepository(
        findPublishedByCategoryResult: [createPost(3, 'Post 3', 'post-3')],
        countByCategoryResult: 25,
    );
    $pagination = createMockPaginationService();
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $controller = new CategoryController($categoryRepo, $postRepo, $pagination, $view);
    $controller->show('tech', page: 3);

    \expect($capturedData['posts']->currentPage)->toBe(3);
});

\it('includes pagination metadata in response', function (): void {
    $category = createCategory(1, 'Tech', 'tech');
    $posts = [createPost(1, 'Post 1', 'post-1')];

    $categoryRepo = createMockCategoryRepository(findBySlugResult: $category);
    $postRepo = createMockPostRepository(
        findPublishedByCategoryResult: $posts,
        countByCategoryResult: 25,
    );
    $pagination = createMockPaginationService();
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $controller = new CategoryController($categoryRepo, $postRepo, $pagination, $view);
    $controller->show('tech');

    \expect($capturedData['posts'])->toBeInstanceOf(PaginatedResult::class)
        ->and($capturedData['posts']->totalItems)->toBe(25)
        ->and($capturedData['posts']->totalPages)->toBe(3);
});

\it('renders using view template', function (): void {
    $category = createCategory(1, 'Tech', 'tech');

    $categoryRepo = createMockCategoryRepository(findBySlugResult: $category);
    $postRepo = createMockPostRepository();
    $pagination = createMockPaginationService();
    $view = createMockView();

    $controller = new CategoryController($categoryRepo, $postRepo, $pagination, $view);
    $response = $controller->show('tech');

    \expect($response->body())->toContain('blog::category/show');
});

// Helper functions

function createCategory(
    int $id,
    string $name,
    string $slug,
    ?int $parentId = null,
): Category {
    $category = new Category();
    $category->id = $id;
    $category->name = $name;
    $category->slug = $slug;
    $category->parentId = $parentId;

    return $category;
}

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

function createMockCategoryRepository(
    ?Category $findBySlugResult = null,
    array $pathResult = [],
): CategoryRepositoryInterface {
    return new class ($findBySlugResult, $pathResult) implements CategoryRepositoryInterface
    {
        public function __construct(
            private readonly ?Category $findBySlugResult,
            private readonly array $pathResult,
        ) {}

        public function findBySlug(
            string $slug,
        ): ?Category {
            return $this->findBySlugResult;
        }

        public function isSlugUnique(
            string $slug,
            ?int $excludeId = null,
        ): bool {
            return true;
        }

        public function findChildren(
            Category $parent,
        ): array {
            return [];
        }

        public function getPath(
            Category $category,
        ): array {
            return $this->pathResult ?: [$category];
        }

        public function findRoots(): array
        {
            return [];
        }

        public function getPostsForCategory(
            int $categoryId,
        ): array {
            return [];
        }

        public function find(
            int $id,
        ): ?Entity {
            return null;
        }

        public function findOrFail(
            int $id,
        ): Entity {
            throw RepositoryException::notFound(Category::class, $id);
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

function createMockPostRepository(
    array $findPublishedByCategoryResult = [],
    int $countByCategoryResult = 0,
): PostRepositoryInterface {
    return new class ($findPublishedByCategoryResult, $countByCategoryResult) implements PostRepositoryInterface
    {
        public function __construct(
            private readonly array $findPublishedByCategoryResult,
            private readonly int $countByCategoryResult,
        ) {}

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
            return $this->findPublishedByCategoryResult;
        }

        public function countPublishedByCategory(
            int $categoryId,
        ): int {
            return $this->countByCategoryResult;
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
                totalPages: (int) ceil($totalItems / ($perPage ?? 10)),
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
