<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
use Marko\Routing\Exceptions\RouteException;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteDiscovery;
use Test\DiscoveryModule\DeleteController;
use Test\DiscoveryModule\DisabledRouteController;
use Test\DiscoveryModule\GetController;
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

it('throws RouteException with package suggestion when attribute class is missing', function () {
    require_once $this->fixturesPath . '/src/MissingAttributeController.php';

    expect(fn () => $this->discovery->discoverFromClass(MissingAttributeController::class))
        ->toThrow(RouteException::class, 'not found');
});
