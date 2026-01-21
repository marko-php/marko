<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema;

use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use ReflectionClass;

describe('Index', function (): void {
    it('creates readonly Index class with name, columns, and type (btree, unique, fulltext)', function (): void {
        // Default btree index
        $btreeIndex = new Index(
            name: 'idx_posts_author_id',
            columns: ['author_id'],
        );

        expect($btreeIndex->name)->toBe('idx_posts_author_id');
        expect($btreeIndex->columns)->toBe(['author_id']);
        expect($btreeIndex->type)->toBe(IndexType::Btree);

        // Unique index
        $uniqueIndex = new Index(
            name: 'idx_posts_slug',
            columns: ['slug'],
            type: IndexType::Unique,
        );

        expect($uniqueIndex->name)->toBe('idx_posts_slug');
        expect($uniqueIndex->columns)->toBe(['slug']);
        expect($uniqueIndex->type)->toBe(IndexType::Unique);

        // Fulltext index
        $fulltextIndex = new Index(
            name: 'idx_posts_content',
            columns: ['title', 'body'],
            type: IndexType::Fulltext,
        );

        expect($fulltextIndex->name)->toBe('idx_posts_content');
        expect($fulltextIndex->columns)->toBe(['title', 'body']);
        expect($fulltextIndex->type)->toBe(IndexType::Fulltext);

        // Verify it's a readonly class
        $reflection = new ReflectionClass($btreeIndex);
        expect($reflection->isReadOnly())->toBeTrue();
    });
});
