<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Diff;

use Marko\Database\Diff\TableDiff;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
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

    it('reports isEmpty correctly', function (): void {
        $emptyDiff = new TableDiff(tableName: 'posts');
        expect($emptyDiff->isEmpty())->toBeTrue();

        $diffWithAddColumn = new TableDiff(
            tableName: 'posts',
            columnsToAdd: [new Column(name: 'slug', type: 'VARCHAR')],
        );
        expect($diffWithAddColumn->isEmpty())->toBeFalse();

        $diffWithDropIndex = new TableDiff(
            tableName: 'posts',
            indexesToDrop: [new Index(name: 'idx_old', columns: ['old'])],
        );
        expect($diffWithDropIndex->isEmpty())->toBeFalse();
    });

    it('reports hasDestructiveChanges correctly', function (): void {
        $emptyDiff = new TableDiff(tableName: 'posts');
        expect($emptyDiff->hasDestructiveChanges())->toBeFalse();

        $diffWithAddColumn = new TableDiff(
            tableName: 'posts',
            columnsToAdd: [new Column(name: 'slug', type: 'VARCHAR')],
        );
        expect($diffWithAddColumn->hasDestructiveChanges())->toBeFalse();

        $diffWithDropColumn = new TableDiff(
            tableName: 'posts',
            columnsToDrop: [new Column(name: 'old', type: 'VARCHAR')],
        );
        expect($diffWithDropColumn->hasDestructiveChanges())->toBeTrue();

        $diffWithDropIndex = new TableDiff(
            tableName: 'posts',
            indexesToDrop: [new Index(name: 'idx_old', columns: ['old'])],
        );
        expect($diffWithDropIndex->hasDestructiveChanges())->toBeTrue();

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
        expect($diffWithDropFk->hasDestructiveChanges())->toBeTrue();
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
});
