<?php

declare(strict_types=1);

use Marko\Blog\Controllers\PostController;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Repositories\AuthorRepositoryInterface;
use Marko\Blog\Repositories\CategoryRepositoryInterface;
use Marko\Blog\Repositories\CommentRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Blog\Tests\Mocks\MockAuthorRepository;
use Marko\Blog\Tests\Mocks\MockCategoryRepository;
use Marko\Blog\Tests\Mocks\MockCommentRepository;
use Marko\Blog\Tests\Mocks\MockPostRepository;
use Marko\Core\Attributes\Preference;
use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Routing\Attributes\DisableRoute;
use Marko\Routing\Attributes\InheritRoute;
use Marko\Routing\Http\Response;
use Marko\Routing\PreferenceRouteResolver;
use Marko\Routing\RouteDiscovery;
use Marko\Session\Contracts\SessionInterface;
use Marko\Testing\Fake\FakeSession;
use Marko\View\ViewInterface;

/**
 * Tests for Preference override behavior of PostController.
 * Demonstrates how demo/app/blog can override marko/blog PostController.
 */

it('demo app/blog overrides PostController via Preference', function (): void {
    $mockRepository = new MockPostRepository();
    $mockAuthorRepository = new MockAuthorRepository();
    $mockCategoryRepository = new MockCategoryRepository();
    $mockCommentRepository = new MockCommentRepository();
    $mockPaginationService = createMockPaginationServiceForPreferenceTest();
    $mockView = createMockViewForPreferenceTest();

    // Test fixture simulating demo/app/blog/Controllers/PostController
    $appPostController = new #[Preference(replaces: PostController::class)]
    class (
        $mockRepository,
        $mockAuthorRepository,
        $mockCategoryRepository,
        $mockCommentRepository,
        $mockPaginationService,
        $mockView,
        new FakeSession(),
    ) extends PostController
    {
        public function show(
            string $slug,
        ): Response {
            return new Response("Custom Blog Post: $slug - with app customizations");
        }
    };

    $preferenceRegistry = new PreferenceRegistry();
    $preferenceRegistry->register(PostController::class, $appPostController::class);

    // Container needs to be able to resolve all dependencies
    // We bind the mock instances as singletons
    $container = new Container($preferenceRegistry);
    $container->instance(PostRepositoryInterface::class, $mockRepository);
    $container->instance(AuthorRepositoryInterface::class, $mockAuthorRepository);
    $container->instance(CategoryRepositoryInterface::class, $mockCategoryRepository);
    $container->instance(CommentRepositoryInterface::class, $mockCommentRepository);
    $container->instance(PaginationServiceInterface::class, $mockPaginationService);
    $container->instance(ViewInterface::class, $mockView);
    $container->instance(SessionInterface::class, new FakeSession());

    // When requesting the original controller, we get the Preference instead
    $resolvedController = $container->get(PostController::class);

    expect($resolvedController)->toBeInstanceOf($appPostController::class);
});

it('app PostController modifies show method response', function (): void {
    $mockRepository = new MockPostRepository();
    $mockAuthorRepository = new MockAuthorRepository();
    $mockCategoryRepository = new MockCategoryRepository();
    $mockCommentRepository = new MockCommentRepository();
    $mockPaginationService = createMockPaginationServiceForPreferenceTest();
    $mockView = createMockViewForPreferenceTest();

    // Test fixture simulating demo/app/blog/Controllers/PostController
    $appPostController = new #[Preference(replaces: PostController::class)]
    class (
        $mockRepository,
        $mockAuthorRepository,
        $mockCategoryRepository,
        $mockCommentRepository,
        $mockPaginationService,
        $mockView,
        new FakeSession(),
    ) extends PostController
    {
        public function show(
            string $slug,
        ): Response {
            return new Response("Custom Blog Post: $slug - with app customizations");
        }
    };

    $response = $appPostController->show('test-post');

    expect($response->body())->toContain('Custom Blog Post')
        ->and($response->body())->toContain('test-post')
        ->and($response->body())->toContain('app customizations');
});

it('DisableRoute attribute removes route from Preference override', function (): void {
    $mockRepository = new MockPostRepository();
    $mockAuthorRepository = new MockAuthorRepository();
    $mockCategoryRepository = new MockCategoryRepository();
    $mockCommentRepository = new MockCommentRepository();
    $mockPaginationService = createMockPaginationServiceForPreferenceTest();
    $mockView = createMockViewForPreferenceTest();

    // Test fixture: child controller disables parent route
    $appPostController = new #[Preference(replaces: PostController::class)]
    class (
        $mockRepository,
        $mockAuthorRepository,
        $mockCategoryRepository,
        $mockCommentRepository,
        $mockPaginationService,
        $mockView,
        new FakeSession(),
    ) extends PostController
    {
        #[DisableRoute]
        public function index(
            int $page = 1,
        ): Response {
            return parent::index($page);
        }

        #[InheritRoute]
        public function show(
            string $slug,
        ): Response {
            return new Response("Custom Blog Post: $slug");
        }
    };

    // Check that the index method has DisableRoute attribute
    $reflection = new ReflectionClass($appPostController);
    $indexMethod = $reflection->getMethod('index');
    $disableAttributes = $indexMethod->getAttributes(DisableRoute::class);

    expect($disableAttributes)->toHaveCount(1);

    // Use PreferenceRouteResolver to verify route is disabled
    $preferenceRegistry = new PreferenceRegistry();
    $preferenceRegistry->register(PostController::class, $appPostController::class);

    $discovery = new RouteDiscovery();
    $resolver = new PreferenceRouteResolver($preferenceRegistry, $discovery);

    $routes = $resolver->resolveRoutes($appPostController::class);

    // Only the show route should be present (index is disabled)
    expect($routes)->toHaveCount(1);

    $route = $routes[0];
    expect($route->path)->toBe('/blog/{slug}')
        ->and($route->action)->toBe('show');
});

// Helper function to create mock PaginationServiceInterface for preference tests

function createMockPaginationServiceForPreferenceTest(): PaginationServiceInterface
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

// Helper function to create mock ViewInterface for preference tests

function createMockViewForPreferenceTest(): ViewInterface
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
