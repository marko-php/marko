<?php

declare(strict_types=1);

use Marko\Blog\Controllers\PostController;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

it('has GET /blog route on index method', function (): void {
    $controller = new PostController();
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('index');
    $attributes = $method->getAttributes(Get::class);

    expect($attributes)->toHaveCount(1);

    $routeAttribute = $attributes[0]->newInstance();
    expect($routeAttribute->path)->toBe('/blog');
});

it('has GET /blog/{slug} route on show method', function (): void {
    $controller = new PostController();
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('show');
    $attributes = $method->getAttributes(Get::class);

    expect($attributes)->toHaveCount(1);

    $routeAttribute = $attributes[0]->newInstance();
    expect($routeAttribute->path)->toBe('/blog/{slug}');
});

it('index returns response confirming route matched', function (): void {
    $controller = new PostController();
    $response = $controller->index();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->body())->toContain('Blog Index')
        ->and($response->statusCode())->toBe(200);
});

it('show returns response including the slug parameter', function (): void {
    $controller = new PostController();
    $response = $controller->show('hello-world');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->body())->toContain('hello-world')
        ->and($response->statusCode())->toBe(200);
});
