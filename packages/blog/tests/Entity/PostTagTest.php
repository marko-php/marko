<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Entity;

use Marko\Blog\Entity\PostTag;
use Marko\Blog\Entity\PostTagInterface;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use ReflectionClass;

it('creates post tag pivot with post_id and tag_id', function (): void {
    $postTag = new PostTag();
    $postTag->postId = 1;
    $postTag->tagId = 2;

    expect($postTag->postId)->toBe(1)
        ->and($postTag->tagId)->toBe(2)
        ->and($postTag)->toBeInstanceOf(Entity::class)
        ->and($postTag)->toBeInstanceOf(PostTagInterface::class);

    // Verify Table attribute
    $reflection = new ReflectionClass(PostTag::class);
    $tableAttributes = $reflection->getAttributes(Table::class);

    expect($tableAttributes)->toHaveCount(1);

    $tableAttribute = $tableAttributes[0]->newInstance();
    expect($tableAttribute->name)->toBe('post_tags');
});

it('enforces foreign key to posts table', function (): void {
    $reflection = new ReflectionClass(PostTag::class);
    $property = $reflection->getProperty('postId');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('post_id')
        ->and($columnAttribute->references)->toBe('posts.id')
        ->and($columnAttribute->onDelete)->toBe('CASCADE');
});

it('enforces foreign key to tags table', function (): void {
    $reflection = new ReflectionClass(PostTag::class);
    $property = $reflection->getProperty('tagId');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $columnAttribute = $attributes[0]->newInstance();
    expect($columnAttribute->name)->toBe('tag_id')
        ->and($columnAttribute->references)->toBe('tags.id')
        ->and($columnAttribute->onDelete)->toBe('CASCADE');
});

it('prevents duplicate post tag combinations', function (): void {
    $reflection = new ReflectionClass(PostTag::class);
    $indexAttributes = $reflection->getAttributes(Index::class);

    expect($indexAttributes)->not->toBeEmpty();

    // Find the unique index on post_id and tag_id
    $foundUniqueIndex = false;

    foreach ($indexAttributes as $attribute) {
        $index = $attribute->newInstance();
        if ($index->unique && in_array('post_id', $index->columns, true) && in_array(
            'tag_id',
            $index->columns,
            true
        )) {
            $foundUniqueIndex = true;
            break;
        }
    }

    expect($foundUniqueIndex)->toBeTrue();
});
