<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Repositories;

use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Database\Repository\RepositoryInterface;
use ReflectionClass;

it('creates PostRepositoryInterface with all repository methods', function (): void {
    $reflection = new ReflectionClass(PostRepositoryInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->implementsInterface(RepositoryInterface::class))->toBeTrue();

    // Check methods unique to PostRepositoryInterface (not inherited)
    $expectedMethods = [
        'findBySlug',
        'findPublished',
        'findByStatus',
        'findByAuthor',
        'findScheduledPostsDue',
        'countByAuthor',
        'isSlugUnique',
    ];

    foreach ($expectedMethods as $method) {
        expect($reflection->hasMethod($method))->toBeTrue(
            "PostRepositoryInterface should have method: $method",
        );

        $methodReflection = $reflection->getMethod($method);
        expect($methodReflection->isPublic())->toBeTrue();
    }

    // Verify inherited methods from RepositoryInterface are available
    $inheritedMethods = ['find', 'findAll', 'findBy', 'findOneBy', 'save', 'delete'];
    foreach ($inheritedMethods as $method) {
        expect($reflection->hasMethod($method))->toBeTrue(
            "PostRepositoryInterface should inherit method: $method",
        );
    }
});

it('findBySlug method signature requires string and returns nullable Post', function (): void {
    $reflection = new ReflectionClass(PostRepositoryInterface::class);
    $method = $reflection->getMethod('findBySlug');

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('slug')
        ->and($parameters[0]->getType()->getName())->toBe('string');

    $returnType = $method->getReturnType();
    expect($returnType->allowsNull())->toBeTrue()
        ->and($returnType->getName())->toBe(Post::class);
});

it('findByStatus method signature requires PostStatus and returns array', function (): void {
    $reflection = new ReflectionClass(PostRepositoryInterface::class);
    $method = $reflection->getMethod('findByStatus');

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('status')
        ->and($parameters[0]->getType()->getName())->toBe(PostStatus::class);

    $returnType = $method->getReturnType();
    expect($returnType->getName())->toBe('array');
});

it('findByAuthor method signature requires int authorId and returns array', function (): void {
    $reflection = new ReflectionClass(PostRepositoryInterface::class);
    $method = $reflection->getMethod('findByAuthor');

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('authorId')
        ->and($parameters[0]->getType()->getName())->toBe('int');

    $returnType = $method->getReturnType();
    expect($returnType->getName())->toBe('array');
});

it('isSlugUnique method signature requires slug and optional excludeId', function (): void {
    $reflection = new ReflectionClass(PostRepositoryInterface::class);
    $method = $reflection->getMethod('isSlugUnique');

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('slug')
        ->and($parameters[0]->getType()->getName())->toBe('string')
        ->and($parameters[1]->getName())->toBe('excludeId')
        ->and($parameters[1]->getType()->allowsNull())->toBeTrue()
        ->and($parameters[1]->isDefaultValueAvailable())->toBeTrue();

    $returnType = $method->getReturnType();
    expect($returnType->getName())->toBe('bool');
});
