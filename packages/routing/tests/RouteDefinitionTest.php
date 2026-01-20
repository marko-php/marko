<?php

declare(strict_types=1);

use Marko\Routing\RouteDefinition;

it('RouteDefinition stores HTTP method', function () {
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );

    expect($route->method)->toBe('GET');
});

it('RouteDefinition stores path pattern', function () {
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts/{id}',
        controller: 'PostController',
        action: 'show',
    );

    expect($route->path)->toBe('/posts/{id}');
});

it('RouteDefinition stores controller class name', function () {
    $route = new RouteDefinition(
        method: 'POST',
        path: '/posts',
        controller: 'App\\Http\\Controllers\\PostController',
        action: 'store',
    );

    expect($route->controller)->toBe('App\\Http\\Controllers\\PostController');
});

it('RouteDefinition stores method name', function () {
    $route = new RouteDefinition(
        method: 'PUT',
        path: '/posts/{id}',
        controller: 'PostController',
        action: 'update',
    );

    expect($route->action)->toBe('update');
});

it('RouteDefinition stores middleware array', function () {
    $route = new RouteDefinition(
        method: 'DELETE',
        path: '/posts/{id}',
        controller: 'PostController',
        action: 'destroy',
        middleware: ['AuthMiddleware', 'LoggingMiddleware'],
    );

    expect($route->middleware)->toBe(['AuthMiddleware', 'LoggingMiddleware']);
});

it('RouteDefinition extracts parameter names from path', function () {
    $route = new RouteDefinition(
        method: 'GET',
        path: '/users/{userId}/posts/{postId}',
        controller: 'PostController',
        action: 'show',
    );

    expect($route->parameters)->toBe(['userId', 'postId']);
});

it('RouteDefinition with path /posts/{id} has parameter id', function () {
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts/{id}',
        controller: 'PostController',
        action: 'show',
    );

    expect($route->parameters)->toBe(['id']);
});

it('RouteDefinition with path /posts/{id}/comments/{commentId} has parameters id and commentId', function () {
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts/{id}/comments/{commentId}',
        controller: 'CommentController',
        action: 'show',
    );

    expect($route->parameters)->toBe(['id', 'commentId']);
});

it('RouteDefinition with no parameters has empty parameter array', function () {
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );

    expect($route->parameters)->toBe([]);
});

it('RouteDefinition generates regex pattern for matching', function () {
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts/{id}',
        controller: 'PostController',
        action: 'show',
    );

    expect($route->regex)->toBe('#^/posts/(?P<id>[^/]+)$#')
        ->and(preg_match($route->regex, '/posts/123'))->toBe(1)
        ->and(preg_match($route->regex, '/posts/'))->toBe(0)
        ->and(preg_match($route->regex, '/users/123'))->toBe(0);
});

it('RouteDefinition is readonly', function () {
    $reflection = new ReflectionClass(RouteDefinition::class);

    expect($reflection->isReadOnly())->toBeTrue();
});
