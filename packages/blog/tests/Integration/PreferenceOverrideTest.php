<?php

declare(strict_types=1);

use Marko\Blog\Controllers\PostController;
use Marko\Blog\Entity\Post;
use Marko\Blog\Repositories\PostRepository;
use Marko\Core\Attributes\Preference;
use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Routing\Attributes\DisableRoute;
use Marko\Routing\Attributes\InheritRoute;
use Marko\Routing\Http\Response;
use Marko\Routing\PreferenceRouteResolver;
use Marko\Routing\RouteDiscovery;
use Marko\View\ViewInterface;

/**
 * Tests for Preference override behavior of PostController.
 * Demonstrates how demo/app/blog can override marko/blog PostController.
 */

it('demo app/blog overrides PostController via Preference', function (): void {
    $mockRepository = createMockPostRepositoryForPreferenceTest();
    $mockView = createMockViewForPreferenceTest();

    // Test fixture simulating demo/app/blog/Controllers/PostController
    $appPostController = new #[Preference(replaces: PostController::class)]
    class ($mockRepository, $mockView) extends PostController
    {
        public function show(
            string $slug,
        ): Response {
            return new Response("Custom Blog Post: $slug - with app customizations");
        }
    };

    $preferenceRegistry = new PreferenceRegistry();
    $preferenceRegistry->register(PostController::class, $appPostController::class);

    // Container needs to be able to resolve PostRepository and ViewInterface dependencies
    // We bind the mock instances as singletons
    $container = new Container($preferenceRegistry);
    $container->instance(PostRepository::class, $mockRepository);
    $container->instance(ViewInterface::class, $mockView);

    // When requesting the original controller, we get the Preference instead
    $resolvedController = $container->get(PostController::class);

    expect($resolvedController)->toBeInstanceOf($appPostController::class);
});

it('app PostController modifies show method response', function (): void {
    $mockRepository = createMockPostRepositoryForPreferenceTest();
    $mockView = createMockViewForPreferenceTest();

    // Test fixture simulating demo/app/blog/Controllers/PostController
    $appPostController = new #[Preference(replaces: PostController::class)]
    class ($mockRepository, $mockView) extends PostController
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
    $mockRepository = createMockPostRepositoryForPreferenceTest();
    $mockView = createMockViewForPreferenceTest();

    // Test fixture: child controller disables parent route
    $appPostController = new #[Preference(replaces: PostController::class)]
    class ($mockRepository, $mockView) extends PostController
    {
        #[DisableRoute]
        public function index(): Response
        {
            return parent::index();
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

// Helper function to create mock PostRepository for preference tests

function createMockPostRepositoryForPreferenceTest(): PostRepository
{
    return new class () extends PostRepository
    {
        public function __construct()
        {
            // Skip parent constructor - we're mocking everything
        }

        public function findAll(): array
        {
            return [];
        }

        public function findBySlug(
            string $slug,
        ): ?Post {
            return null;
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
