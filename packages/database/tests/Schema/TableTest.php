<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema;

use Marko\Database\Schema\Column;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;
use ReflectionClass;

describe('Table', function (): void {
    it('creates readonly Table class with name and columns', function (): void {
        $columns = [
            new Column(name: 'id', type: 'int'),
            new Column(name: 'title', type: 'varchar'),
        ];

        $table = new Table(name: 'posts', columns: $columns);

        expect($table->name)->toBe('posts');
        expect($table->columns)->toBe($columns);
        expect($table->columns)->toHaveCount(2);

        // Verify it's a readonly class
        $reflection = new ReflectionClass($table);
        expect($reflection->isReadOnly())->toBeTrue();
    });

    it('provides Table::withColumn() for immutable building', function (): void {
        $table = new Table(name: 'posts');

        $idColumn = new Column(name: 'id', type: 'int', primaryKey: true);
        $titleColumn = new Column(name: 'title', type: 'varchar');

        $tableWithId = $table->withColumn($idColumn);
        $tableWithBoth = $tableWithId->withColumn($titleColumn);

        // Original table is unchanged (immutable)
        expect($table->columns)->toHaveCount(0);

        // First addition
        expect($tableWithId->columns)->toHaveCount(1);
        expect($tableWithId->columns[0]->name)->toBe('id');
        expect($tableWithId->name)->toBe('posts');

        // Second addition
        expect($tableWithBoth->columns)->toHaveCount(2);
        expect($tableWithBoth->columns[0]->name)->toBe('id');
        expect($tableWithBoth->columns[1]->name)->toBe('title');

        // All are different instances
        expect($table)->not->toBe($tableWithId);
        expect($tableWithId)->not->toBe($tableWithBoth);
    });

    it('provides Table::withIndex() for immutable building', function (): void {
        $table = new Table(name: 'posts');

        $slugIndex = new Index(
            name: 'idx_posts_slug',
            columns: ['slug'],
            type: IndexType::Unique,
        );
        $authorIndex = new Index(
            name: 'idx_posts_author',
            columns: ['author_id'],
        );

        $tableWithSlugIdx = $table->withIndex($slugIndex);
        $tableWithBoth = $tableWithSlugIdx->withIndex($authorIndex);

        // Original table is unchanged (immutable)
        expect($table->indexes)->toHaveCount(0);

        // First addition
        expect($tableWithSlugIdx->indexes)->toHaveCount(1);
        expect($tableWithSlugIdx->indexes[0]->name)->toBe('idx_posts_slug');

        // Second addition
        expect($tableWithBoth->indexes)->toHaveCount(2);
        expect($tableWithBoth->indexes[0]->name)->toBe('idx_posts_slug');
        expect($tableWithBoth->indexes[1]->name)->toBe('idx_posts_author');

        // All are different instances
        expect($table)->not->toBe($tableWithSlugIdx);
        expect($tableWithSlugIdx)->not->toBe($tableWithBoth);
    });
});
