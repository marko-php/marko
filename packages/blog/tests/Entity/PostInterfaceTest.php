<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Entity;

use DateTimeImmutable;
use Marko\Blog\Entity\AuthorInterface;
use Marko\Blog\Entity\PostInterface;
use Marko\Blog\Enum\PostStatus;
use ReflectionClass;

it('creates PostInterface with all post property accessors', function (): void {
    $reflection = new ReflectionClass(PostInterface::class);

    expect($reflection->isInterface())->toBeTrue();

    // Check all required methods exist
    $expectedMethods = [
        'getId' => '?int',
        'getTitle' => 'string',
        'getSlug' => 'string',
        'getContent' => 'string',
        'getSummary' => '?string',
        'getStatus' => PostStatus::class,
        'getAuthorId' => 'int',
        'getAuthor' => AuthorInterface::class,
        'getScheduledAt' => '?' . DateTimeImmutable::class,
        'getPublishedAt' => '?' . DateTimeImmutable::class,
        'getCreatedAt' => '?' . DateTimeImmutable::class,
        'getUpdatedAt' => '?' . DateTimeImmutable::class,
        'wasUpdatedAfterPublishing' => 'bool',
        'isPublished' => 'bool',
        'isDraft' => 'bool',
        'isScheduled' => 'bool',
    ];

    foreach ($expectedMethods as $method => $returnType) {
        expect($reflection->hasMethod($method))->toBeTrue(
            "PostInterface should have method: $method",
        );

        $methodReflection = $reflection->getMethod($method);
        expect($methodReflection->isPublic())->toBeTrue();
    }
});
