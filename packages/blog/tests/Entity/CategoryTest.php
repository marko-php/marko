<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Entity;

use DateTimeImmutable;
use Marko\Blog\Entity\Category;
use Marko\Blog\Entity\CategoryInterface;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use ReflectionClass;

it('extends the Entity base class', function (): void {
    $category = new Category();

    expect($category)->toBeInstanceOf(Entity::class);
});

it('implements CategoryInterface', function (): void {
    $category = new Category();

    expect($category)->toBeInstanceOf(CategoryInterface::class);
});

it('has Table attribute with categories table name', function (): void {
    $reflection = new ReflectionClass(Category::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $tableAttribute = $attributes[0]->newInstance();
    expect($tableAttribute->name)->toBe('categories');
});

it('has id property with primaryKey and autoIncrement Column attributes', function (): void {
    $reflection = new ReflectionClass(Category::class);

    expect($reflection->hasProperty('id'))->toBeTrue();

    $property = $reflection->getProperty('id');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->primaryKey)->toBeTrue()
        ->and($columnAttribute->autoIncrement)->toBeTrue();
});

it('creates category with name and slug', function (): void {
    $category = new Category();
    $category->name = 'Technology';
    $category->slug = 'technology';

    expect($category->name)->toBe('Technology')
        ->and($category->slug)->toBe('technology');
});

it('requires name field', function (): void {
    $reflection = new ReflectionClass(Category::class);
    $property = $reflection->getProperty('name');

    expect($property->getType()->allowsNull())->toBeFalse()
        ->and($property->getType()->getName())->toBe('string');

    $attributes = $property->getAttributes(Column::class);
    expect($attributes)->toHaveCount(1);
});

it('has slug property with Column attribute and unique constraint', function (): void {
    $reflection = new ReflectionClass(Category::class);

    expect($reflection->hasProperty('slug'))->toBeTrue();

    $property = $reflection->getProperty('slug');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->unique)->toBeTrue()
        ->and($property->getType()->getName())->toBe('string');
});

it('allows optional parent category via parentId', function (): void {
    $reflection = new ReflectionClass(Category::class);

    expect($reflection->hasProperty('parentId'))->toBeTrue();

    $property = $reflection->getProperty('parentId');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);
    expect($property->getType()->allowsNull())->toBeTrue();

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('parent_id');
});

it('has createdAt property with Column attribute mapping to created_at', function (): void {
    $reflection = new ReflectionClass(Category::class);

    expect($reflection->hasProperty('createdAt'))->toBeTrue();

    $property = $reflection->getProperty('createdAt');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('created_at');
});

it('has updatedAt property with Column attribute mapping to updated_at', function (): void {
    $reflection = new ReflectionClass(Category::class);

    expect($reflection->hasProperty('updatedAt'))->toBeTrue();

    $property = $reflection->getProperty('updatedAt');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('updated_at');
});

it('uses nullable types for optional fields appropriately', function (): void {
    $reflection = new ReflectionClass(Category::class);

    // id is nullable (null before insert, set after)
    $idProperty = $reflection->getProperty('id');
    expect($idProperty->getType()->allowsNull())->toBeTrue();

    // parentId is nullable (root categories have no parent)
    $parentIdProperty = $reflection->getProperty('parentId');
    expect($parentIdProperty->getType()->allowsNull())->toBeTrue();

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

it('implements getId method from CategoryInterface', function (): void {
    $category = new Category();

    expect($category->getId())->toBeNull();

    $category->id = 42;
    expect($category->getId())->toBe(42);
});

it('implements getName method from CategoryInterface', function (): void {
    $category = new Category();
    $category->name = 'Programming';

    expect($category->getName())->toBe('Programming');
});

it('implements getSlug method from CategoryInterface', function (): void {
    $category = new Category();
    $category->slug = 'programming';

    expect($category->getSlug())->toBe('programming');
});

it('implements getParentId method from CategoryInterface', function (): void {
    $category = new Category();

    expect($category->getParentId())->toBeNull();

    $category->parentId = 5;
    expect($category->getParentId())->toBe(5);
});

it('implements getCreatedAt method from CategoryInterface', function (): void {
    $category = new Category();

    expect($category->getCreatedAt())->toBeNull();

    $category->createdAt = '2024-01-15 10:30:00';
    $createdAt = $category->getCreatedAt();

    expect($createdAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($createdAt->format('Y-m-d H:i:s'))->toBe('2024-01-15 10:30:00');
});

it('implements getUpdatedAt method from CategoryInterface', function (): void {
    $category = new Category();

    expect($category->getUpdatedAt())->toBeNull();

    $category->updatedAt = '2024-01-20 14:45:00';
    $updatedAt = $category->getUpdatedAt();

    expect($updatedAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($updatedAt->format('Y-m-d H:i:s'))->toBe('2024-01-20 14:45:00');
});
