<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Repositories;

use Marko\Blog\Repositories\CommentRepositoryInterface;
use ReflectionClass;

it('does not define getThreadedCommentsForPost method', function (): void {
    $reflection = new ReflectionClass(CommentRepositoryInterface::class);

    expect($reflection->hasMethod('getThreadedCommentsForPost'))->toBeFalse();
});

it('does not define calculateDepth method', function (): void {
    $reflection = new ReflectionClass(CommentRepositoryInterface::class);

    expect($reflection->hasMethod('calculateDepth'))->toBeFalse();
});
