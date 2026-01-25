<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Entity;

use Marko\Blog\Entity\PostCategory;
use Marko\Blog\Entity\PostCategoryInterface;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use ReflectionClass;

it('creates post category pivot with post_id and category_id', function (): void {
    $postCategory = new PostCategory(
        postId: 1,
        categoryId: 2,
    );

    expect($postCategory)->toBeInstanceOf(PostCategoryInterface::class)
        ->and($postCategory)->toBeInstanceOf(Entity::class)
        ->and($postCategory->getPostId())->toBe(1)
        ->and($postCategory->getCategoryId())->toBe(2);
});

it('has Table attribute pointing to post_categories table', function (): void {
    $reflection = new ReflectionClass(PostCategory::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('post_categories');
});

it('has Column attributes for post_id and category_id', function (): void {
    $reflection = new ReflectionClass(PostCategory::class);

    $postIdProperty = $reflection->getProperty('postId');
    $postIdAttributes = $postIdProperty->getAttributes(Column::class);

    $categoryIdProperty = $reflection->getProperty('categoryId');
    $categoryIdAttributes = $categoryIdProperty->getAttributes(Column::class);

    expect($postIdAttributes)->toHaveCount(1)
        ->and($postIdAttributes[0]->newInstance()->name)->toBe('post_id')
        ->and($categoryIdAttributes)->toHaveCount(1)
        ->and($categoryIdAttributes[0]->newInstance()->name)->toBe('category_id');
});

it('enforces foreign key to posts table', function (): void {
    $reflection = new ReflectionClass(PostCategory::class);

    $postIdProperty = $reflection->getProperty('postId');
    $postIdAttributes = $postIdProperty->getAttributes(Column::class);

    expect($postIdAttributes)->toHaveCount(1);

    $column = $postIdAttributes[0]->newInstance();

    expect($column->references)->toBe('posts.id')
        ->and($column->onDelete)->toBe('CASCADE');
});

it('enforces foreign key to categories table', function (): void {
    $reflection = new ReflectionClass(PostCategory::class);

    $categoryIdProperty = $reflection->getProperty('categoryId');
    $categoryIdAttributes = $categoryIdProperty->getAttributes(Column::class);

    expect($categoryIdAttributes)->toHaveCount(1);

    $column = $categoryIdAttributes[0]->newInstance();

    expect($column->references)->toBe('categories.id')
        ->and($column->onDelete)->toBe('CASCADE');
});

it('prevents duplicate post category combinations', function (): void {
    $reflection = new ReflectionClass(PostCategory::class);
    $indexAttributes = $reflection->getAttributes(Index::class);

    expect($indexAttributes)->toHaveCount(1);

    $index = $indexAttributes[0]->newInstance();

    expect($index->unique)->toBeTrue()
        ->and($index->columns)->toBe(['post_id', 'category_id']);
});
