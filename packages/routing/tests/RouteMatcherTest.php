<?php

declare(strict_types=1);

use Marko\Routing\MatchedRoute;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;

it('matches exact static path', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('GET', '/posts');

    expect($result)->toBeInstanceOf(MatchedRoute::class)
        ->and($result->route)->toBe($route);
});

it('matches path with single parameter', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts/{id}',
        controller: 'PostController',
        action: 'show',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('GET', '/posts/123');

    expect($result)->toBeInstanceOf(MatchedRoute::class)
        ->and($result->route)->toBe($route);
});

it('matches path with multiple parameters', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/users/{userId}/posts/{postId}',
        controller: 'PostController',
        action: 'show',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('GET', '/users/42/posts/123');

    expect($result)->toBeInstanceOf(MatchedRoute::class)
        ->and($result->route)->toBe($route);
});

it('extracts parameter value from matched path', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts/{id}',
        controller: 'PostController',
        action: 'show',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('GET', '/posts/123');

    expect($result->parameters)->toBe(['id' => '123']);
});

it('extracts multiple parameter values', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/users/{userId}/posts/{postId}',
        controller: 'PostController',
        action: 'show',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('GET', '/users/42/posts/123');

    expect($result->parameters)->toBe([
        'userId' => '42',
        'postId' => '123',
    ]);
});

it('returns null for non-matching path', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('GET', '/users');

    expect($result)->toBeNull();
});

it('returns null for wrong HTTP method', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('POST', '/posts');

    expect($result)->toBeNull();
});

it('matches correct route when multiple routes have same prefix', function () {
    $collection = new RouteCollection();
    $indexRoute = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $showRoute = new RouteDefinition(
        method: 'GET',
        path: '/posts/{id}',
        controller: 'PostController',
        action: 'show',
    );
    $commentsRoute = new RouteDefinition(
        method: 'GET',
        path: '/posts/{id}/comments',
        controller: 'CommentController',
        action: 'index',
    );
    $collection->add($indexRoute);
    $collection->add($showRoute);
    $collection->add($commentsRoute);

    $matcher = new RouteMatcher($collection);

    expect($matcher->match('GET', '/posts')->route)->toBe($indexRoute)
        ->and($matcher->match('GET', '/posts/123')->route)->toBe($showRoute)
        ->and($matcher->match('GET', '/posts/123/comments')->route)->toBe($commentsRoute);
});

it('returns MatchedRoute with route definition and parameters', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts/{id}',
        controller: 'PostController',
        action: 'show',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('GET', '/posts/456');

    expect($result)->toBeInstanceOf(MatchedRoute::class)
        ->and($result->route)->toBe($route)
        ->and($result->route->controller)->toBe('PostController')
        ->and($result->route->action)->toBe('show')
        ->and($result->parameters)->toBe(['id' => '456']);
});

it('handles trailing slashes consistently', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);

    // Route defined without trailing slash should match both with and without
    expect($matcher->match('GET', '/posts'))->toBeInstanceOf(MatchedRoute::class)
        ->and($matcher->match('GET', '/posts/'))->toBeInstanceOf(MatchedRoute::class);
});

it('matches root path /', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/',
        controller: 'HomeController',
        action: 'index',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('GET', '/');

    expect($result)->toBeInstanceOf(MatchedRoute::class)
        ->and($result->route)->toBe($route)
        ->and($result->parameters)->toBe([]);
});

it('memoizes match() results per (method, path)', function () {
    $collection = new RouteCollection();
    $collection->add(new RouteDefinition(
        method: 'GET',
        path: '/products/{id}',
        controller: 'ProductController',
        action: 'show',
    ));

    $matcher = new RouteMatcher($collection);

    $first = $matcher->match('GET', '/products/42');
    $second = $matcher->match('GET', '/products/42');

    // Same input must return the identical MatchedRoute object (memoized)
    expect($second)->toBe($first);
});

it('memoizes null results for unmatched (method, path) pairs', function () {
    $collection = new RouteCollection();
    $collection->add(new RouteDefinition(
        method: 'GET',
        path: '/products',
        controller: 'ProductController',
        action: 'index',
    ));

    $matcher = new RouteMatcher($collection);

    expect($matcher->match('GET', '/missing'))->toBeNull()
        ->and($matcher->match('GET', '/missing'))->toBeNull();
});

it('distinguishes cached entries by method and by path', function () {
    $collection = new RouteCollection();
    $getRoute = new RouteDefinition(
        method: 'GET',
        path: '/orders/{id}',
        controller: 'OrderController',
        action: 'show',
    );
    $postRoute = new RouteDefinition(
        method: 'POST',
        path: '/orders/{id}',
        controller: 'OrderController',
        action: 'update',
    );
    $collection->add($getRoute);
    $collection->add($postRoute);

    $matcher = new RouteMatcher($collection);

    $getMatch = $matcher->match('GET', '/orders/1');
    $postMatch = $matcher->match('POST', '/orders/1');
    $otherPath = $matcher->match('GET', '/orders/2');

    expect($getMatch->route)->toBe($getRoute)
        ->and($postMatch->route)->toBe($postRoute)
        ->and($otherPath)->not->toBe($getMatch)
        ->and($otherPath->route)->toBe($getRoute);
});
