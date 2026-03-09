<?php

declare(strict_types=1);

use Marko\Blog\Controllers\AuthorController;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Author;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Repositories\AuthorRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Database\Entity\Entity;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

it('injects AuthorRepositoryInterface and PostRepositoryInterface not concrete classes', function (): void {
    $reflection = new ReflectionClass(AuthorController::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->not->toBeNull();

    $parameters = $constructor->getParameters();

    // Find the repository parameters
    $authorRepoParam = null;
    $postRepoParam = null;

    foreach ($parameters as $param) {
        $typeName = $param->getType()?->getName();
        if ($typeName === AuthorRepositoryInterface::class) {
            $authorRepoParam = $param;
        }
        if ($typeName === PostRepositoryInterface::class) {
            $postRepoParam = $param;
        }
    }

    expect($authorRepoParam)->not->toBeNull('AuthorRepositoryInterface should be injected')
        ->and($postRepoParam)->not->toBeNull('PostRepositoryInterface should be injected');
});

it('returns paginated posts by author at GET /blog/author/{slug}', function (): void {
    $reflection = new ReflectionClass(AuthorController::class);

    // Check that show method exists
    expect($reflection->hasMethod('show'))->toBeTrue();

    $method = $reflection->getMethod('show');
    $attributes = $method->getAttributes(Get::class);

    expect($attributes)->toHaveCount(1);

    $routeAttribute = $attributes[0]->newInstance();
    expect($routeAttribute->path)->toBe('/blog/author/{slug}');
});

it('returns 404 when author slug not found', function (): void {
    $authorRepository = authorControllerCreateAuthorRepository();
    $postRepository = authorControllerCreatePostRepository();
    $paginationService = authorControllerCreatePaginationService();
    $view = authorControllerCreateView();

    $controller = new AuthorController(
        $authorRepository,
        $postRepository,
        $paginationService,
        $view,
    );

    $response = $controller->show('non-existent-author');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(404)
        ->and($response->body())->toContain('not found');
});

it('includes author name email and bio in response', function (): void {
    $author = authorControllerCreateAuthor(
        id: 1,
        name: 'Jane Smith',
        email: 'jane@example.com',
        bio: 'An experienced PHP developer',
        slug: 'jane-smith',
    );

    $capturedData = new stdClass();
    $capturedData->data = null;

    $authorRepository = authorControllerCreateAuthorRepository($author);
    $postRepository = authorControllerCreatePostRepository();
    $paginationService = authorControllerCreatePaginationService();
    $view = authorControllerCreateViewWithCapture($capturedData);

    $controller = new AuthorController(
        $authorRepository,
        $postRepository,
        $paginationService,
        $view,
    );

    $controller->show('jane-smith');

    expect($capturedData->data)->not->toBeNull()
        ->and($capturedData->data['author'])->toBeInstanceOf(Author::class)
        ->and($capturedData->data['author']->getName())->toBe('Jane Smith')
        ->and($capturedData->data['author']->getEmail())->toBe('jane@example.com')
        ->and($capturedData->data['author']->getBio())->toBe('An experienced PHP developer');
});

it('only includes published posts', function (): void {
    $author = authorControllerCreateAuthor(id: 1, slug: 'john-doe');

    // Create published posts only (simulates what the repository returns)
    $publishedPosts = [
        authorControllerCreatePost(1, 'Published Post 1', status: PostStatus::Published),
        authorControllerCreatePost(2, 'Published Post 2', status: PostStatus::Published),
    ];

    $capturedData = new stdClass();
    $capturedData->data = null;

    // The mock repository's findPublishedByAuthor method returns only published posts
    $authorRepository = authorControllerCreateAuthorRepository($author);
    $postRepository = authorControllerCreatePostRepository(
        findPublishedByAuthorResult: $publishedPosts,
        countPublishedByAuthorResult: 2,
    );
    $paginatedResult = authorControllerCreatePaginatedResult($publishedPosts, totalItems: 2);
    $paginationService = authorControllerCreatePaginationService($paginatedResult);
    $view = authorControllerCreateViewWithCapture($capturedData);

    $controller = new AuthorController(
        $authorRepository,
        $postRepository,
        $paginationService,
        $view,
    );

    $controller->show('john-doe');

    // Verify the paginated result contains only published posts
    expect($capturedData->data)->not->toBeNull()
        ->and($capturedData->data['posts'])->toBeInstanceOf(PaginatedResult::class)
        ->and($capturedData->data['posts']->items)->toHaveCount(2)
        ->and($capturedData->data['posts']->items[0]->isPublished())->toBeTrue()
        ->and($capturedData->data['posts']->items[1]->isPublished())->toBeTrue();
});

it('orders posts by published date descending', function (): void {
    $author = authorControllerCreateAuthor(id: 1, slug: 'john-doe');

    // Posts are returned in descending order by published_at (repository does the ordering)
    $orderedPosts = [
        authorControllerCreatePost(2, 'Newer Post', publishedAt: '2024-02-01 10:00:00'),
        authorControllerCreatePost(1, 'Older Post', publishedAt: '2024-01-01 10:00:00'),
    ];

    $capturedData = new stdClass();
    $capturedData->data = null;

    $authorRepository = authorControllerCreateAuthorRepository($author);
    $postRepository = authorControllerCreatePostRepository(
        findPublishedByAuthorResult: $orderedPosts,
        countPublishedByAuthorResult: 2,
    );
    $paginatedResult = authorControllerCreatePaginatedResult($orderedPosts, totalItems: 2);
    $paginationService = authorControllerCreatePaginationService($paginatedResult);
    $view = authorControllerCreateViewWithCapture($capturedData);

    $controller = new AuthorController(
        $authorRepository,
        $postRepository,
        $paginationService,
        $view,
    );

    $controller->show('john-doe');

    // Verify posts are ordered by published_at descending (newer first)
    expect($capturedData->data)->not->toBeNull()
        ->and($capturedData->data['posts']->items)->toHaveCount(2)
        ->and($capturedData->data['posts']->items[0]->getPublishedAt()->format('Y-m-d'))
            ->toBe('2024-02-01')
        ->and($capturedData->data['posts']->items[1]->getPublishedAt()->format('Y-m-d'))
            ->toBe('2024-01-01');
});

it('accepts page query parameter for pagination', function (): void {
    $reflection = new ReflectionClass(AuthorController::class);
    $method = $reflection->getMethod('show');

    $parameters = $method->getParameters();
    $pageParam = null;

    foreach ($parameters as $param) {
        if ($param->getName() === 'page') {
            $pageParam = $param;
            break;
        }
    }

    expect($pageParam)->not->toBeNull('show method should have a page parameter')
        ->and($pageParam->getType()?->getName())->toBe('int')
        ->and($pageParam->isDefaultValueAvailable())->toBeTrue()
        ->and($pageParam->getDefaultValue())->toBe(1);
});

it('includes pagination metadata in response', function (): void {
    $author = authorControllerCreateAuthor(id: 1, slug: 'john-doe');

    $posts = [
        authorControllerCreatePost(1, 'Post 1'),
        authorControllerCreatePost(2, 'Post 2'),
    ];

    $capturedData = new stdClass();
    $capturedData->data = null;

    $authorRepository = authorControllerCreateAuthorRepository($author);
    $postRepository = authorControllerCreatePostRepository(
        findPublishedByAuthorResult: $posts,
        countPublishedByAuthorResult: 25, // Total of 25 posts
    );
    $paginatedResult = authorControllerCreatePaginatedResult(
        items: $posts,
        currentPage: 2,
        totalItems: 25,
        perPage: 10,
    );
    $paginationService = authorControllerCreatePaginationService($paginatedResult);
    $view = authorControllerCreateViewWithCapture($capturedData);

    $controller = new AuthorController(
        $authorRepository,
        $postRepository,
        $paginationService,
        $view,
    );

    $controller->show('john-doe', 2);

    // Verify pagination metadata is available
    expect($capturedData->data)->not->toBeNull()
        ->and($capturedData->data['posts'])->toBeInstanceOf(PaginatedResult::class)
        ->and($capturedData->data['posts']->currentPage)->toBe(2)
        ->and($capturedData->data['posts']->totalItems)->toBe(25)
        ->and($capturedData->data['posts']->perPage)->toBe(10)
        ->and($capturedData->data['posts']->totalPages)->toBe(3)
        ->and($capturedData->data['posts']->hasPreviousPage)->toBeTrue()
        ->and($capturedData->data['posts']->hasNextPage)->toBeTrue();
});

it('renders using view template', function (): void {
    $author = authorControllerCreateAuthor(id: 1, slug: 'john-doe');

    $capturedData = new stdClass();
    $capturedData->template = null;

    $authorRepository = authorControllerCreateAuthorRepository($author);
    $postRepository = authorControllerCreatePostRepository();
    $paginationService = authorControllerCreatePaginationService();
    $view = authorControllerCreateViewWithCapture($capturedData);

    $controller = new AuthorController(
        $authorRepository,
        $postRepository,
        $paginationService,
        $view,
    );

    $response = $controller->show('john-doe');

    expect($capturedData->template)->toBe('blog::author/show')
        ->and($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200);
});

// Helper function to create a mock Author
function authorControllerCreateAuthor(
    int $id = 1,
    string $name = 'John Doe',
    string $email = 'john@example.com',
    ?string $bio = 'A writer',
    string $slug = 'john-doe',
): Author {
    $author = new Author();
    $author->id = $id;
    $author->name = $name;
    $author->email = $email;
    $author->bio = $bio;
    $author->slug = $slug;

    return $author;
}

// Helper function to create a mock Post
function authorControllerCreatePost(
    int $id,
    string $title,
    string $slug = '',
    int $authorId = 1,
    PostStatus $status = PostStatus::Published,
    ?string $publishedAt = null,
): Post {
    $post = new Post();
    $post->id = $id;
    $post->title = $title;
    $post->slug = $slug ?: strtolower(str_replace(' ', '-', $title));
    $post->authorId = $authorId;
    $post->status = $status;
    $post->publishedAt = $publishedAt ?? '2024-01-15 10:00:00';

    return $post;
}

// Helper function to create mock PaginatedResult
function authorControllerCreatePaginatedResult(
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

// Helper function to create mock AuthorRepositoryInterface
function authorControllerCreateAuthorRepository(
    ?Author $findBySlugResult = null,
): AuthorRepositoryInterface {
    return new readonly class ($findBySlugResult) implements AuthorRepositoryInterface
    {
        public function __construct(
            private ?Author $findBySlugResult,
        ) {}

        public function findBySlug(
            string $slug,
        ): ?Author {
            return $this->findBySlugResult;
        }

        public function findByEmail(
            string $email,
        ): ?Author {
            return null;
        }

        public function isSlugUnique(
            string $slug,
            ?int $excludeId = null,
        ): bool {
            return true;
        }

        public function find(
            int $id,
        ): ?Entity {
            return null;
        }

        public function findOrFail(
            int $id,
        ): Entity {
            throw new RuntimeException('Entity not found');
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

        public function existsBy(
            array $criteria,
        ): bool {
            return $this->findOneBy(criteria: $criteria) !== null;
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
function authorControllerCreatePostRepository(
    array $findPublishedByAuthorResult = [],
    int $countPublishedByAuthorResult = 0,
): PostRepositoryInterface {
    return new readonly class ($findPublishedByAuthorResult, $countPublishedByAuthorResult) implements PostRepositoryInterface
    {
        public function __construct(
            private array $findPublishedByAuthorResult,
            private int $countPublishedByAuthorResult,
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

        public function isSlugUnique(
            string $slug,
            ?int $excludeId = null,
        ): bool {
            return true;
        }

        public function findPublishedByAuthor(
            int $authorId,
            int $limit,
            int $offset,
        ): array {
            return $this->findPublishedByAuthorResult;
        }

        public function countPublishedByAuthor(
            int $authorId,
        ): int {
            return $this->countPublishedByAuthorResult ?: count($this->findPublishedByAuthorResult);
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
            throw new RuntimeException('Entity not found');
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

        public function existsBy(
            array $criteria,
        ): bool {
            return $this->findOneBy(criteria: $criteria) !== null;
        }

        public function save(
            Entity $entity,
        ): void {}

        public function delete(
            Entity $entity,
        ): void {}
    };
}

// Helper function to create mock PaginationServiceInterface
function authorControllerCreatePaginationService(
    ?PaginatedResult $paginateResult = null,
): PaginationServiceInterface {
    $paginateResult = $paginateResult ?? authorControllerCreatePaginatedResult();

    return new readonly class ($paginateResult) implements PaginationServiceInterface
    {
        public function __construct(
            private PaginatedResult $paginateResult,
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
function authorControllerCreateView(): ViewInterface
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
function authorControllerCreateViewWithCapture(
    object $capturedData,
): ViewInterface {
    return new readonly class ($capturedData) implements ViewInterface
    {
        public function __construct(
            private object $capturedData,
        ) {}

        public function render(
            string $template,
            array $data = [],
        ): Response {
            $this->capturedData->data = $data;
            $this->capturedData->template = $template;

            return new Response("rendered: $template");
        }

        public function renderToString(
            string $template,
            array $data = [],
        ): string {
            $this->capturedData->data = $data;
            $this->capturedData->template = $template;

            return "rendered: $template";
        }
    };
}
