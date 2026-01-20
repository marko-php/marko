<?php

declare(strict_types=1);

use Marko\Routing\Exceptions\RouteConflictException;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;

it('stores RouteDefinition objects', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );

    $collection->add($route);

    expect($collection->count())->toBe(1);
});

it('indexes routes by method and path', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );

    $collection->add($route);

    expect($collection->has('GET', '/posts'))->toBeTrue();
});

it('retrieves route by method and path', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );

    $collection->add($route);

    expect($collection->get('GET', '/posts'))->toBe($route);
});

it('returns null for non-existent route', function () {
    $collection = new RouteCollection();

    expect($collection->get('GET', '/non-existent'))->toBeNull();
});

it('throws RouteConflictException for duplicate GET routes', function () {
    $collection = new RouteCollection();
    $route1 = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $route2 = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'AnotherController',
        action: 'list',
    );

    $collection->add($route1);
    $collection->add($route2);
})->throws(RouteConflictException::class);

it('throws RouteConflictException for duplicate POST routes', function () {
    $collection = new RouteCollection();
    $route1 = new RouteDefinition(
        method: 'POST',
        path: '/posts',
        controller: 'PostController',
        action: 'store',
    );
    $route2 = new RouteDefinition(
        method: 'POST',
        path: '/posts',
        controller: 'AnotherController',
        action: 'create',
    );

    $collection->add($route1);
    $collection->add($route2);
})->throws(RouteConflictException::class);

it('allows same path with different methods', function () {
    $collection = new RouteCollection();
    $getRoute = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $postRoute = new RouteDefinition(
        method: 'POST',
        path: '/posts',
        controller: 'PostController',
        action: 'store',
    );

    $collection->add($getRoute);
    $collection->add($postRoute);

    expect($collection->count())->toBe(2)
        ->and($collection->get('GET', '/posts'))->toBe($getRoute)
        ->and($collection->get('POST', '/posts'))->toBe($postRoute);
});

it('RouteConflictException includes both controller class names', function () {
    $collection = new RouteCollection();
    $route1 = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'App\\Controllers\\PostController',
        action: 'index',
    );
    $route2 = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'App\\Controllers\\AnotherController',
        action: 'list',
    );

    $collection->add($route1);

    try {
        $collection->add($route2);
    } catch (RouteConflictException $e) {
        $context = $e->getContext();
        expect(str_contains($context, 'App\\Controllers\\PostController'))->toBeTrue()
            ->and(str_contains($context, 'App\\Controllers\\AnotherController'))->toBeTrue();

        return;
    }

    throw new Exception('Expected RouteConflictException was not thrown');
});

it('returns all routes as array', function () {
    $collection = new RouteCollection();
    $route1 = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $route2 = new RouteDefinition(
        method: 'POST',
        path: '/posts',
        controller: 'PostController',
        action: 'store',
    );
    $route3 = new RouteDefinition(
        method: 'GET',
        path: '/users',
        controller: 'UserController',
        action: 'index',
    );

    $collection->add($route1);
    $collection->add($route2);
    $collection->add($route3);

    $allRoutes = $collection->all();

    expect($allRoutes)->toBeArray()
        ->and($allRoutes)->toHaveCount(3)
        ->and($allRoutes)->toContain($route1)
        ->and($allRoutes)->toContain($route2)
        ->and($allRoutes)->toContain($route3);
});

it('RouteConflictException includes the conflicting path', function () {
    $collection = new RouteCollection();
    $route1 = new RouteDefinition(
        method: 'GET',
        path: '/users/{id}/posts',
        controller: 'PostController',
        action: 'index',
    );
    $route2 = new RouteDefinition(
        method: 'GET',
        path: '/users/{id}/posts',
        controller: 'AnotherController',
        action: 'list',
    );

    $collection->add($route1);

    try {
        $collection->add($route2);
    } catch (RouteConflictException $e) {
        expect($e->getMessage())->toContain('/users/{id}/posts');

        return;
    }

    throw new Exception('Expected RouteConflictException was not thrown');
});

it('returns routes filtered by HTTP method', function () {
    $collection = new RouteCollection();
    $getRoute1 = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $getRoute2 = new RouteDefinition(
        method: 'GET',
        path: '/users',
        controller: 'UserController',
        action: 'index',
    );
    $postRoute = new RouteDefinition(
        method: 'POST',
        path: '/posts',
        controller: 'PostController',
        action: 'store',
    );
    $deleteRoute = new RouteDefinition(
        method: 'DELETE',
        path: '/posts/{id}',
        controller: 'PostController',
        action: 'destroy',
    );

    $collection->add($getRoute1);
    $collection->add($getRoute2);
    $collection->add($postRoute);
    $collection->add($deleteRoute);

    $getRoutes = $collection->byMethod('GET');

    expect($getRoutes)->toBeArray()
        ->and($getRoutes)->toHaveCount(2)
        ->and($getRoutes)->toContain($getRoute1)
        ->and($getRoutes)->toContain($getRoute2)
        ->and($getRoutes)->not->toContain($postRoute)
        ->and($getRoutes)->not->toContain($deleteRoute);
});
