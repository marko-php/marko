<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema;

use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;

describe('Schema Value Objects Equality', function (): void {
    it('implements equals() method for diff comparison', function (): void {
        // Column equality
        $column1 = new Column(name: 'id', type: 'int', primaryKey: true);
        $column2 = new Column(name: 'id', type: 'int', primaryKey: true);
        $column3 = new Column(name: 'id', type: 'bigint', primaryKey: true);
        $column4 = new Column(name: 'user_id', type: 'int', primaryKey: true);

        expect($column1->equals($column2))->toBeTrue();
        expect($column1->equals($column3))->toBeFalse(); // Different type
        expect($column1->equals($column4))->toBeFalse(); // Different name

        // Column with all properties
        $fullColumn1 = new Column(
            name: 'email',
            type: 'varchar',
            length: 255,
            nullable: true,
            default: null,
            unique: true,
        );
        $fullColumn2 = new Column(
            name: 'email',
            type: 'varchar',
            length: 255,
            nullable: true,
            default: null,
            unique: true,
        );
        $fullColumn3 = new Column(
            name: 'email',
            type: 'varchar',
            length: 100, // Different length
            nullable: true,
            default: null,
            unique: true,
        );

        expect($fullColumn1->equals($fullColumn2))->toBeTrue();
        expect($fullColumn1->equals($fullColumn3))->toBeFalse();

        // Index equality
        $index1 = new Index(name: 'idx_posts_slug', columns: ['slug'], type: IndexType::Unique);
        $index2 = new Index(name: 'idx_posts_slug', columns: ['slug'], type: IndexType::Unique);
        $index3 = new Index(name: 'idx_posts_slug', columns: ['slug'], type: IndexType::Btree);
        $index4 = new Index(name: 'idx_posts_title', columns: ['title'], type: IndexType::Unique);

        expect($index1->equals($index2))->toBeTrue();
        expect($index1->equals($index3))->toBeFalse(); // Different type
        expect($index1->equals($index4))->toBeFalse(); // Different name and columns

        // ForeignKey equality
        $fk1 = new ForeignKey(
            name: 'fk_posts_author',
            columns: ['author_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
            onDelete: 'CASCADE',
        );
        $fk2 = new ForeignKey(
            name: 'fk_posts_author',
            columns: ['author_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
            onDelete: 'CASCADE',
        );
        $fk3 = new ForeignKey(
            name: 'fk_posts_author',
            columns: ['author_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
            onDelete: 'SET NULL', // Different action
        );

        expect($fk1->equals($fk2))->toBeTrue();
        expect($fk1->equals($fk3))->toBeFalse();

        // Table equality
        $table1 = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'id', type: 'int', primaryKey: true),
                new Column(name: 'title', type: 'varchar'),
            ],
            indexes: [
                new Index(name: 'idx_posts_title', columns: ['title']),
            ],
        );
        $table2 = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'id', type: 'int', primaryKey: true),
                new Column(name: 'title', type: 'varchar'),
            ],
            indexes: [
                new Index(name: 'idx_posts_title', columns: ['title']),
            ],
        );
        $table3 = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'id', type: 'bigint', primaryKey: true), // Different column type
                new Column(name: 'title', type: 'varchar'),
            ],
            indexes: [
                new Index(name: 'idx_posts_title', columns: ['title']),
            ],
        );

        expect($table1->equals($table2))->toBeTrue();
        expect($table1->equals($table3))->toBeFalse();
    });
});
