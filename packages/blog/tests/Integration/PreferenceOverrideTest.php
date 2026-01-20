<?php

declare(strict_types=1);

use Marko\Blog\Controllers\PostController;
use Marko\Core\Attributes\Preference;
use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Routing\Attributes\DisableRoute;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\InheritRoute;
use Marko\Routing\Http\Response;
use Marko\Routing\PreferenceRouteResolver;
use Marko\Routing\RouteDiscovery;

/**
 * Tests for Preference override behavior of PostController.
 * Demonstrates how demo/app/blog can override marko/blog PostController.
 */

it('demo app/blog overrides PostController via Preference', function (): void {
    // Test fixture simulating demo/app/blog/Controllers/PostController
    $appPostController = new #[Preference(replaces: PostController::class)]
    class extends PostController {
        public function show(string $slug): Response
        {
            return new Response("Custom Blog Post: {$slug} - with app customizations");
        }
    };

    $preferenceRegistry = new PreferenceRegistry();
    $preferenceRegistry->register(PostController::class, $appPostController::class);

    $container = new Container($preferenceRegistry);

    // When requesting the original controller, we get the Preference instead
    $resolvedController = $container->get(PostController::class);

    expect($resolvedController)->toBeInstanceOf($appPostController::class);
});

it('app PostController modifies show method response', function (): void {
    // Test fixture simulating demo/app/blog/Controllers/PostController
    $appPostController = new #[Preference(replaces: PostController::class)]
    class extends PostController {
        public function show(string $slug): Response
        {
            return new Response("Custom Blog Post: {$slug} - with app customizations");
        }
    };

    $response = $appPostController->show('test-post');

    expect($response->body())->toContain('Custom Blog Post')
        ->and($response->body())->toContain('test-post')
        ->and($response->body())->toContain('app customizations');
});

it('DisableRoute attribute removes route from Preference override', function (): void {
    // Test fixture: child controller disables parent route
    $appPostController = new #[Preference(replaces: PostController::class)]
    class extends PostController {
        #[DisableRoute]
        public function index(): Response
        {
            return parent::index();
        }

        #[InheritRoute]
        public function show(string $slug): Response
        {
            return new Response("Custom Blog Post: {$slug}");
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
