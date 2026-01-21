<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Entity;

use Marko\Blog\Entity\Post;
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
