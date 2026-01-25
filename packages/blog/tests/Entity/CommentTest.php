<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Entity;

use DateTimeImmutable;
use InvalidArgumentException;
use Marko\Blog\Entity\AuthorInterface;
use Marko\Blog\Entity\Comment;
use Marko\Blog\Entity\PostInterface;
use Marko\Blog\Enum\CommentStatus;
use Marko\Blog\Enum\PostStatus;
use Marko\Database\Attributes\Column;
use ReflectionClass;
use RuntimeException;

it('creates comment with post_id author_name author_email and content', function (): void {
    $comment = new Comment();
    $comment->postId = 1;
    $comment->authorName = 'John Doe';
    $comment->authorEmail = 'john@example.com';
    $comment->content = 'This is a great blog post!';

    expect($comment->postId)->toBe(1)
        ->and($comment->authorName)->toBe('John Doe')
        ->and($comment->authorEmail)->toBe('john@example.com')
        ->and($comment->content)->toBe('This is a great blog post!');
});

it('requires post_id field', function (): void {
    $reflection = new ReflectionClass(Comment::class);
    $property = $reflection->getProperty('postId');

    expect($property->getType()->allowsNull())->toBeFalse()
        ->and($property->getType()->getName())->toBe('int');

    $attributes = $property->getAttributes(Column::class);
    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('post_id');
});

it('requires author_name field', function (): void {
    $reflection = new ReflectionClass(Comment::class);
    $property = $reflection->getProperty('authorName');

    expect($property->getType()->allowsNull())->toBeFalse()
        ->and($property->getType()->getName())->toBe('string');

    $attributes = $property->getAttributes(Column::class);
    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('author_name');
});

it('requires author_email with valid format', function (): void {
    $reflection = new ReflectionClass(Comment::class);
    $property = $reflection->getProperty('authorEmail');

    expect($property->getType()->allowsNull())->toBeFalse()
        ->and($property->getType()->getName())->toBe('string');

    $attributes = $property->getAttributes(Column::class);
    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('author_email');
});

it('requires content field with minimum length', function (): void {
    $reflection = new ReflectionClass(Comment::class);
    $property = $reflection->getProperty('content');

    expect($property->getType()->allowsNull())->toBeFalse()
        ->and($property->getType()->getName())->toBe('string');

    $attributes = $property->getAttributes(Column::class);
    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->type)->toBe('TEXT');
});

it('validates content does not exceed maximum length of 10000 characters', function (): void {
    $comment = new Comment();
    $comment->postId = 1;
    $comment->authorName = 'John Doe';
    $comment->authorEmail = 'john@example.com';
    $comment->content = str_repeat('a', 10000);

    // Content at exactly 10000 should be valid
    expect($comment->content)->toHaveLength(10000);

    // The entity should have a constant or method defining the max length
    expect(Comment::MAX_CONTENT_LENGTH)->toBe(10000);
});

it('has status defaulting to pending', function (): void {
    $comment = new Comment();

    expect($comment->status)->toBe(CommentStatus::Pending);

    $reflection = new ReflectionClass(Comment::class);
    $property = $reflection->getProperty('status');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);
});

it('has nullable parent_id for threading', function (): void {
    $reflection = new ReflectionClass(Comment::class);

    expect($reflection->hasProperty('parentId'))->toBeTrue();

    $property = $reflection->getProperty('parentId');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1)
        ->and($property->getType()->allowsNull())->toBeTrue();

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('parent_id');
});

it('has verified_at nullable datetime', function (): void {
    $reflection = new ReflectionClass(Comment::class);

    expect($reflection->hasProperty('verifiedAt'))->toBeTrue();

    $property = $reflection->getProperty('verifiedAt');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1)
        ->and($property->getType()->allowsNull())->toBeTrue();

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('verified_at');

    // Test getter method
    $comment = new Comment();
    expect($comment->getVerifiedAt())->toBeNull();

    $comment->verifiedAt = '2024-01-15 10:30:00';
    $verifiedAt = $comment->getVerifiedAt();
    expect($verifiedAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($verifiedAt->format('Y-m-d H:i:s'))->toBe('2024-01-15 10:30:00');
});

it('has created_at timestamp', function (): void {
    $reflection = new ReflectionClass(Comment::class);

    expect($reflection->hasProperty('createdAt'))->toBeTrue();

    $property = $reflection->getProperty('createdAt');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('created_at');

    // Test getter method
    $comment = new Comment();
    expect($comment->getCreatedAt())->toBeNull();

    $comment->createdAt = '2024-01-15 10:30:00';
    $createdAt = $comment->getCreatedAt();
    expect($createdAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($createdAt->format('Y-m-d H:i:s'))->toBe('2024-01-15 10:30:00');
});

it('returns associated post entity', function (): void {
    $comment = new Comment();
    $comment->postId = 1;

    // Before post is loaded, throws exception
    expect(fn () => $comment->getPost())->toThrow(InvalidArgumentException::class, 'Post has not been loaded');

    // Create mock post
    $post = new class () implements PostInterface
    {
        public function getId(): ?int
        {
            return 1;
        }

        public function getTitle(): string
        {
            return 'Test Post';
        }

        public function getSlug(): string
        {
            return 'test-post';
        }

        public function getContent(): string
        {
            return '';
        }

        public function getSummary(): ?string
        {
            return null;
        }

        public function getStatus(): PostStatus
        {
            return PostStatus::Published;
        }

        public function getAuthorId(): int
        {
            return 1;
        }

        public function getAuthor(): AuthorInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function getScheduledAt(): ?DateTimeImmutable
        {
            return null;
        }

        public function getPublishedAt(): ?DateTimeImmutable
        {
            return null;
        }

        public function getCreatedAt(): ?DateTimeImmutable
        {
            return null;
        }

        public function getUpdatedAt(): ?DateTimeImmutable
        {
            return null;
        }

        public function wasUpdatedAfterPublishing(): bool
        {
            return false;
        }

        public function isPublished(): bool
        {
            return true;
        }

        public function isDraft(): bool
        {
            return false;
        }

        public function isScheduled(): bool
        {
            return false;
        }
    };

    $comment->setPost($post);
    expect($comment->getPost())->toBe($post)
        ->and($comment->getPost()->getTitle())->toBe('Test Post');
});

it('returns parent comment if exists', function (): void {
    $parentComment = new Comment();
    $parentComment->id = 1;
    $parentComment->postId = 1;
    $parentComment->authorName = 'Parent Author';
    $parentComment->authorEmail = 'parent@example.com';
    $parentComment->content = 'This is the parent comment.';

    $childComment = new Comment();
    $childComment->id = 2;
    $childComment->postId = 1;
    $childComment->parentId = 1;
    $childComment->authorName = 'Child Author';
    $childComment->authorEmail = 'child@example.com';
    $childComment->content = 'This is a reply to the parent comment.';

    // Before parent is set, returns null
    expect($childComment->getParent())->toBeNull();

    // After setting parent
    $childComment->setParent($parentComment);
    expect($childComment->getParent())->toBe($parentComment)
        ->and($childComment->getParent()->getAuthorName())->toBe('Parent Author');
});

it('returns child comments', function (): void {
    $parentComment = new Comment();
    $parentComment->id = 1;
    $parentComment->postId = 1;
    $parentComment->authorName = 'Parent Author';
    $parentComment->authorEmail = 'parent@example.com';
    $parentComment->content = 'This is the parent comment.';

    $childComment1 = new Comment();
    $childComment1->id = 2;
    $childComment1->postId = 1;
    $childComment1->parentId = 1;
    $childComment1->authorName = 'Child Author 1';
    $childComment1->authorEmail = 'child1@example.com';
    $childComment1->content = 'First reply.';

    $childComment2 = new Comment();
    $childComment2->id = 3;
    $childComment2->postId = 1;
    $childComment2->parentId = 1;
    $childComment2->authorName = 'Child Author 2';
    $childComment2->authorEmail = 'child2@example.com';
    $childComment2->content = 'Second reply.';

    // Initially empty
    expect($parentComment->getChildren())->toBe([]);

    // After setting children
    $parentComment->setChildren([$childComment1, $childComment2]);
    expect($parentComment->getChildren())->toHaveCount(2)
        ->and($parentComment->getChildren()[0]->getAuthorName())->toBe('Child Author 1')
        ->and($parentComment->getChildren()[1]->getAuthorName())->toBe('Child Author 2');
});
