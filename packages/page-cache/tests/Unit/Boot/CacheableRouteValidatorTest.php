<?php

declare(strict_types=1);

use Marko\PageCache\Attributes\Cacheable;
use Marko\PageCache\Boot\CacheableRouteValidator;
use Marko\PageCache\Config\PageCacheConfig;
use Marko\PageCache\Exceptions\PageCacheException;
use Marko\PageCache\Middleware\PageCacheMiddleware;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Testing\Fake\FakeConfigRepository;

class RouteValidatorFixtureController
{
    #[Cacheable(ttl: 300)]
    public function cached(): void {}

    public function uncached(): void {}
}

class AccountAuthMiddleware {}

class LocaleMiddleware {}

function makeCacheableRouteValidator(
    array $patterns = ['*Auth*'],
): CacheableRouteValidator {
    return new CacheableRouteValidator(new PageCacheConfig(new FakeConfigRepository([
        'page-cache.auth_middleware_patterns' => $patterns,
        'page-cache.cacheable_methods' => ['GET', 'HEAD'],
    ])));
}

function makeValidatorRoutes(
    RouteDefinition ...$routes,
): RouteCollection {
    $collection = new RouteCollection();

    foreach ($routes as $route) {
        $collection->add($route);
    }

    return $collection;
}

it('fails loudly when a cacheable route uses authentication middleware', function (): void {
    $routes = makeValidatorRoutes(new RouteDefinition(
        method: 'GET',
        path: '/account/orders',
        controller: RouteValidatorFixtureController::class,
        action: 'cached',
        middleware: [AccountAuthMiddleware::class],
    ));

    expect(fn () => makeCacheableRouteValidator()->validate($routes))
        ->toThrow(PageCacheException::class, "Route '/account/orders'");
});

it('matches auth middleware patterns case-insensitively against the short class name', function (): void {
    $routes = makeValidatorRoutes(new RouteDefinition(
        method: 'GET',
        path: '/account',
        controller: RouteValidatorFixtureController::class,
        action: 'cached',
        middleware: ['App\\Http\\RequireLogin'],
    ));

    expect(fn () => makeCacheableRouteValidator(['requirelogin'])->validate($routes))
        ->toThrow(PageCacheException::class, 'App\\Http\\RequireLogin');
});

it('allows cacheable routes whose middleware matches no auth pattern', function (): void {
    $routes = makeValidatorRoutes(new RouteDefinition(
        method: 'GET',
        path: '/products',
        controller: RouteValidatorFixtureController::class,
        action: 'cached',
        middleware: [LocaleMiddleware::class],
    ));

    makeCacheableRouteValidator()->validate($routes);

    expect($routes->count())->toBe(1);
});

it('allows auth middleware on routes without #[Cacheable]', function (): void {
    $routes = makeValidatorRoutes(new RouteDefinition(
        method: 'GET',
        path: '/account',
        controller: RouteValidatorFixtureController::class,
        action: 'uncached',
        middleware: [AccountAuthMiddleware::class],
    ));

    makeCacheableRouteValidator()->validate($routes);

    expect($routes->count())->toBe(1);
});

it('skips routes that exclude the page cache or the auth middleware', function (): void {
    $routes = makeValidatorRoutes(
        new RouteDefinition(
            method: 'GET',
            path: '/no-page-cache',
            controller: RouteValidatorFixtureController::class,
            action: 'cached',
            middleware: [AccountAuthMiddleware::class],
            withoutMiddleware: [PageCacheMiddleware::class],
        ),
        new RouteDefinition(
            method: 'GET',
            path: '/no-auth',
            controller: RouteValidatorFixtureController::class,
            action: 'cached',
            middleware: [AccountAuthMiddleware::class],
            withoutMiddleware: [AccountAuthMiddleware::class],
        ),
    );

    makeCacheableRouteValidator()->validate($routes);

    expect($routes->count())->toBe(2);
});

it('skips routes whose method is never cached', function (): void {
    $routes = makeValidatorRoutes(new RouteDefinition(
        method: 'POST',
        path: '/account',
        controller: RouteValidatorFixtureController::class,
        action: 'cached',
        middleware: [AccountAuthMiddleware::class],
    ));

    makeCacheableRouteValidator()->validate($routes);

    expect($routes->count())->toBe(1);
});

it('disables the check when no auth middleware patterns are configured', function (): void {
    $routes = makeValidatorRoutes(new RouteDefinition(
        method: 'GET',
        path: '/account',
        controller: RouteValidatorFixtureController::class,
        action: 'cached',
        middleware: [AccountAuthMiddleware::class],
    ));

    makeCacheableRouteValidator([])->validate($routes);

    expect($routes->count())->toBe(1);
});
