<?php

declare(strict_types=1);

use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Patch;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Attributes\Put;

it('Get attribute accepts path parameter', function () {
    $get = new Get('/posts');

    expect($get->path)->toBe('/posts');
});

it('Get attribute accepts optional middleware array parameter', function () {
    $get = new Get('/posts', ['AuthMiddleware', 'LoggingMiddleware']);

    expect($get->middleware)->toBe(['AuthMiddleware', 'LoggingMiddleware']);
});

it('Get attribute defaults middleware to empty array', function () {
    $get = new Get('/posts');

    expect($get->middleware)->toBe([]);
});

it('Post attribute accepts path and optional middleware', function () {
    $post = new Post('/posts');
    $postWithMiddleware = new Post('/posts', ['AuthMiddleware']);

    expect($post->path)->toBe('/posts')
        ->and($post->middleware)->toBe([])
        ->and($postWithMiddleware->middleware)->toBe(['AuthMiddleware']);
});

it('Put attribute accepts path and optional middleware', function () {
    $put = new Put('/posts/{id}');
    $putWithMiddleware = new Put('/posts/{id}', ['AuthMiddleware']);

    expect($put->path)->toBe('/posts/{id}')
        ->and($put->middleware)->toBe([])
        ->and($putWithMiddleware->middleware)->toBe(['AuthMiddleware']);
});

it('Patch attribute accepts path and optional middleware', function () {
    $patch = new Patch('/posts/{id}');
    $patchWithMiddleware = new Patch('/posts/{id}', ['AuthMiddleware']);

    expect($patch->path)->toBe('/posts/{id}')
        ->and($patch->middleware)->toBe([])
        ->and($patchWithMiddleware->middleware)->toBe(['AuthMiddleware']);
});

it('Delete attribute accepts path and optional middleware', function () {
    $delete = new Delete('/posts/{id}');
    $deleteWithMiddleware = new Delete('/posts/{id}', ['AuthMiddleware']);

    expect($delete->path)->toBe('/posts/{id}')
        ->and($delete->middleware)->toBe([])
        ->and($deleteWithMiddleware->middleware)->toBe(['AuthMiddleware']);
});

it('all route attributes target methods only', function () {
    $routeAttributes = [Get::class, Post::class, Put::class, Patch::class, Delete::class];

    foreach ($routeAttributes as $attributeClass) {
        $reflection = new ReflectionClass($attributeClass);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->newInstance()->flags)->toBe(Attribute::TARGET_METHOD);
    }
});

it('route attributes are readonly', function () {
    $routeAttributes = [Get::class, Post::class, Put::class, Patch::class, Delete::class];

    foreach ($routeAttributes as $attributeClass) {
        $reflection = new ReflectionClass($attributeClass);

        expect($reflection->isReadOnly())->toBeTrue();
    }
});

it('route attributes expose method property matching HTTP method', function () {
    expect((new Get('/posts'))->getMethod())->toBe('GET')
        ->and((new Post('/posts'))->getMethod())->toBe('POST')
        ->and((new Put('/posts/{id}'))->getMethod())->toBe('PUT')
        ->and((new Patch('/posts/{id}'))->getMethod())->toBe('PATCH')
        ->and((new Delete('/posts/{id}'))->getMethod())->toBe('DELETE');
});
