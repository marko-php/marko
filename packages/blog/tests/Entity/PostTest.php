<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Entity;

use DateTimeImmutable;
use InvalidArgumentException;
use Marko\Blog\Entity\Author;
use Marko\Blog\Entity\AuthorInterface;
use Marko\Blog\Entity\Post;
use Marko\Blog\Entity\PostInterface;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Services\SlugGenerator;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use ReflectionClass;

it('extends the Entity base class', function (): void {
    $post = new Post();

    expect($post)->toBeInstanceOf(Entity::class);
});

it('has Table attribute with posts table name', function (): void {
    $reflection = new ReflectionClass(Post::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $tableAttribute = $attributes[0]->newInstance();
    expect($tableAttribute->name)->toBe('posts');
});

it('has id property with primaryKey and autoIncrement Column attributes', function (): void {
    $reflection = new ReflectionClass(Post::class);

    expect($reflection->hasProperty('id'))->toBeTrue();

    $property = $reflection->getProperty('id');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->primaryKey)->toBeTrue()
        ->and($columnAttribute->autoIncrement)->toBeTrue();
});

it('has title property with Column attribute', function (): void {
    $reflection = new ReflectionClass(Post::class);

    expect($reflection->hasProperty('title'))->toBeTrue();

    $property = $reflection->getProperty('title');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1)
        ->and($property->getType()->getName())->toBe('string');
});

it('has slug property with Column attribute and unique constraint', function (): void {
    $reflection = new ReflectionClass(Post::class);

    expect($reflection->hasProperty('slug'))->toBeTrue();

    $property = $reflection->getProperty('slug');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->unique)->toBeTrue()
        ->and($property->getType()->getName())->toBe('string');
});

it('has content property with Column attribute for TEXT type', function (): void {
    $reflection = new ReflectionClass(Post::class);

    expect($reflection->hasProperty('content'))->toBeTrue();

    $property = $reflection->getProperty('content');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->type)->toBe('TEXT')
        ->and($property->getType()->getName())->toBe('string');
});

it('has createdAt property with Column attribute mapping to created_at', function (): void {
    $reflection = new ReflectionClass(Post::class);

    expect($reflection->hasProperty('createdAt'))->toBeTrue();

    $property = $reflection->getProperty('createdAt');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('created_at');
});

it('has updatedAt property with Column attribute mapping to updated_at', function (): void {
    $reflection = new ReflectionClass(Post::class);

    expect($reflection->hasProperty('updatedAt'))->toBeTrue();

    $property = $reflection->getProperty('updatedAt');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('updated_at');
});

it('uses nullable types for optional fields appropriately', function (): void {
    $reflection = new ReflectionClass(Post::class);

    // id is nullable (null before insert, set after)
    $idProperty = $reflection->getProperty('id');
    expect($idProperty->getType()->allowsNull())->toBeTrue();

    // createdAt is nullable (set by database/application on insert)
    $createdAtProperty = $reflection->getProperty('createdAt');
    expect($createdAtProperty->getType()->allowsNull())->toBeTrue();

    // updatedAt is nullable (set by database/application on update)
    $updatedAtProperty = $reflection->getProperty('updatedAt');
    expect($updatedAtProperty->getType()->allowsNull())->toBeTrue();

    // Required fields are NOT nullable
    $titleProperty = $reflection->getProperty('title');
    $slugProperty = $reflection->getProperty('slug');
    $contentProperty = $reflection->getProperty('content');

    expect($titleProperty->getType()->allowsNull())->toBeFalse()
        ->and($slugProperty->getType()->allowsNull())->toBeFalse()
        ->and($contentProperty->getType()->allowsNull())->toBeFalse();
});

it('implements PostInterface', function (): void {
    $post = new Post();

    expect($post)->toBeInstanceOf(PostInterface::class);
});

it('has status field defaulting to draft', function (): void {
    $reflection = new ReflectionClass(Post::class);

    expect($reflection->hasProperty('status'))->toBeTrue();

    $property = $reflection->getProperty('status');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    // Test the default value
    $slugGenerator = new SlugGenerator();
    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slugGenerator: $slugGenerator,
    );

    expect($post->getStatus())->toBe(PostStatus::Draft);
});

it('has scheduled_at nullable datetime field', function (): void {
    $reflection = new ReflectionClass(Post::class);

    expect($reflection->hasProperty('scheduledAt'))->toBeTrue();

    $property = $reflection->getProperty('scheduledAt');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('scheduled_at')
        ->and($property->getType()->allowsNull())->toBeTrue();
});

it('has published_at nullable datetime field', function (): void {
    $reflection = new ReflectionClass(Post::class);

    expect($reflection->hasProperty('publishedAt'))->toBeTrue();

    $property = $reflection->getProperty('publishedAt');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('published_at')
        ->and($property->getType()->allowsNull())->toBeTrue();
});

it('has created_at timestamp', function (): void {
    $reflection = new ReflectionClass(Post::class);

    expect($reflection->hasProperty('createdAt'))->toBeTrue();

    $property = $reflection->getProperty('createdAt');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('created_at');

    // Test the getter returns DateTimeImmutable
    $slugGenerator = new SlugGenerator();
    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slugGenerator: $slugGenerator,
    );
    $post->createdAt = '2024-01-15 10:30:00';

    expect($post->getCreatedAt())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($post->getCreatedAt()->format('Y-m-d H:i:s'))->toBe('2024-01-15 10:30:00');
});

it('has updated_at timestamp', function (): void {
    $reflection = new ReflectionClass(Post::class);

    expect($reflection->hasProperty('updatedAt'))->toBeTrue();

    $property = $reflection->getProperty('updatedAt');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('updated_at');

    // Test the getter returns DateTimeImmutable
    $slugGenerator = new SlugGenerator();
    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slugGenerator: $slugGenerator,
    );
    $post->updatedAt = '2024-01-16 14:00:00';

    expect($post->getUpdatedAt())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($post->getUpdatedAt()->format('Y-m-d H:i:s'))->toBe('2024-01-16 14:00:00');
});

it('has summary text field', function (): void {
    $reflection = new ReflectionClass(Post::class);

    expect($reflection->hasProperty('summary'))->toBeTrue();

    $property = $reflection->getProperty('summary');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->type)->toBe('TEXT')
        ->and($property->getType()->allowsNull())->toBeTrue();

    // Test getter
    $slugGenerator = new SlugGenerator();
    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slugGenerator: $slugGenerator,
        summary: 'A brief summary',
    );

    expect($post->getSummary())->toBe('A brief summary');
});

it('has author_id foreign key to authors table', function (): void {
    $reflection = new ReflectionClass(Post::class);

    expect($reflection->hasProperty('authorId'))->toBeTrue();

    $property = $reflection->getProperty('authorId');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('author_id')
        ->and($property->getType()->getName())->toBe('int');

    // Test getter
    $slugGenerator = new SlugGenerator();
    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 42,
        slugGenerator: $slugGenerator,
    );

    expect($post->getAuthorId())->toBe(42);
});

it('sets published_at when status changes to published', function (): void {
    $slugGenerator = new SlugGenerator();
    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slugGenerator: $slugGenerator,
    );

    expect($post->getStatus())->toBe(PostStatus::Draft)
        ->and($post->getPublishedAt())->toBeNull();

    $post->setStatus(PostStatus::Published);

    expect($post->getStatus())->toBe(PostStatus::Published)
        ->and($post->getPublishedAt())->toBeInstanceOf(DateTimeImmutable::class);
});

it('auto-generates slug from title using SlugGenerator', function (): void {
    $slugGenerator = new SlugGenerator();
    $post = new Post(
        title: 'My Amazing Blog Post!',
        content: 'Test content',
        authorId: 1,
        slugGenerator: $slugGenerator,
    );

    expect($post->getSlug())->toBe('my-amazing-blog-post');
});

it('allows manual slug override', function (): void {
    $slugGenerator = new SlugGenerator();
    $post = new Post(
        title: 'My Amazing Blog Post!',
        content: 'Test content',
        authorId: 1,
        slugGenerator: $slugGenerator,
        slug: 'custom-slug-here',
    );

    expect($post->getSlug())->toBe('custom-slug-here');
});

it('ensures slug uniqueness within posts table', function (): void {
    $existingSlugs = ['test-post', 'test-post-1', 'test-post-2'];

    $slugGenerator = new SlugGenerator();
    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slugGenerator: $slugGenerator,
        uniquenessChecker: fn (string $slug): bool => !in_array($slug, $existingSlugs, true),
    );

    expect($post->getSlug())->toBe('test-post-3');
});

it('validates scheduled_at is set when status is scheduled', function (): void {
    $slugGenerator = new SlugGenerator();
    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slugGenerator: $slugGenerator,
    );

    expect(fn () => $post->setStatus(PostStatus::Scheduled))
        ->toThrow(InvalidArgumentException::class, 'Scheduled posts must have a scheduled_at date');
});

it('allows scheduled status when scheduled_at is set', function (): void {
    $slugGenerator = new SlugGenerator();
    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slugGenerator: $slugGenerator,
    );

    $futureDate = new DateTimeImmutable('+1 day');
    $post->setScheduledAt($futureDate);
    $post->setStatus(PostStatus::Scheduled);

    // Compare formatted strings since the datetime is stored/retrieved as string
    expect($post->getStatus())->toBe(PostStatus::Scheduled)
        ->and($post->getScheduledAt()->format('Y-m-d H:i:s'))
        ->toBe($futureDate->format('Y-m-d H:i:s'));
});

it('returns associated author entity', function (): void {
    $author = new Author();
    $author->id = 1;
    $author->name = 'John Doe';
    $author->email = 'john@example.com';
    $author->slug = 'john-doe';

    $slugGenerator = new SlugGenerator();
    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slugGenerator: $slugGenerator,
    );

    $post->setAuthor($author);

    expect($post->getAuthor())->toBe($author)
        ->and($post->getAuthor())->toBeInstanceOf(AuthorInterface::class);
});

it('determines if post was modified after publishing via wasUpdatedAfterPublishing method', function (): void {
    $slugGenerator = new SlugGenerator();
    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slugGenerator: $slugGenerator,
    );

    // Not published yet
    expect($post->wasUpdatedAfterPublishing())->toBeFalse();

    // Publish the post
    $post->setStatus(PostStatus::Published);
    $post->publishedAt = '2024-01-15 10:00:00';
    $post->updatedAt = '2024-01-15 10:00:00';

    expect($post->wasUpdatedAfterPublishing())->toBeFalse();

    // Update the post after publishing
    $post->updatedAt = '2024-01-16 12:00:00';

    expect($post->wasUpdatedAfterPublishing())->toBeTrue();
});

it('checks isPublished method', function (): void {
    $slugGenerator = new SlugGenerator();
    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slugGenerator: $slugGenerator,
    );

    expect($post->isPublished())->toBeFalse();

    $post->setStatus(PostStatus::Published);

    expect($post->isPublished())->toBeTrue();
});

it('checks isDraft method', function (): void {
    $slugGenerator = new SlugGenerator();
    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slugGenerator: $slugGenerator,
    );

    expect($post->isDraft())->toBeTrue();

    $post->setStatus(PostStatus::Published);

    expect($post->isDraft())->toBeFalse();
});

it('checks isScheduled method', function (): void {
    $slugGenerator = new SlugGenerator();
    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slugGenerator: $slugGenerator,
    );

    expect($post->isScheduled())->toBeFalse();

    $post->setScheduledAt(new DateTimeImmutable('+1 day'));
    $post->setStatus(PostStatus::Scheduled);

    expect($post->isScheduled())->toBeTrue();
});
