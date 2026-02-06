<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Unit\Admin\Api;

use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\Blog\Admin\Api\AuthorApiController;
use Marko\Blog\Admin\Api\CategoryApiController;
use Marko\Blog\Admin\Api\CommentApiController;
use Marko\Blog\Admin\Api\PostApiController;
use Marko\Blog\Admin\Api\TagApiController;
use ReflectionClass;

it('requires appropriate blog permissions on each endpoint', function (): void {
    // PostApiController permissions
    $postRef = new ReflectionClass(PostApiController::class);

    $postPerms = [
        'index' => 'blog.posts.view',
        'show' => 'blog.posts.view',
        'store' => 'blog.posts.create',
        'update' => 'blog.posts.edit',
        'destroy' => 'blog.posts.delete',
        'publish' => 'blog.posts.publish',
    ];

    foreach ($postPerms as $method => $expectedPerm) {
        $attrs = $postRef->getMethod($method)->getAttributes(RequiresPermission::class);
        expect($attrs)->toHaveCount(1, "PostApiController::$method should have RequiresPermission");
        expect($attrs[0]->newInstance()->permission)->toBe($expectedPerm);
    }

    // AuthorApiController permissions
    $authorRef = new ReflectionClass(AuthorApiController::class);

    $authorPerms = [
        'index' => 'blog.authors.view',
        'show' => 'blog.authors.view',
        'store' => 'blog.authors.create',
        'update' => 'blog.authors.edit',
        'destroy' => 'blog.authors.delete',
    ];

    foreach ($authorPerms as $method => $expectedPerm) {
        $attrs = $authorRef->getMethod($method)->getAttributes(RequiresPermission::class);
        expect($attrs)->toHaveCount(1, "AuthorApiController::$method should have RequiresPermission");
        expect($attrs[0]->newInstance()->permission)->toBe($expectedPerm);
    }

    // CategoryApiController permissions
    $categoryRef = new ReflectionClass(CategoryApiController::class);

    $categoryPerms = [
        'index' => 'blog.categories.view',
        'show' => 'blog.categories.view',
        'store' => 'blog.categories.create',
        'update' => 'blog.categories.edit',
        'destroy' => 'blog.categories.delete',
    ];

    foreach ($categoryPerms as $method => $expectedPerm) {
        $attrs = $categoryRef->getMethod($method)->getAttributes(RequiresPermission::class);
        expect($attrs)->toHaveCount(1, "CategoryApiController::$method should have RequiresPermission");
        expect($attrs[0]->newInstance()->permission)->toBe($expectedPerm);
    }

    // TagApiController permissions
    $tagRef = new ReflectionClass(TagApiController::class);

    $tagPerms = [
        'index' => 'blog.tags.view',
        'show' => 'blog.tags.view',
        'store' => 'blog.tags.create',
        'update' => 'blog.tags.edit',
        'destroy' => 'blog.tags.delete',
    ];

    foreach ($tagPerms as $method => $expectedPerm) {
        $attrs = $tagRef->getMethod($method)->getAttributes(RequiresPermission::class);
        expect($attrs)->toHaveCount(1, "TagApiController::$method should have RequiresPermission");
        expect($attrs[0]->newInstance()->permission)->toBe($expectedPerm);
    }

    // CommentApiController permissions
    $commentRef = new ReflectionClass(CommentApiController::class);

    $commentPerms = [
        'index' => 'blog.comments.view',
        'show' => 'blog.comments.view',
        'verify' => 'blog.comments.edit',
        'destroy' => 'blog.comments.delete',
    ];

    foreach ($commentPerms as $method => $expectedPerm) {
        $attrs = $commentRef->getMethod($method)->getAttributes(RequiresPermission::class);
        expect($attrs)->toHaveCount(1, "CommentApiController::$method should have RequiresPermission");
        expect($attrs[0]->newInstance()->permission)->toBe($expectedPerm);
    }
});
