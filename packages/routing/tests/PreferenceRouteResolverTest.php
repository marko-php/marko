<?php

declare(strict_types=1);

use Marko\Core\Container\PreferenceRegistry;
use Marko\Routing\Exceptions\RouteException;
use Marko\Routing\PreferenceRouteResolver;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteDiscovery;
use Test\PreferenceRoutes\ChildNotOverridingController;
use Test\PreferenceRoutes\ChildWithAmbiguousOverrideController;
use Test\PreferenceRoutes\ChildWithDisabledRouteController;
use Test\PreferenceRoutes\ChildWithOwnRouteController;
use Test\PreferenceRoutes\GrandchildController;
use Test\PreferenceRoutes\GrandparentController;
use Test\PreferenceRoutes\MiddleController;
use Test\PreferenceRoutes\ParentController;
use Test\PreferenceRoutes\StandaloneController;

beforeEach(function () {
    $this->registry = new PreferenceRegistry();
    $this->discovery = new RouteDiscovery();
    $this->resolver = new PreferenceRouteResolver($this->registry, $this->discovery);
    $this->fixturesPath = __DIR__ . '/Fixtures/PreferenceRoutes';
});

it('inherits parent route when method not overridden', function () {
    require_once $this->fixturesPath . '/ParentController.php';
    require_once $this->fixturesPath . '/ChildNotOverridingController.php';

    // Register the preference
    $this->registry->register(ParentController::class, ChildNotOverridingController::class);

    // Resolve routes for the child class
    $routes = $this->resolver->resolveRoutes(ChildNotOverridingController::class);

    expect($routes)->toHaveCount(2)
        ->and($routes[0])->toBeInstanceOf(RouteDefinition::class)
        ->and($routes[0]->path)->toBe('/posts')
        ->and($routes[0]->controller)->toBe(ChildNotOverridingController::class)
        ->and($routes[0]->action)->toBe('index');
});

it('uses child route when method overridden with route attribute', function () {
    require_once $this->fixturesPath . '/ParentController.php';
    require_once $this->fixturesPath . '/ChildWithOwnRouteController.php';

    // Register the preference
    $this->registry->register(ParentController::class, ChildWithOwnRouteController::class);

    // Resolve routes for the child class
    $routes = $this->resolver->resolveRoutes(ChildWithOwnRouteController::class);

    // Find the index route
    $indexRoute = null;
    foreach ($routes as $route) {
        if ($route->action === 'index') {
            $indexRoute = $route;
            break;
        }
    }

    expect($indexRoute)->not->toBeNull()
        ->and($indexRoute->path)->toBe('/custom-posts')
        ->and($indexRoute->controller)->toBe(ChildWithOwnRouteController::class);
});

it('removes route when method overridden with DisableRoute', function () {
    require_once $this->fixturesPath . '/ParentController.php';
    require_once $this->fixturesPath . '/ChildWithDisabledRouteController.php';

    // Register the preference
    $this->registry->register(ParentController::class, ChildWithDisabledRouteController::class);

    // Resolve routes for the child class
    $routes = $this->resolver->resolveRoutes(ChildWithDisabledRouteController::class);

    // index route should not exist (was disabled)
    $actions = array_map(fn ($r) => $r->action, $routes);

    expect($routes)->toHaveCount(1)
        ->and($actions)->not->toContain('index')
        ->and($actions)->toContain('show');
});

it('throws RouteException when method overridden without route attribute', function () {
    require_once $this->fixturesPath . '/ParentController.php';
    require_once $this->fixturesPath . '/ChildWithAmbiguousOverrideController.php';

    // Register the preference
    $this->registry->register(ParentController::class, ChildWithAmbiguousOverrideController::class);

    // This should throw because index() is overridden but has no route or disable attribute
    $this->resolver->resolveRoutes(ChildWithAmbiguousOverrideController::class);
})->throws(RouteException::class);

it('RouteException for ambiguous override includes class and method names', function () {
    require_once $this->fixturesPath . '/ParentController.php';
    require_once $this->fixturesPath . '/ChildWithAmbiguousOverrideController.php';

    // Register the preference
    $this->registry->register(ParentController::class, ChildWithAmbiguousOverrideController::class);

    try {
        $this->resolver->resolveRoutes(ChildWithAmbiguousOverrideController::class);
        $this->fail('Expected RouteException to be thrown');
    } catch (RouteException $e) {
        expect($e->getMessage())->toContain('index')
            ->and($e->getContext())->toContain(ParentController::class)
            ->and($e->getContext())->toContain(ChildWithAmbiguousOverrideController::class);
    }
});

it('RouteException suggests adding route attribute or DisableRoute', function () {
    require_once $this->fixturesPath . '/ParentController.php';
    require_once $this->fixturesPath . '/ChildWithAmbiguousOverrideController.php';

    // Register the preference
    $this->registry->register(ParentController::class, ChildWithAmbiguousOverrideController::class);

    try {
        $this->resolver->resolveRoutes(ChildWithAmbiguousOverrideController::class);
        $this->fail('Expected RouteException to be thrown');
    } catch (RouteException $e) {
        expect($e->getSuggestion())->toContain('#[Route]');
    }
});

it('handles multi-level inheritance (grandparent routes)', function () {
    require_once $this->fixturesPath . '/GrandparentController.php';
    require_once $this->fixturesPath . '/MiddleController.php';
    require_once $this->fixturesPath . '/GrandchildController.php';

    // Register preferences
    $this->registry->register(GrandparentController::class, MiddleController::class);
    $this->registry->register(MiddleController::class, GrandchildController::class);

    // Resolve routes for the grandchild class
    $routes = $this->resolver->resolveRoutes(GrandchildController::class);

    // Find routes by action
    $listRoute = null;
    $viewRoute = null;
    foreach ($routes as $route) {
        if ($route->action === 'list') {
            $listRoute = $route;
        }
        if ($route->action === 'view') {
            $viewRoute = $route;
        }
    }

    // list() should be inherited from grandparent (via middle which didn't override it)
    expect($listRoute)->not->toBeNull()
        ->and($listRoute->path)->toBe('/articles')
        ->and($listRoute->controller)->toBe(GrandchildController::class);

    // view() should be inherited from middle (which overrode grandparent)
    expect($viewRoute)->not->toBeNull()
        ->and($viewRoute->path)->toBe('/custom-articles/{id}')
        ->and($viewRoute->controller)->toBe(GrandchildController::class);
});

it('correctly identifies Preference relationships via registry', function () {
    require_once $this->fixturesPath . '/ParentController.php';
    require_once $this->fixturesPath . '/ChildNotOverridingController.php';

    // Register the preference
    $this->registry->register(ParentController::class, ChildNotOverridingController::class);

    // The resolver should be able to identify the relationship via registry
    $isPreference = $this->resolver->isPreferenceFor(
        ChildNotOverridingController::class,
        ParentController::class,
    );

    expect($isPreference)->toBeTrue();
});

it('returns false for non-preference relationship', function () {
    require_once $this->fixturesPath . '/ParentController.php';
    require_once $this->fixturesPath . '/ChildNotOverridingController.php';

    // DO NOT register the preference - registry is empty

    // The resolver should return false since no preference is registered
    $isPreference = $this->resolver->isPreferenceFor(
        ChildNotOverridingController::class,
        ParentController::class,
    );

    expect($isPreference)->toBeFalse();
});

it('inherited routes use child controller class for dispatch', function () {
    require_once $this->fixturesPath . '/ParentController.php';
    require_once $this->fixturesPath . '/ChildNotOverridingController.php';

    // Register the preference
    $this->registry->register(ParentController::class, ChildNotOverridingController::class);

    // Resolve routes for the child class
    $routes = $this->resolver->resolveRoutes(ChildNotOverridingController::class);

    // All routes should use child controller class, not parent
    foreach ($routes as $route) {
        expect($route->controller)->toBe(ChildNotOverridingController::class)
            ->and($route->controller)->not->toBe(ParentController::class);
    }
});

it('handles controller with no parent (no inheritance logic)', function () {
    require_once $this->fixturesPath . '/StandaloneController.php';

    // Resolve routes for standalone controller (no parent)
    $routes = $this->resolver->resolveRoutes(StandaloneController::class);

    expect($routes)->toHaveCount(2);

    $indexRoute = null;
    $createRoute = null;
    foreach ($routes as $route) {
        if ($route->action === 'index') {
            $indexRoute = $route;
        }
        if ($route->action === 'create') {
            $createRoute = $route;
        }
    }

    expect($indexRoute)->not->toBeNull()
        ->and($indexRoute->path)->toBe('/standalone')
        ->and($indexRoute->controller)->toBe(StandaloneController::class)
        ->and($indexRoute->method)->toBe('GET');

    expect($createRoute)->not->toBeNull()
        ->and($createRoute->path)->toBe('/standalone/create')
        ->and($createRoute->controller)->toBe(StandaloneController::class)
        ->and($createRoute->method)->toBe('POST');
});
