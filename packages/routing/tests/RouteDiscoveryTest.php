<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteDiscovery;
use Test\DiscoveryModule\DeleteController;
use Test\DiscoveryModule\DisabledRouteController;
use Test\DiscoveryModule\GetController;
use Test\DiscoveryModule\InlineMiddlewareController;
use Test\DiscoveryModule\MiddlewareController;
use Test\DiscoveryModule\MissingAttributeController;
use Test\DiscoveryModule\MultiRouteController;
use Test\DiscoveryModule\PatchController;
use Test\DiscoveryModule\PostController;
use Test\DiscoveryModule\PutController;

beforeEach(function () {
    $this->discovery = new RouteDiscovery();
    $this->fixturesPath = __DIR__ . '/Fixtures/DiscoveryModule';
});

it('discovers routes in module src directories', function () {
    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $this->fixturesPath,
    );

    $routes = $this->discovery->discoverInModule($manifest);

    expect($routes)->toBeArray();
});

it('finds methods with Get attribute', function () {
    require_once $this->fixturesPath . '/src/GetController.php';

    $routes = $this->discovery->discoverFromClass(GetController::class);

    expect($routes)->toHaveCount(1)
        ->and($routes[0])->toBeInstanceOf(RouteDefinition::class)
        ->and($routes[0]->method)->toBe('GET');
});

it('finds methods with Post attribute', function () {
    require_once $this->fixturesPath . '/src/PostController.php';

    $routes = $this->discovery->discoverFromClass(PostController::class);

    expect($routes)->toHaveCount(1)
        ->and($routes[0])->toBeInstanceOf(RouteDefinition::class)
        ->and($routes[0]->method)->toBe('POST');
});

it('finds methods with Put attribute', function () {
    require_once $this->fixturesPath . '/src/PutController.php';

    $routes = $this->discovery->discoverFromClass(PutController::class);

    expect($routes)->toHaveCount(1)
        ->and($routes[0])->toBeInstanceOf(RouteDefinition::class)
        ->and($routes[0]->method)->toBe('PUT');
});

it('finds methods with Patch attribute', function () {
    require_once $this->fixturesPath . '/src/PatchController.php';

    $routes = $this->discovery->discoverFromClass(PatchController::class);

    expect($routes)->toHaveCount(1)
        ->and($routes[0])->toBeInstanceOf(RouteDefinition::class)
        ->and($routes[0]->method)->toBe('PATCH');
});

it('finds methods with Delete attribute', function () {
    require_once $this->fixturesPath . '/src/DeleteController.php';

    $routes = $this->discovery->discoverFromClass(DeleteController::class);

    expect($routes)->toHaveCount(1)
        ->and($routes[0])->toBeInstanceOf(RouteDefinition::class)
        ->and($routes[0]->method)->toBe('DELETE');
});

it('creates RouteDefinition for each discovered route', function () {
    require_once $this->fixturesPath . '/src/GetController.php';

    $routes = $this->discovery->discoverFromClass(GetController::class);

    expect($routes[0])->toBeInstanceOf(RouteDefinition::class);
});

it('extracts path from route attribute', function () {
    require_once $this->fixturesPath . '/src/GetController.php';

    $routes = $this->discovery->discoverFromClass(GetController::class);

    expect($routes[0]->path)->toBe('/posts');
});

it('extracts controller class name', function () {
    require_once $this->fixturesPath . '/src/GetController.php';

    $routes = $this->discovery->discoverFromClass(GetController::class);

    expect($routes[0]->controller)->toBe(GetController::class);
});

it('extracts method name', function () {
    require_once $this->fixturesPath . '/src/GetController.php';

    $routes = $this->discovery->discoverFromClass(GetController::class);

    expect($routes[0]->action)->toBe('index');
});

it('combines class-level middleware with method-level middleware', function () {
    require_once $this->fixturesPath . '/src/MiddlewareController.php';

    $routes = $this->discovery->discoverFromClass(MiddlewareController::class);

    $withMiddlewareRoute = array_values(array_filter(
        $routes,
        fn ($r) => $r->action === 'withMiddleware',
    ))[0];

    expect($withMiddlewareRoute->middleware)->toContain('ClassMiddleware')
        ->and($withMiddlewareRoute->middleware)->toContain('MethodMiddleware');
});

it('applies class middleware before method middleware', function () {
    require_once $this->fixturesPath . '/src/MiddlewareController.php';

    $routes = $this->discovery->discoverFromClass(MiddlewareController::class);

    $withMiddlewareRoute = array_values(array_filter(
        $routes,
        fn ($r) => $r->action === 'withMiddleware',
    ))[0];

    expect($withMiddlewareRoute->middleware)->toBe(['ClassMiddleware', 'MethodMiddleware']);
});

it('skips methods with DisableRoute attribute', function () {
    require_once $this->fixturesPath . '/src/DisabledRouteController.php';

    $routes = $this->discovery->discoverFromClass(DisabledRouteController::class);

    $actions = array_map(fn ($r) => $r->action, $routes);

    expect($routes)->toHaveCount(1)
        ->and($actions)->toContain('enabled')
        ->and($actions)->not->toContain('disabled');
});

it('handles multiple routes in same controller', function () {
    require_once $this->fixturesPath . '/src/MultiRouteController.php';

    $routes = $this->discovery->discoverFromClass(MultiRouteController::class);

    expect($routes)->toHaveCount(5);

    $methods = array_map(fn ($r) => $r->method, $routes);

    expect($methods)->toContain('GET')
        ->and($methods)->toContain('POST')
        ->and($methods)->toContain('PUT')
        ->and($methods)->toContain('DELETE');
});

it('includes middleware from route attribute inline parameter', function () {
    require_once $this->fixturesPath . '/src/InlineMiddlewareController.php';

    $routes = $this->discovery->discoverFromClass(InlineMiddlewareController::class);

    $inlineRoute = array_values(array_filter(
        $routes,
        fn ($r) => $r->action === 'inlineOnly',
    ))[0];

    expect($inlineRoute->middleware)->toContain('ClassMiddleware')
        ->and($inlineRoute->middleware)->toContain('InlineMiddleware');
});

it('orders class middleware before inline before method middleware', function () {
    require_once $this->fixturesPath . '/src/InlineMiddlewareController.php';

    $routes = $this->discovery->discoverFromClass(InlineMiddlewareController::class);

    $combinedRoute = array_values(array_filter(
        $routes,
        fn ($r) => $r->action === 'combinedAll',
    ))[0];

    expect($combinedRoute->middleware)->toBe(['ClassMiddleware', 'InlineMiddleware', 'MethodMiddleware']);
});

it('skips missing Marko attribute classes and still discovers routes', function () {
    require_once $this->fixturesPath . '/src/MissingAttributeController.php';

    $routes = $this->discovery->discoverFromClass(MissingAttributeController::class);

    // The #[Get] route is still discovered, the missing Marko attribute is skipped
    expect($routes)->toHaveCount(1)
        ->and($routes[0]->method)->toBe('GET')
        ->and($routes[0]->path)->toBe('/admin/dashboard');
});
