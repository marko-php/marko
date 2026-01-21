<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Attributes;

use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use ReflectionClass;

#[Table('posts')]
#[Index('idx_author', ['author_id'])]
class IndexTestEntity extends Entity
{
    public int $id;

    public int $authorId;

    public string $title;
}

#[Table('users')]
#[Index('idx_email', ['email'], unique: true)]
class UniqueIndexTestEntity extends Entity
{
    public int $id;

    public string $email;
}

#[Table('articles')]
#[Index('idx_author', ['author_id'])]
#[Index('idx_published', ['published_at'])]
#[Index('idx_author_published', ['author_id', 'published_at'])]
class MultipleIndexTestEntity extends Entity
{
    public int $id;

    public int $authorId;

    public string $title;

    public ?string $publishedAt;
}

it('creates #[Index] attribute with name and columns', function (): void {
    $reflection = new ReflectionClass(IndexTestEntity::class);
    $attributes = $reflection->getAttributes(Index::class);

    expect($attributes)->toHaveCount(1);

    $index = $attributes[0]->newInstance();
    expect($index)->toBeInstanceOf(Index::class);
    expect($index->name)->toBe('idx_author');
    expect($index->columns)->toBe(['author_id']);
});

it('supports #[Index] unique parameter', function (): void {
    $reflection = new ReflectionClass(UniqueIndexTestEntity::class);
    $attributes = $reflection->getAttributes(Index::class);

    $index = $attributes[0]->newInstance();
    expect($index->name)->toBe('idx_email');
    expect($index->columns)->toBe(['email']);
    expect($index->unique)->toBeTrue();
});

it('allows multiple #[Index] attributes on a class', function (): void {
    $reflection = new ReflectionClass(MultipleIndexTestEntity::class);
    $attributes = $reflection->getAttributes(Index::class);

    expect($attributes)->toHaveCount(3);

    $indexes = array_map(fn ($attr) => $attr->newInstance(), $attributes);

    expect($indexes[0]->name)->toBe('idx_author');
    expect($indexes[0]->columns)->toBe(['author_id']);

    expect($indexes[1]->name)->toBe('idx_published');
    expect($indexes[1]->columns)->toBe(['published_at']);

    expect($indexes[2]->name)->toBe('idx_author_published');
    expect($indexes[2]->columns)->toBe(['author_id', 'published_at']);
});
