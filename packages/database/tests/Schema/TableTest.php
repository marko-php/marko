<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema;

use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
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

        expect($table->name)->toBe('posts')
            ->and($table->columns)->toBe($columns)
            ->and($table->columns)->toHaveCount(2);

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
        expect($table->columns)->toBeEmpty()
            // First addition
            ->and($tableWithId->columns)->toHaveCount(1)
            ->and($tableWithId->columns[0]->name)->toBe('id')
            ->and($tableWithId->name)->toBe('posts')
            // Second addition
            ->and($tableWithBoth->columns)->toHaveCount(2)
            ->and($tableWithBoth->columns[0]->name)->toBe('id')
            ->and($tableWithBoth->columns[1]->name)->toBe('title')
            // All are different instances
            ->and($table)->not->toBe($tableWithId)
            ->and($tableWithId)->not->toBe($tableWithBoth);
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
        expect($table->indexes)->toBeEmpty()
            // First addition
            ->and($tableWithSlugIdx->indexes)->toHaveCount(1)
            ->and($tableWithSlugIdx->indexes[0]->name)->toBe('idx_posts_slug')
            // Second addition
            ->and($tableWithBoth->indexes)->toHaveCount(2)
            ->and($tableWithBoth->indexes[0]->name)->toBe('idx_posts_slug')
            ->and($tableWithBoth->indexes[1]->name)->toBe('idx_posts_author')
            // All are different instances
            ->and($table)->not->toBe($tableWithSlugIdx)
            ->and($tableWithSlugIdx)->not->toBe($tableWithBoth);
    });

    it('provides Table::withForeignKey() for immutable building', function (): void {
        $table = new Table(name: 'posts');

        $userFk = new ForeignKey(
            name: 'fk_posts_user',
            columns: ['user_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
            onDelete: 'CASCADE',
        );
        $categoryFk = new ForeignKey(
            name: 'fk_posts_category',
            columns: ['category_id'],
            referencedTable: 'categories',
            referencedColumns: ['id'],
        );

        $tableWithUserFk = $table->withForeignKey($userFk);
        $tableWithBoth = $tableWithUserFk->withForeignKey($categoryFk);

        // Original table is unchanged (immutable)
        expect($table->foreignKeys)->toBeEmpty()
            // First addition
            ->and($tableWithUserFk->foreignKeys)->toHaveCount(1)
            ->and($tableWithUserFk->foreignKeys[0]->name)->toBe('fk_posts_user')
            // Second addition
            ->and($tableWithBoth->foreignKeys)->toHaveCount(2)
            ->and($tableWithBoth->foreignKeys[0]->name)->toBe('fk_posts_user')
            ->and($tableWithBoth->foreignKeys[1]->name)->toBe('fk_posts_category')
            // All are different instances
            ->and($table)->not->toBe($tableWithUserFk)
            ->and($tableWithUserFk)->not->toBe($tableWithBoth);
    });

    it('supports foreignKeys array in constructor', function (): void {
        $foreignKeys = [
            new ForeignKey(
                name: 'fk_posts_user',
                columns: ['user_id'],
                referencedTable: 'users',
                referencedColumns: ['id'],
            ),
        ];

        $table = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'id', type: 'int'),
                new Column(name: 'user_id', type: 'int'),
            ],
            indexes: [],
            foreignKeys: $foreignKeys,
        );

        expect($table->foreignKeys)->toBe($foreignKeys)
            ->and($table->foreignKeys)->toHaveCount(1)
            ->and($table->foreignKeys[0]->name)->toBe('fk_posts_user');
    });
});
