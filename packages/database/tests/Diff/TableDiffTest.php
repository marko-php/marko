<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Diff;

use Marko\Database\Diff\TableDiff;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\Table;
use ReflectionClass;

describe('TableDiff', function (): void {
    it('creates readonly TableDiff value object', function (): void {
        $diff = new TableDiff(tableName: 'posts');

        $reflection = new ReflectionClass($diff);
        expect($reflection->isReadOnly())->toBeTrue();
    });

    it('stores all diff components', function (): void {
        $columnsToAdd = [new Column(name: 'slug', type: 'VARCHAR')];
        $columnsToDrop = [new Column(name: 'old_field', type: 'TEXT')];
        $columnsToModify = ['title' => new Column(name: 'title', type: 'TEXT')];
        $indexesToAdd = [new Index(name: 'idx_slug', columns: ['slug'])];
        $indexesToDrop = [new Index(name: 'idx_old', columns: ['old_field'])];
        $foreignKeysToAdd = [
            new ForeignKey(
                name: 'fk_user',
                columns: ['user_id'],
                referencedTable: 'users',
                referencedColumns: ['id'],
            ),
        ];
        $foreignKeysToDrop = [
            new ForeignKey(
                name: 'fk_old',
                columns: ['old_id'],
                referencedTable: 'old_table',
                referencedColumns: ['id'],
            ),
        ];

        $diff = new TableDiff(
            tableName: 'posts',
            columnsToAdd: $columnsToAdd,
            columnsToDrop: $columnsToDrop,
            columnsToModify: $columnsToModify,
            indexesToAdd: $indexesToAdd,
            indexesToDrop: $indexesToDrop,
            foreignKeysToAdd: $foreignKeysToAdd,
            foreignKeysToDrop: $foreignKeysToDrop,
        );

        expect($diff->tableName)
            ->toBe('posts')
            ->and($diff->columnsToAdd)->toBe($columnsToAdd)
            ->and($diff->columnsToDrop)->toBe($columnsToDrop)
            ->and($diff->columnsToModify)->toBe($columnsToModify)
            ->and($diff->indexesToAdd)->toBe($indexesToAdd)
            ->and($diff->indexesToDrop)->toBe($indexesToDrop)
            ->and($diff->foreignKeysToAdd)->toBe($foreignKeysToAdd)
            ->and($diff->foreignKeysToDrop)->toBe($foreignKeysToDrop);
    });

    it('defaults columnsToModifyFrom to an empty array', function (): void {
        $diff = new TableDiff(
            tableName: 'posts',
            columnsToModify: ['title' => new Column(name: 'title', type: 'TEXT')],
        );

        expect($diff->columnsToModifyFrom)->toBeEmpty();
    });

    it('returns the previous definition of a modified column', function (): void {
        $previous = new Column(name: 'title', type: 'VARCHAR');

        $diff = new TableDiff(
            tableName: 'posts',
            columnsToModify: ['title' => new Column(name: 'title', type: 'TEXT')],
            columnsToModifyFrom: ['title' => $previous],
        );

        expect($diff->previousColumn('title'))->toBe($previous);
    });

    it(
        'throws a MigrationException naming the table and column when the previous definition is missing',
        function (): void {
            $diff = new TableDiff(
                tableName: 'posts',
                columnsToModify: ['title' => new Column(name: 'title', type: 'TEXT')],
            );

            expect(fn () => $diff->previousColumn('title'))->toThrow(
                MigrationException::class,
                "Column 'posts.title' is modified, but the diff holds no previous definition for it",
            );
        },
    );

    it('reports isEmpty correctly', function (): void {
        $emptyDiff = new TableDiff(tableName: 'posts');
        $diffWithAddColumn = new TableDiff(
            tableName: 'posts',
            columnsToAdd: [new Column(name: 'slug', type: 'VARCHAR')],
        );
        $diffWithDropIndex = new TableDiff(
            tableName: 'posts',
            indexesToDrop: [new Index(name: 'idx_old', columns: ['old'])],
        );

        expect($emptyDiff->isEmpty())->toBeTrue()
            ->and($diffWithAddColumn->isEmpty())->toBeFalse()
            ->and($diffWithDropIndex->isEmpty())->toBeFalse();
    });

    it('reports hasDestructiveChanges correctly', function (): void {
        $emptyDiff = new TableDiff(tableName: 'posts');
        $diffWithAddColumn = new TableDiff(
            tableName: 'posts',
            columnsToAdd: [new Column(name: 'slug', type: 'VARCHAR')],
        );
        $diffWithDropColumn = new TableDiff(
            tableName: 'posts',
            columnsToDrop: [new Column(name: 'old', type: 'VARCHAR')],
        );
        $diffWithDropIndex = new TableDiff(
            tableName: 'posts',
            indexesToDrop: [new Index(name: 'idx_old', columns: ['old'])],
        );
        $diffWithDropFk = new TableDiff(
            tableName: 'posts',
            foreignKeysToDrop: [
                new ForeignKey(
                    name: 'fk_old',
                    columns: ['old_id'],
                    referencedTable: 'old',
                    referencedColumns: ['id'],
                ),
            ],
        );

        expect($emptyDiff->hasDestructiveChanges())->toBeFalse()
            ->and($diffWithAddColumn->hasDestructiveChanges())->toBeFalse()
            ->and($diffWithDropColumn->hasDestructiveChanges())->toBeTrue()
            ->and($diffWithDropIndex->hasDestructiveChanges())->toBeTrue()
            ->and($diffWithDropFk->hasDestructiveChanges())->toBeTrue();
    });

    it('returns destructive changes list', function (): void {
        $diff = new TableDiff(
            tableName: 'posts',
            columnsToDrop: [new Column(name: 'old_col', type: 'VARCHAR')],
            indexesToDrop: [new Index(name: 'idx_old', columns: ['old_col'])],
            foreignKeysToDrop: [
                new ForeignKey(
                    name: 'fk_old',
                    columns: ['old_id'],
                    referencedTable: 'old',
                    referencedColumns: ['id'],
                ),
            ],
        );

        $changes = $diff->getDestructiveChanges();

        expect($changes)
            ->toContain('DROP COLUMN posts.old_col')
            ->toContain('DROP INDEX posts.idx_old')
            ->toContain('DROP FOREIGN KEY posts.fk_old');
    });

    it('returns summary lines', function (): void {
        $diff = new TableDiff(
            tableName: 'posts',
            columnsToAdd: [new Column(name: 'slug', type: 'VARCHAR')],
            columnsToModify: ['title' => new Column(name: 'title', type: 'TEXT')],
            indexesToAdd: [new Index(name: 'idx_slug', columns: ['slug'])],
        );

        $lines = $diff->getSummaryLines();

        expect($lines)
            ->toContain('  Add column: slug')
            ->toContain('  Modify column: title')
            ->toContain('  Add index: idx_slug');
    });

    it('defaults the current primary key to an empty list', function (): void {
        expect(new TableDiff(tableName: 'posts')->currentPrimaryKey)->toBeEmpty();
    });

    it('keeps the current primary key out of isEmpty so a key alone is no change', function (): void {
        expect(new TableDiff(tableName: 'posts', currentPrimaryKey: ['id'])->isEmpty())->toBeTrue();
    });

    it('accepts primary key columns added to a table without a primary key', function (): void {
        $diff = new TableDiff(tableName: 'post_tags', columnsToAdd: [
            new Column(name: 'id', type: 'int', primaryKey: true, autoIncrement: true),
        ]);

        expect(fn () => $diff->assertSupportedPrimaryKeyChange('MySQL'))->not->toThrow(MigrationException::class);
    });

    it('keeps the current table and current primary key on the destructive-only diff', function (): void {
        $currentTable = new Table(name: 'posts', columns: [new Column(name: 'id', type: 'int', primaryKey: true)]);
        $diff = new TableDiff(
            tableName: 'posts',
            columnsToDrop: [new Column(name: 'old', type: 'text')],
            currentPrimaryKey: ['id'],
            currentTable: $currentTable,
        );

        $destructive = $diff->destructiveOnly();

        expect($destructive->currentTable)->toBe($currentTable)
            ->and($destructive->currentPrimaryKey)->toBe(['id']);
    });

    it('treats a diff whose only content is the current table as empty', function (): void {
        $diff = new TableDiff(
            tableName: 'posts',
            currentTable: new Table(name: 'posts', columns: [new Column(name: 'id', type: 'int')]),
        );

        expect($diff->isEmpty())->toBeTrue()
            ->and($diff->hasDestructiveChanges())->toBeFalse()
            ->and($diff->getDestructiveChanges())->toBeEmpty()
            ->and($diff->getSummaryLines())->toBeEmpty();
    });

    it('accepts dropping every column of the current primary key', function (): void {
        $diff = new TableDiff(
            tableName: 'posts',
            columnsToDrop: [new Column(name: 'id', type: 'int', primaryKey: true)],
            currentPrimaryKey: ['id'],
        );

        expect(fn () => $diff->assertSupportedPrimaryKeyChange('MySQL'))->not->toThrow(MigrationException::class);
    });

    it('refuses primary key columns added to a table that already has a primary key', function (): void {
        $diff = new TableDiff(
            tableName: 'posts',
            columnsToAdd: [new Column(name: 'uuid', type: 'uuid', primaryKey: true)],
            currentPrimaryKey: ['id'],
        );

        expect(fn () => $diff->assertSupportedPrimaryKeyChange('MySQL'))->toThrow(
            MigrationException::class,
            "Cannot add primary key column 'uuid' to table 'posts', which already has a primary key on 'id'",
        );
    });

    it('refuses a new primary key even when the diff drops the current key columns', function (): void {
        $diff = new TableDiff(
            tableName: 'posts',
            columnsToAdd: [new Column(name: 'uuid', type: 'uuid', primaryKey: true)],
            columnsToDrop: [new Column(name: 'id', type: 'int', primaryKey: true)],
            currentPrimaryKey: ['id'],
        );

        expect(fn () => $diff->assertSupportedPrimaryKeyChange('PostgreSQL'))->toThrow(
            MigrationException::class,
            "Cannot add primary key column 'uuid' to table 'posts', which already has a primary key on 'id'",
        );
    });

    it('refuses dropping part of a composite primary key', function (): void {
        $diff = new TableDiff(
            tableName: 'post_tags',
            columnsToDrop: [new Column(name: 'tag_id', type: 'int', primaryKey: true)],
            currentPrimaryKey: ['post_id', 'tag_id'],
        );

        expect(fn () => $diff->assertSupportedPrimaryKeyChange('PostgreSQL'))->toThrow(
            MigrationException::class,
            "Cannot change the primary key of column 'post_tags.tag_id' in place on PostgreSQL",
        );
    });
});
