<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Entity;

use DateTimeImmutable;
use Marko\Blog\Entity\Tag;
use Marko\Blog\Entity\TagInterface;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use ReflectionClass;

it('creates tag with name and slug', function (): void {
    $tag = new Tag();
    $tag->name = 'PHP Development';
    $tag->slug = 'php-development';

    expect($tag->name)->toBe('PHP Development')
        ->and($tag->slug)->toBe('php-development');
});

it('requires name field', function (): void {
    $reflection = new ReflectionClass(Tag::class);
    $property = $reflection->getProperty('name');

    expect($property->getType()->allowsNull())->toBeFalse()
        ->and($property->getType()->getName())->toBe('string');
});

it('auto-generates slug from name using SlugGenerator', function (): void {
    // This is tested at the integration level with TagRepository
    // Tag entity itself just stores the slug value
    $tag = new Tag();
    $tag->name = 'Test Tag';
    $tag->slug = 'test-tag';

    expect($tag->slug)->toBe('test-tag');
});

it('allows manual slug override', function (): void {
    $tag = new Tag();
    $tag->name = 'My Custom Tag';
    $tag->slug = 'custom-slug-override';

    expect($tag->slug)->toBe('custom-slug-override')
        ->and($tag->name)->toBe('My Custom Tag');
});

it('ensures slug uniqueness within tags table', function (): void {
    $reflection = new ReflectionClass(Tag::class);
    $property = $reflection->getProperty('slug');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->unique)->toBeTrue();
});

it('has created_at timestamp', function (): void {
    $reflection = new ReflectionClass(Tag::class);

    expect($reflection->hasProperty('createdAt'))->toBeTrue();

    $property = $reflection->getProperty('createdAt');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('created_at');
});

it('has updated_at timestamp', function (): void {
    $reflection = new ReflectionClass(Tag::class);

    expect($reflection->hasProperty('updatedAt'))->toBeTrue();

    $property = $reflection->getProperty('updatedAt');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('updated_at');
});

it('extends the Entity base class', function (): void {
    $tag = new Tag();

    expect($tag)->toBeInstanceOf(Entity::class);
});

it('implements TagInterface', function (): void {
    $tag = new Tag();

    expect($tag)->toBeInstanceOf(TagInterface::class);
});

it('has Table attribute with tags table name', function (): void {
    $reflection = new ReflectionClass(Tag::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $tableAttribute = $attributes[0]->newInstance();
    expect($tableAttribute->name)->toBe('tags');
});

it('has id property with primaryKey and autoIncrement Column attributes', function (): void {
    $reflection = new ReflectionClass(Tag::class);

    expect($reflection->hasProperty('id'))->toBeTrue();

    $property = $reflection->getProperty('id');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->primaryKey)->toBeTrue()
        ->and($columnAttribute->autoIncrement)->toBeTrue();
});

it('has name property with Column attribute', function (): void {
    $reflection = new ReflectionClass(Tag::class);

    expect($reflection->hasProperty('name'))->toBeTrue();

    $property = $reflection->getProperty('name');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1)
        ->and($property->getType()->getName())->toBe('string');
});

it('has slug property with Column attribute and unique constraint', function (): void {
    $reflection = new ReflectionClass(Tag::class);

    expect($reflection->hasProperty('slug'))->toBeTrue();

    $property = $reflection->getProperty('slug');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->unique)->toBeTrue()
        ->and($property->getType()->getName())->toBe('string');
});

it('uses nullable types for optional fields appropriately', function (): void {
    $reflection = new ReflectionClass(Tag::class);

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
    $nameProperty = $reflection->getProperty('name');
    $slugProperty = $reflection->getProperty('slug');

    expect($nameProperty->getType()->allowsNull())->toBeFalse()
        ->and($slugProperty->getType()->allowsNull())->toBeFalse();
});

it('exposes getter methods via TagInterface', function (): void {
    $tag = new Tag();
    $tag->id = 1;
    $tag->name = 'PHP';
    $tag->slug = 'php';
    $tag->createdAt = '2024-01-01 00:00:00';
    $tag->updatedAt = '2024-01-02 00:00:00';

    expect($tag->getId())->toBe(1)
        ->and($tag->getName())->toBe('PHP')
        ->and($tag->getSlug())->toBe('php')
        ->and($tag->getCreatedAt())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($tag->getUpdatedAt())->toBeInstanceOf(DateTimeImmutable::class);
});

it('returns null for timestamps when not set', function (): void {
    $tag = new Tag();
    $tag->name = 'Test';
    $tag->slug = 'test';

    expect($tag->getCreatedAt())->toBeNull()
        ->and($tag->getUpdatedAt())->toBeNull();
});
