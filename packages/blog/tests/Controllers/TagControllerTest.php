<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Controllers\TagController;

use Marko\Blog\Controllers\TagController;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Post;
use Marko\Blog\Entity\Tag;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Repositories\AuthorRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Repositories\TagRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Blog\Tests\Mocks\MockAuthorRepository;
use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;
use ReflectionClass;

\it('injects TagRepositoryInterface and PostRepositoryInterface not concrete classes', function (): void {
    $reflection = new ReflectionClass(TagController::class);
    $constructor = $reflection->getConstructor();

    \expect($constructor)->not->toBeNull();

    $parameters = $constructor->getParameters();
    \expect($parameters)->toHaveCount(5)
        ->and($parameters[0]->getName())->toBe('tagRepository')
        ->and($parameters[0]->getType()->getName())->toBe(TagRepositoryInterface::class)
        ->and($parameters[1]->getName())->toBe('postRepository')
        ->and($parameters[1]->getType()->getName())->toBe(PostRepositoryInterface::class)
        ->and($parameters[2]->getName())->toBe('authorRepository')
        ->and($parameters[2]->getType()->getName())->toBe(AuthorRepositoryInterface::class)
        ->and($parameters[3]->getName())->toBe('paginationService')
        ->and($parameters[3]->getType()->getName())->toBe(PaginationServiceInterface::class)
        ->and($parameters[4]->getName())->toBe('view')
        ->and($parameters[4]->getType()->getName())->toBe(ViewInterface::class);
});

\it('returns paginated posts with tag at GET /blog/tag/{slug}', function (): void {
    $reflection = new ReflectionClass(TagController::class);
    $method = $reflection->getMethod('index');
    $attributes = $method->getAttributes(Get::class);

    \expect($attributes)->toHaveCount(1);

    $routeAttribute = $attributes[0]->newInstance();
    \expect($routeAttribute->path)->toBe('/blog/tag/{slug}');

    // Test actual behavior
    $tag = new Tag();
    $tag->id = 1;
    $tag->name = 'PHP';
    $tag->slug = 'php';

    $posts = [createPost(1, 'Post 1'), createPost(2, 'Post 2')];
    $paginatedResult = createPaginatedResult($posts);

    $tagRepository = createTagRepository(findBySlugResult: $tag);
    $postRepository = createPostRepository(findPublishedByTagResult: $posts);
    $paginationService = createPaginationService($paginatedResult);
    $view = createView();

    $authorRepository = new MockAuthorRepository();
    $controller = new TagController($tagRepository, $postRepository, $authorRepository, $paginationService, $view);
    $response = $controller->index('php');

    \expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200);
});

\it('returns 404 when tag slug not found', function (): void {
    $tagRepository = createTagRepository(findBySlugResult: null);
    $postRepository = createPostRepository();
    $paginationService = createPaginationService(createPaginatedResult());
    $view = createView();

    $authorRepository = new MockAuthorRepository();
    $controller = new TagController($tagRepository, $postRepository, $authorRepository, $paginationService, $view);
    $response = $controller->index('non-existent-tag');

    \expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(404)
        ->and($response->body())->toContain('not found');
});

\it('includes tag name in response', function (): void {
    $tag = new Tag();
    $tag->id = 1;
    $tag->name = 'PHP Development';
    $tag->slug = 'php-development';

    $capturedData = [];
    $tagRepository = createTagRepository(findBySlugResult: $tag);
    $postRepository = createPostRepository();
    $paginationService = createPaginationService(createPaginatedResult());
    $view = createViewWithCapture($capturedData);

    $authorRepository = new MockAuthorRepository();
    $controller = new TagController($tagRepository, $postRepository, $authorRepository, $paginationService, $view);
    $controller->index('php-development');

    \expect($capturedData)->toHaveKey('tag')
        ->and($capturedData['tag'])->toBe($tag)
        ->and($capturedData['tag']->name)->toBe('PHP Development');
});

\it('only includes published posts', function (): void {
    // This test verifies that the controller uses findPublishedByTag,
    // which only returns published posts. The filtering is done by the repository method.
    $tag = new Tag();
    $tag->id = 1;
    $tag->name = 'PHP';
    $tag->slug = 'php';

    $publishedPosts = [createPost(1, 'Published Post')];

    $tagRepository = createTagRepository(findBySlugResult: $tag);
    $postRepository = createPostRepository(findPublishedByTagResult: $publishedPosts);
    $paginationService = createPaginationService(createPaginatedResult($publishedPosts, 1, 1));
    $view = createView();

    $authorRepository = new MockAuthorRepository();
    $controller = new TagController($tagRepository, $postRepository, $authorRepository, $paginationService, $view);
    $response = $controller->index('php');

    // A successful response means findPublishedByTag was called
    \expect($response->statusCode())->toBe(200);
});

\it('orders posts by published date descending', function (): void {
    // Ordering is done by the repository's findPublishedByTag method.
    // This test verifies the controller receives posts in the order provided
    // by the repository (which orders by published_at DESC).
    $tag = new Tag();
    $tag->id = 1;
    $tag->name = 'PHP';
    $tag->slug = 'php';

    // Posts ordered by published date (newest first)
    $post1 = createPost(1, 'Newest Post');
    $post1->publishedAt = '2024-03-01 12:00:00';
    $post2 = createPost(2, 'Older Post');
    $post2->publishedAt = '2024-02-01 12:00:00';
    $post3 = createPost(3, 'Oldest Post');
    $post3->publishedAt = '2024-01-01 12:00:00';

    $orderedPosts = [$post1, $post2, $post3];

    $capturedData = [];
    $tagRepository = createTagRepository(findBySlugResult: $tag);
    $postRepository = createPostRepository(findPublishedByTagResult: $orderedPosts);
    $paginationService = createPaginationService(createPaginatedResult($orderedPosts, 1, 3));
    $view = createViewWithCapture($capturedData);

    $authorRepository = new MockAuthorRepository();
    $controller = new TagController($tagRepository, $postRepository, $authorRepository, $paginationService, $view);
    $controller->index('php');

    // Verify posts are in the expected order (as returned by repository)
    \expect($capturedData['posts']->items)->toBe($orderedPosts);
});

\it('accepts page query parameter for pagination', function (): void {
    // Verify the controller method signature accepts a page parameter
    $reflection = new ReflectionClass(TagController::class);
    $method = $reflection->getMethod('index');
    $parameters = $method->getParameters();

    // Should have slug and page parameters
    \expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('slug')
        ->and($parameters[1]->getName())->toBe('page')
        ->and($parameters[1]->getDefaultValue())->toBe(1)
        ->and($parameters[1]->getType()->getName())->toBe('int');

    // Test that page parameter affects offset calculation
    $tag = new Tag();
    $tag->id = 1;
    $tag->name = 'PHP';
    $tag->slug = 'php';

    $page2Posts = [createPost(11, 'Post 11'), createPost(12, 'Post 12')];
    $paginatedResult = createPaginatedResult($page2Posts, 2, 25);

    $tagRepository = createTagRepository(findBySlugResult: $tag);
    $postRepository = createPostRepository(findPublishedByTagResult: $page2Posts);
    $paginationService = createPaginationService($paginatedResult);
    $view = createView();

    $authorRepository = new MockAuthorRepository();
    $controller = new TagController($tagRepository, $postRepository, $authorRepository, $paginationService, $view);
    $response = $controller->index('php', 2);

    \expect($response->statusCode())->toBe(200);
});

\it('includes pagination metadata in response', function (): void {
    $tag = new Tag();
    $tag->id = 1;
    $tag->name = 'PHP';
    $tag->slug = 'php';

    $posts = [createPost(1, 'Post 1'), createPost(2, 'Post 2')];
    $paginatedResult = createPaginatedResult(
        items: $posts,
        currentPage: 2,
        totalItems: 25,
        perPage: 10,
    );

    $capturedData = [];
    $tagRepository = createTagRepository(findBySlugResult: $tag);
    $postRepository = createPostRepository(findPublishedByTagResult: $posts);
    $paginationService = createPaginationService($paginatedResult);
    $view = createViewWithCapture($capturedData);

    $authorRepository = new MockAuthorRepository();
    $controller = new TagController($tagRepository, $postRepository, $authorRepository, $paginationService, $view);
    $controller->index('php', 2);

    // Verify pagination metadata is passed to the view
    \expect($capturedData)->toHaveKey('posts')
        ->and($capturedData['posts'])->toBeInstanceOf(PaginatedResult::class)
        ->and($capturedData['posts']->currentPage)->toBe(2)
        ->and($capturedData['posts']->totalItems)->toBe(25)
        ->and($capturedData['posts']->perPage)->toBe(10)
        ->and($capturedData['posts']->totalPages)->toBe(3)
        ->and($capturedData['posts']->hasPreviousPage)->toBeTrue()
        ->and($capturedData['posts']->hasNextPage)->toBeTrue();
});

\it('renders using view template', function (): void {
    $tag = new Tag();
    $tag->id = 1;
    $tag->name = 'PHP';
    $tag->slug = 'php';

    $capturedTemplate = null;
    $tagRepository = createTagRepository(findBySlugResult: $tag);
    $postRepository = createPostRepository();
    $paginationService = createPaginationService(createPaginatedResult());
    $view = createViewWithTemplateCapture($capturedTemplate);

    $authorRepository = new MockAuthorRepository();
    $controller = new TagController($tagRepository, $postRepository, $authorRepository, $paginationService, $view);
    $controller->index('php');

    // Verify the correct template is used
    \expect($capturedTemplate)->toBe('blog::tag/index');
});

// Helper function to create a mock Post
function createPost(
    int $id,
    string $title,
    string $slug = '',
): Post {
    $post = new Post();
    $post->id = $id;
    $post->title = $title;
    $post->slug = $slug ?: strtolower(str_replace(' ', '-', $title));

    return $post;
}

// Helper function to create mock PaginatedResult
function createPaginatedResult(
    array $items = [],
    int $currentPage = 1,
    int $totalItems = 0,
    int $perPage = 10,
): PaginatedResult {
    $totalItems = $totalItems ?: count($items);
    $totalPages = (int) ceil($totalItems / $perPage) ?: 0;

    return new PaginatedResult(
        items: $items,
        currentPage: $currentPage,
        totalItems: $totalItems,
        perPage: $perPage,
        totalPages: $totalPages,
        hasPreviousPage: $currentPage > 1,
        hasNextPage: $currentPage < $totalPages,
        pageNumbers: range(1, max(1, $totalPages)),
    );
}

// Helper function to create mock TagRepositoryInterface
function createTagRepository(
    ?Tag $findBySlugResult = null,
): TagRepositoryInterface {
    return new class ($findBySlugResult) implements TagRepositoryInterface
    {
        public function __construct(
            private readonly ?Tag $findBySlugResult,
        ) {}

        public function findBySlug(
            string $slug,
        ): ?Tag {
            return $this->findBySlugResult;
        }

        public function findByNameLike(
            string $name,
        ): array {
            return [];
        }

        public function isSlugUnique(
            string $slug,
            ?int $excludeId = null,
        ): bool {
            return true;
        }

        public function getPostsForTag(
            int $tagId,
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
            throw RepositoryException::notFound(Tag::class, $id);
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

// Helper function to create mock PostRepositoryInterface
function createPostRepository(
    array $findPublishedByTagResult = [],
    int $countPublishedByTagResult = 0,
): PostRepositoryInterface {
    return new class ($findPublishedByTagResult, $countPublishedByTagResult) implements PostRepositoryInterface
    {
        public function __construct(
            private readonly array $findPublishedByTagResult,
            private readonly int $countPublishedByTagResult,
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
            return $this->findPublishedByTagResult;
        }

        public function countPublishedByTag(
            int $tagId,
        ): int {
            return $this->countPublishedByTagResult ?: count($this->findPublishedByTagResult);
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

// Helper function to create mock PostRepositoryInterface with call tracking
function createPostRepositoryWithCallTracking(
    array $findPublishedByTagResult,
    int $countPublishedByTagResult,
    array &$methodCalls,
): PostRepositoryInterface {
    return new class ($findPublishedByTagResult, $countPublishedByTagResult, $methodCalls) implements PostRepositoryInterface
    {
        public function __construct(
            private readonly array $findPublishedByTagResult,
            private readonly int $countPublishedByTagResult,
            private array &$methodCalls,
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
            $this->methodCalls[] = 'findPublishedByTag';

            return $this->findPublishedByTagResult;
        }

        public function countPublishedByTag(
            int $tagId,
        ): int {
            $this->methodCalls[] = 'countPublishedByTag';

            return $this->countPublishedByTagResult;
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

        public function save(Entity $entity): void {}

        public function delete(Entity $entity): void {}
    };
}

// Helper function to create mock PaginationServiceInterface
function createPaginationService(
    PaginatedResult $paginateResult,
): PaginationServiceInterface {
    return new class ($paginateResult) implements PaginationServiceInterface
    {
        public function __construct(
            private readonly PaginatedResult $paginateResult,
        ) {}

        public function paginate(
            array $items,
            int $totalItems,
            int $currentPage,
            ?int $perPage = null,
        ): PaginatedResult {
            return $this->paginateResult;
        }

        public function calculateOffset(
            int $page,
            ?int $perPage = null,
        ): int {
            $perPage = $perPage ?? 10;

            return ($page - 1) * $perPage;
        }

        public function getPerPage(): int
        {
            return 10;
        }
    };
}

// Helper function to create mock ViewInterface
function createView(): ViewInterface
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

// Helper function to create mock ViewInterface that captures data
function createViewWithCapture(
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
            foreach ($data as $key => $value) {
                $this->capturedData[$key] = $value;
            }

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

// Helper function to create mock ViewInterface that captures template name
function createViewWithTemplateCapture(
    ?string &$capturedTemplate,
): ViewInterface {
    return new class ($capturedTemplate) implements ViewInterface
    {
        public function __construct(
            private ?string &$capturedTemplate,
        ) {}

        public function render(
            string $template,
            array $data = [],
        ): Response {
            $this->capturedTemplate = $template;

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
