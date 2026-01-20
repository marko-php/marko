<?php

declare(strict_types=1);

use Marko\Routing\Attributes\Middleware;

it('accepts single middleware class', function () {
    $attribute = new Middleware(AuthMiddleware::class);

    expect($attribute->middleware)->toBe([AuthMiddleware::class]);
});

it('accepts array of middleware classes', function () {
    $attribute = new Middleware([AuthMiddleware::class, LoggingMiddleware::class]);

    expect($attribute->middleware)->toBe([AuthMiddleware::class, LoggingMiddleware::class]);
});

it('can target both classes and methods', function () {
    $reflection = new ReflectionClass(Middleware::class);
    $attributes = $reflection->getAttributes(Attribute::class);

    expect($attributes)->toHaveCount(1);

    $attribute = $attributes[0]->newInstance();
    $flags = $attribute->flags;

    expect($flags & Attribute::TARGET_CLASS)->toBe(Attribute::TARGET_CLASS)
        ->and($flags & Attribute::TARGET_METHOD)->toBe(Attribute::TARGET_METHOD);
});

// Dummy middleware classes for testing
class AuthMiddleware {}
class LoggingMiddleware {}
