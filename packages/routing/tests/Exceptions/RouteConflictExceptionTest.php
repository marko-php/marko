<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Routing\Exceptions\RouteConflictException;

it('creates RouteConflictException extending MarkoException', function () {
    $exception = new RouteConflictException(
        message: 'Test message',
        context: 'Test context',
        suggestion: 'Test suggestion',
    );

    expect($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toBe('Test message')
        ->and($exception->getContext())->toBe('Test context')
        ->and($exception->getSuggestion())->toBe('Test suggestion');
});

it('RouteConflictException::duplicateRoute shows both conflicting routes with paths and controllers', function () {
    $exception = RouteConflictException::duplicateRoute(
        path: '/posts/{id}',
        method: 'GET',
        existingController: 'Vendor\Blog\Controller\PostController',
        existingMethod: 'show',
        newController: 'App\Blog\Controller\PostController',
        newMethod: 'show',
    );

    expect($exception)->toBeInstanceOf(RouteConflictException::class)
        ->and($exception->getMessage())->toContain('Duplicate route')
        ->and($exception->getMessage())->toContain('GET')
        ->and($exception->getMessage())->toContain('/posts/{id}')
        ->and($exception->getContext())->toContain('Vendor\Blog\Controller\PostController')
        ->and($exception->getContext())->toContain('App\Blog\Controller\PostController');
});

it('RouteConflictException includes suggestion to use Preference or change path', function () {
    $exception = RouteConflictException::duplicateRoute(
        path: '/posts/{id}',
        method: 'GET',
        existingController: 'Vendor\Blog\Controller\PostController',
        existingMethod: 'show',
        newController: 'App\Blog\Controller\PostController',
        newMethod: 'show',
    );

    expect($exception->getSuggestion())->toContain('Preference')
        ->and($exception->getSuggestion())->toContain('path');
});
