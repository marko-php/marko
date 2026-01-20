<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Routing\Exceptions\RouteException;

it('creates RouteException extending MarkoException', function () {
    $exception = new RouteException(
        message: 'Test message',
        context: 'Test context',
        suggestion: 'Test suggestion',
    );

    expect($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toBe('Test message')
        ->and($exception->getContext())->toBe('Test context')
        ->and($exception->getSuggestion())->toBe('Test suggestion');
});

it('RouteException::ambiguousOverride provides helpful message for method override without attribute', function () {
    $exception = RouteException::ambiguousOverride(
        parentClass: 'Vendor\Blog\Controller\PostController',
        childClass: 'App\Blog\Controller\PostController',
        method: 'show',
    );

    expect($exception)->toBeInstanceOf(RouteException::class)
        ->and($exception->getMessage())->toContain('show')
        ->and($exception->getMessage())->toContain('overrides')
        ->and($exception->getContext())->toContain('Vendor\Blog\Controller\PostController')
        ->and($exception->getContext())->toContain('App\Blog\Controller\PostController')
        ->and($exception->getSuggestion())->toContain('#[Route]');
});

it('RouteException::invalidParameter provides helpful message for malformed route parameters', function () {
    $exception = RouteException::invalidParameter(
        path: '/posts/{id',
        parameter: '{id',
        reason: 'Missing closing brace',
    );

    expect($exception)->toBeInstanceOf(RouteException::class)
        ->and($exception->getMessage())->toContain('Invalid route parameter')
        ->and($exception->getMessage())->toContain('{id')
        ->and($exception->getContext())->toContain('/posts/{id')
        ->and($exception->getContext())->toContain('Missing closing brace')
        ->and($exception->getSuggestion())->toContain('{id}');
});

it('RouteException::controllerNotFound provides helpful message when controller class missing', function () {
    $exception = RouteException::controllerNotFound(
        controller: 'App\Blog\Controller\PostController',
        path: '/posts/{id}',
    );

    expect($exception)->toBeInstanceOf(RouteException::class)
        ->and($exception->getMessage())->toContain('Controller class not found')
        ->and($exception->getMessage())->toContain('App\Blog\Controller\PostController')
        ->and($exception->getContext())->toContain('/posts/{id}')
        ->and($exception->getSuggestion())->toContain('class exists')
        ->and($exception->getSuggestion())->toContain('autoload');
});

it('RouteException::methodNotFound provides helpful message when method missing', function () {
    $exception = RouteException::methodNotFound(
        controller: 'App\Blog\Controller\PostController',
        method: 'show',
        path: '/posts/{id}',
    );

    expect($exception)->toBeInstanceOf(RouteException::class)
        ->and($exception->getMessage())->toContain('Method not found')
        ->and($exception->getMessage())->toContain('show')
        ->and($exception->getContext())->toContain('App\Blog\Controller\PostController')
        ->and($exception->getContext())->toContain('/posts/{id}')
        ->and($exception->getSuggestion())->toContain('method exists')
        ->and($exception->getSuggestion())->toContain('public');
});
