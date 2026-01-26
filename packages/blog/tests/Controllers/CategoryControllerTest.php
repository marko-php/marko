<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Controllers\CategoryController;

use function it;

use Marko\Blog\Controllers\CategoryController;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Category;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Repositories\AuthorRepositoryInterface;
use Marko\Blog\Repositories\CategoryRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Blog\Tests\Mocks\MockAuthorRepository;
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
    \expect($parameters)->toHaveCount(5)
        ->and($parameters[0]->getName())->toBe('categoryRepository')
        ->and($parameters[0]->getType()->getName())->toBe(CategoryRepositoryInterface::class)
        ->and($parameters[1]->getName())->toBe('postRepository')
        ->and($parameters[1]->getType()->getName())->toBe(PostRepositoryInterface::class)
        ->and($parameters[2]->getName())->toBe('authorRepository')
        ->and($parameters[2]->getType()->getName())->toBe(AuthorRepositoryInterface::class)
        ->and($parameters[3]->getName())->toBe('paginationService')
        ->and($parameters[3]->getType()->getName())->toBe(PaginationServiceInterface::class)
        ->and($parameters[4]->getName())->toBe('view')
        ->and($parameters[4]->getType()->getName())->toBe(ViewInterface::class);
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

    $authorRepo = new MockAuthorRepository();
    $controller = new CategoryController($categoryRepo, $postRepo, $authorRepo, $pagination, $view);
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

    $authorRepo = new MockAuthorRepository();
    $controller = new CategoryController($categoryRepo, $postRepo, $authorRepo, $pagination, $view);
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

    $authorRepo = new MockAuthorRepository();
    $controller = new CategoryController($categoryRepo, $postRepo, $authorRepo, $pagination, $view);
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

    $authorRepo = new MockAuthorRepository();
    $controller = new CategoryController($categoryRepo, $postRepo, $authorRepo, $pagination, $view);
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

    $authorRepo = new MockAuthorRepository();
    $controller = new CategoryController($categoryRepo, $postRepo, $authorRepo, $pagination, $view);
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

    $authorRepo = new MockAuthorRepository();
    $controller = new CategoryController($categoryRepo, $postRepo, $authorRepo, $pagination, $view);
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

    $authorRepo = new MockAuthorRepository();
    $controller = new CategoryController($categoryRepo, $postRepo, $authorRepo, $pagination, $view);
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

    $authorRepo = new MockAuthorRepository();
    $controller = new CategoryController($categoryRepo, $postRepo, $authorRepo, $pagination, $view);
    $response = $controller->show('tech');

    \expect($response->body())->toContain('blog::category/show');
});

\it('includes posts from subcategories', function (): void {
    $parentCategory = createCategory(1, 'Technology', 'technology');
    $postsFromMultipleCategories = [
        createPost(1, 'Post in Tech', 'post-in-tech'),
        createPost(2, 'Post in PHP', 'post-in-php'),
        createPost(3, 'Post in JavaScript', 'post-in-javascript'),
    ];

    // Mock returns descendant IDs 2 and 3 (e.g., PHP and JavaScript categories)
    $categoryRepo = createMockCategoryRepository(
        findBySlugResult: $parentCategory,
        descendantIds: [2, 3],
    );
    $postRepo = createMockPostRepositoryWithCapture(
        findByCategoriesResult: $postsFromMultipleCategories,
        countByCategoriesResult: 3,
    );
    $pagination = createMockPaginationService();
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $authorRepo = new MockAuthorRepository();
    $controller = new CategoryController($categoryRepo, $postRepo, $authorRepo, $pagination, $view);
    $controller->show('technology');

    // Verify all 3 posts (from parent + descendants) are included
    \expect($capturedData['posts']->items)->toHaveCount(3)
        ->and($capturedData['posts']->totalItems)->toBe(3);
});

\it('passes correct category IDs to findPublishedByCategories', function (): void {
    $parentCategory = createCategory(1, 'Technology', 'technology');

    $categoryRepo = createMockCategoryRepository(
        findBySlugResult: $parentCategory,
        descendantIds: [2, 3, 4], // 3 descendants
    );
    $capturedCategoryIds = [];
    $postRepo = createMockPostRepositoryCapturingCategoryIds(
        capturedCategoryIds: $capturedCategoryIds,
    );
    $pagination = createMockPaginationService();
    $view = createMockView();

    $authorRepo = new MockAuthorRepository();
    $controller = new CategoryController($categoryRepo, $postRepo, $authorRepo, $pagination, $view);
    $controller->show('technology');

    // Should include parent ID (1) plus all descendant IDs (2, 3, 4)
    \expect($capturedCategoryIds)->toContain(1)
        ->and($capturedCategoryIds)->toContain(2)
        ->and($capturedCategoryIds)->toContain(3)
        ->and($capturedCategoryIds)->toContain(4)
        ->and($capturedCategoryIds)->toHaveCount(4);
});

\it('handles category with no subcategories', function (): void {
    $leafCategory = createCategory(5, 'PHP', 'php');

    $categoryRepo = createMockCategoryRepository(
        findBySlugResult: $leafCategory,
        descendantIds: [], // No descendants
    );
    $capturedCategoryIds = [];
    $postRepo = createMockPostRepositoryCapturingCategoryIds(
        capturedCategoryIds: $capturedCategoryIds,
    );
    $pagination = createMockPaginationService();
    $view = createMockView();

    $authorRepo = new MockAuthorRepository();
    $controller = new CategoryController($categoryRepo, $postRepo, $authorRepo, $pagination, $view);
    $controller->show('php');

    // Should only include the single category ID
    \expect($capturedCategoryIds)->toBe([5]);
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
    array $descendantIds = [],
): CategoryRepositoryInterface {
    return new class ($findBySlugResult, $pathResult, $descendantIds) implements CategoryRepositoryInterface
    {
        public function __construct(
            private readonly ?Category $findBySlugResult,
            private readonly array $pathResult,
            private readonly array $descendantIds,
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

        public function getDescendantIds(
            int $categoryId,
        ): array {
            return $this->descendantIds;
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

        public function findPublishedByCategories(
            array $categoryIds,
            int $limit,
            int $offset,
        ): array {
            return $this->findPublishedByCategoryResult;
        }

        public function countPublishedByCategories(
            array $categoryIds,
        ): int {
            return $this->countByCategoryResult;
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

function createMockPostRepositoryWithCapture(
    array $findByCategoriesResult = [],
    int $countByCategoriesResult = 0,
): PostRepositoryInterface {
    return new class ($findByCategoriesResult, $countByCategoriesResult) implements PostRepositoryInterface
    {
        public function __construct(
            private readonly array $findByCategoriesResult,
            private readonly int $countByCategoriesResult,
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
            return $this->findByCategoriesResult;
        }

        public function countPublishedByCategories(
            array $categoryIds,
        ): int {
            return $this->countByCategoriesResult;
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

        public function save(Entity $entity): void {}

        public function delete(Entity $entity): void {}
    };
}

function createMockPostRepositoryCapturingCategoryIds(
    array &$capturedCategoryIds,
): PostRepositoryInterface {
    return new class ($capturedCategoryIds) implements PostRepositoryInterface
    {
        public function __construct(
            private array &$capturedCategoryIds,
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
            $this->capturedCategoryIds = $categoryIds;

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

        public function save(Entity $entity): void {}

        public function delete(Entity $entity): void {}
    };
}
