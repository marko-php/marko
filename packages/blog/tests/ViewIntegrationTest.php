<?php

declare(strict_types=1);

use Marko\Blog\Controllers\PostController;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Blog\Tests\Mocks\MockAuthorRepository;
use Marko\Blog\Tests\Mocks\MockCategoryRepository;
use Marko\Blog\Tests\Mocks\MockCommentRepository;
use Marko\Database\Entity\Entity;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

it('has resources/views directory', function (): void {
    $viewsPath = dirname(__DIR__) . '/resources/views';

    expect(is_dir($viewsPath))->toBeTrue()
        ->and($viewsPath)->toBeDirectory();
});

it('PostController uses ViewInterface', function (): void {
    $reflection = new ReflectionClass(PostController::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->not->toBeNull();

    $parameters = $constructor->getParameters();
    $parameterTypes = array_map(
        fn (ReflectionParameter $p) => $p->getType()?->getName(),
        $parameters,
    );

    expect($parameterTypes)->toContain(ViewInterface::class);
});

it('has post/index.latte template file', function (): void {
    $templatePath = dirname(__DIR__) . '/resources/views/post/index.latte';

    expect($templatePath)->toBeFile();
});

it('renders post index template', function (): void {
    $posts = [createViewTestPost(1, 'First Post', 'first-post'), createViewTestPost(2, 'Second Post', 'second-post')];
    $repository = createViewTestMockRepository($posts);
    $authorRepository = new MockAuthorRepository();
    $categoryRepository = new MockCategoryRepository();
    $commentRepository = new MockCommentRepository();
    $paginationService = createViewTestMockPaginationService();
    $capture = new stdClass();
    $view = createViewTestCapturingMockView($capture);

    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $paginationService,
        $view
    );
    $response = $controller->index();

    expect($capture->template)->toBe('blog::post/index')
        ->and($capture->data)->toHaveKey('posts');
});

it('has post/show.latte template file', function (): void {
    $templatePath = dirname(__DIR__) . '/resources/views/post/show.latte';

    expect($templatePath)->toBeFile();
});

it('renders post show template', function (): void {
    $post = createViewTestPost(1, 'My Post', 'my-post');
    $repository = createViewTestMockRepository([], $post);
    $authorRepository = new MockAuthorRepository();
    $categoryRepository = new MockCategoryRepository();
    $commentRepository = new MockCommentRepository();
    $paginationService = createViewTestMockPaginationService();
    $capture = new stdClass();
    $view = createViewTestCapturingMockView($capture);

    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $paginationService,
        $view
    );
    $response = $controller->show('my-post');

    expect($capture->template)->toBe('blog::post/show')
        ->and($capture->data)->toHaveKey('post')
        ->and($capture->data['post']->getTitle())->toBe('My Post');
});

// Helper functions

function createViewTestPost(
    int $id,
    string $title,
    string $slug,
): Post {
    $post = new Post(
        title: $title,
        content: "Content for $title",
        authorId: 1,
        slug: $slug,
    );
    $post->id = $id;
    $post->status = PostStatus::Published;
    $post->publishedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');

    return $post;
}

function createViewTestMockRepository(
    array $posts = [],
    ?Post $findBySlugResult = null,
): PostRepositoryInterface {
    return new class ($posts, $findBySlugResult) implements PostRepositoryInterface
    {
        public function __construct(
            private readonly array $posts,
            private readonly ?Post $findBySlugEntity,
        ) {}

        public function find(
            int $id,
        ): ?Post {
            return null;
        }

        public function findOrFail(
            int $id,
        ): Post {
            throw new RuntimeException('Not found');
        }

        public function findAll(): array
        {
            return $this->posts;
        }

        public function findBy(
            array $criteria,
        ): array {
            return [];
        }

        public function findOneBy(
            array $criteria,
        ): ?Post {
            return null;
        }

        public function findBySlug(
            string $slug,
        ): ?Post {
            return $this->findBySlugEntity;
        }

        public function findPublished(): array
        {
            return $this->posts;
        }

        public function findPublishedPaginated(
            int $limit,
            int $offset,
        ): array {
            return $this->posts;
        }

        public function countPublished(): int
        {
            return count($this->posts);
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

        public function save(Entity $entity): void {}

        public function delete(Entity $entity): void {}
    };
}

function createViewTestMockPaginationService(): PaginationServiceInterface
{
    return new class () implements PaginationServiceInterface
    {
        public function paginate(
            array $items,
            int $totalItems,
            int $currentPage,
            ?int $perPage = null,
        ): PaginatedResult {
            $perPage = $perPage ?? 10;
            $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $perPage) : 0;

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

function createViewTestCapturingMockView(
    stdClass $capture,
): ViewInterface {
    return new class ($capture) implements ViewInterface
    {
        public function __construct(
            private stdClass $capture,
        ) {}

        public function render(
            string $template,
            array $data = [],
        ): Response {
            $this->capture->template = $template;
            $this->capture->data = $data;

            return new Response('rendered');
        }

        public function renderToString(
            string $template,
            array $data = [],
        ): string {
            $this->capture->template = $template;
            $this->capture->data = $data;

            return 'rendered';
        }
    };
}
