<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Sql;

use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Diff\TableDiff;
use Marko\Database\MySql\Sql\MySqlGenerator;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;

describe('MySqlGenerator', function (): void {
    it('implements SqlGeneratorInterface', function (): void {
        $generator = new MySqlGenerator();

        expect($generator)->toBeInstanceOf(SqlGeneratorInterface::class);
    });

    it('generates CREATE TABLE with all column definitions', function (): void {
        $generator = new MySqlGenerator();

        $table = new Table(
            name: 'users',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new Column(name: 'email', type: 'string', length: 255, unique: true),
                new Column(name: 'name', type: 'string', length: 100, nullable: true),
                new Column(name: 'created_at', type: 'datetime', default: 'CURRENT_TIMESTAMP'),
            ],
        );

        $sql = $generator->generateCreateTable($table);

        expect($sql)->toContain('CREATE TABLE `users`');
        expect($sql)->toContain('`id` INT NOT NULL AUTO_INCREMENT');
        expect($sql)->toContain('`email` VARCHAR(255) NOT NULL UNIQUE');
        expect($sql)->toContain('`name` VARCHAR(100) NULL');
        expect($sql)->toContain('`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        expect($sql)->toContain('PRIMARY KEY (`id`)');
    });

    it('generates DROP TABLE statements', function (): void {
        $generator = new MySqlGenerator();

        $sql = $generator->generateDropTable('users');

        expect($sql)->toBe('DROP TABLE `users`');
    });

    it('generates ALTER TABLE ADD COLUMN', function (): void {
        $generator = new MySqlGenerator();

        $column = new Column(
            name: 'bio',
            type: 'text',
            nullable: true,
        );

        $sql = $generator->generateAddColumn('users', $column);

        expect($sql)->toBe('ALTER TABLE `users` ADD COLUMN `bio` TEXT NULL');
    });

    it('generates ALTER TABLE DROP COLUMN', function (): void {
        $generator = new MySqlGenerator();

        $sql = $generator->generateDropColumn('users', 'bio');

        expect($sql)->toBe('ALTER TABLE `users` DROP COLUMN `bio`');
    });

    it('generates ALTER TABLE MODIFY COLUMN for type changes', function (): void {
        $generator = new MySqlGenerator();

        $oldColumn = new Column(name: 'name', type: 'string', length: 100, nullable: true);
        $newColumn = new Column(name: 'name', type: 'string', length: 255, nullable: false);

        $sql = $generator->generateModifyColumn('users', $newColumn, $oldColumn);

        expect($sql)->toBe('ALTER TABLE `users` MODIFY COLUMN `name` VARCHAR(255) NOT NULL');
    });

    it('generates CREATE INDEX statements', function (): void {
        $generator = new MySqlGenerator();

        $index = new Index(
            name: 'idx_users_email',
            columns: ['email'],
            type: IndexType::Btree,
        );

        $sql = $generator->generateAddIndex('users', $index);

        expect($sql)->toBe('CREATE INDEX `idx_users_email` ON `users` (`email`)');
    });

    it('generates CREATE UNIQUE INDEX statements', function (): void {
        $generator = new MySqlGenerator();

        $index = new Index(
            name: 'idx_users_email_unique',
            columns: ['email'],
            type: IndexType::Unique,
        );

        $sql = $generator->generateAddIndex('users', $index);

        expect($sql)->toBe('CREATE UNIQUE INDEX `idx_users_email_unique` ON `users` (`email`)');
    });

    it('generates CREATE FULLTEXT INDEX statements', function (): void {
        $generator = new MySqlGenerator();

        $index = new Index(
            name: 'idx_posts_content_fulltext',
            columns: ['title', 'content'],
            type: IndexType::Fulltext,
        );

        $sql = $generator->generateAddIndex('posts', $index);

        expect($sql)->toBe('CREATE FULLTEXT INDEX `idx_posts_content_fulltext` ON `posts` (`title`, `content`)');
    });

    it('generates DROP INDEX statements', function (): void {
        $generator = new MySqlGenerator();

        $sql = $generator->generateDropIndex('users', 'idx_users_email');

        expect($sql)->toBe('DROP INDEX `idx_users_email` ON `users`');
    });

    it('generates ALTER TABLE ADD CONSTRAINT for foreign keys', function (): void {
        $generator = new MySqlGenerator();

        $foreignKey = new ForeignKey(
            name: 'fk_posts_user_id',
            columns: ['user_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
            onDelete: 'CASCADE',
            onUpdate: 'CASCADE',
        );

        $sql = $generator->generateAddForeignKey('posts', $foreignKey);

        expect($sql)->toBe(
            'ALTER TABLE `posts` ADD CONSTRAINT `fk_posts_user_id` ' .
            'FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
        );
    });

    it('generates ALTER TABLE ADD CONSTRAINT without ON DELETE/UPDATE when null', function (): void {
        $generator = new MySqlGenerator();

        $foreignKey = new ForeignKey(
            name: 'fk_posts_user_id',
            columns: ['user_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
        );

        $sql = $generator->generateAddForeignKey('posts', $foreignKey);

        expect($sql)->toBe(
            'ALTER TABLE `posts` ADD CONSTRAINT `fk_posts_user_id` ' .
            'FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)',
        );
    });

    it('generates ALTER TABLE DROP FOREIGN KEY', function (): void {
        $generator = new MySqlGenerator();

        $sql = $generator->generateDropForeignKey('posts', 'fk_posts_user_id');

        expect($sql)->toBe('ALTER TABLE `posts` DROP FOREIGN KEY `fk_posts_user_id`');
    });

    it('maps Column types to MySQL data types', function (): void {
        $generator = new MySqlGenerator();

        // Test integer types
        $intTable = new Table(name: 't', columns: [new Column(name: 'c', type: 'integer')]);
        expect($generator->generateCreateTable($intTable))->toContain('`c` INT NOT NULL');

        $bigintTable = new Table(name: 't', columns: [new Column(name: 'c', type: 'bigint')]);
        expect($generator->generateCreateTable($bigintTable))->toContain('`c` BIGINT NOT NULL');

        $smallintTable = new Table(name: 't', columns: [new Column(name: 'c', type: 'smallint')]);
        expect($generator->generateCreateTable($smallintTable))->toContain('`c` SMALLINT NOT NULL');

        $tinyintTable = new Table(name: 't', columns: [new Column(name: 'c', type: 'tinyint')]);
        expect($generator->generateCreateTable($tinyintTable))->toContain('`c` TINYINT NOT NULL');

        // Test string types
        $stringTable = new Table(name: 't', columns: [new Column(name: 'c', type: 'string', length: 100)]);
        expect($generator->generateCreateTable($stringTable))->toContain('`c` VARCHAR(100) NOT NULL');

        $textTable = new Table(name: 't', columns: [new Column(name: 'c', type: 'text')]);
        expect($generator->generateCreateTable($textTable))->toContain('`c` TEXT NOT NULL');

        // Test boolean
        $boolTable = new Table(name: 't', columns: [new Column(name: 'c', type: 'boolean')]);
        expect($generator->generateCreateTable($boolTable))->toContain('`c` TINYINT(1) NOT NULL');

        // Test date/time types
        $datetimeTable = new Table(name: 't', columns: [new Column(name: 'c', type: 'datetime')]);
        expect($generator->generateCreateTable($datetimeTable))->toContain('`c` DATETIME NOT NULL');

        $dateTable = new Table(name: 't', columns: [new Column(name: 'c', type: 'date')]);
        expect($generator->generateCreateTable($dateTable))->toContain('`c` DATE NOT NULL');

        $timeTable = new Table(name: 't', columns: [new Column(name: 'c', type: 'time')]);
        expect($generator->generateCreateTable($timeTable))->toContain('`c` TIME NOT NULL');

        $timestampTable = new Table(name: 't', columns: [new Column(name: 'c', type: 'timestamp')]);
        expect($generator->generateCreateTable($timestampTable))->toContain('`c` TIMESTAMP NOT NULL');

        // Test decimal/float types
        $decimalTable = new Table(name: 't', columns: [new Column(name: 'c', type: 'decimal')]);
        expect($generator->generateCreateTable($decimalTable))->toContain('`c` DECIMAL(10,2) NOT NULL');

        $floatTable = new Table(name: 't', columns: [new Column(name: 'c', type: 'float')]);
        expect($generator->generateCreateTable($floatTable))->toContain('`c` FLOAT NOT NULL');

        // Test binary types
        $blobTable = new Table(name: 't', columns: [new Column(name: 'c', type: 'blob')]);
        expect($generator->generateCreateTable($blobTable))->toContain('`c` BLOB NOT NULL');

        // Test JSON
        $jsonTable = new Table(name: 't', columns: [new Column(name: 'c', type: 'json')]);
        expect($generator->generateCreateTable($jsonTable))->toContain('`c` JSON NOT NULL');
    });

    it('handles AUTO_INCREMENT for serial columns', function (): void {
        $generator = new MySqlGenerator();

        $table = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new Column(name: 'title', type: 'string', length: 255),
            ],
        );

        $sql = $generator->generateCreateTable($table);

        expect($sql)->toContain('`id` INT NOT NULL AUTO_INCREMENT');
        expect($sql)->toContain('PRIMARY KEY (`id`)');
    });

    it('generates proper DEFAULT expressions', function (): void {
        $generator = new MySqlGenerator();

        // String default
        $stringDefault = new Table(name: 't', columns: [
            new Column(name: 'status', type: 'string', length: 20, default: 'active'),
        ]);
        expect($generator->generateCreateTable($stringDefault))->toContain("DEFAULT 'active'");

        // Numeric default
        $numericDefault = new Table(name: 't', columns: [
            new Column(name: 'count', type: 'integer', default: 0),
        ]);
        expect($generator->generateCreateTable($numericDefault))->toContain('DEFAULT 0');

        // Boolean default
        $boolDefault = new Table(name: 't', columns: [
            new Column(name: 'is_active', type: 'boolean', default: true),
        ]);
        expect($generator->generateCreateTable($boolDefault))->toContain('DEFAULT 1');

        // NULL default (with nullable column)
        $nullDefault = new Table(name: 't', columns: [
            new Column(name: 'optional', type: 'string', length: 100, nullable: true, default: null),
        ]);
        $sql = $generator->generateCreateTable($nullDefault);
        expect($sql)->toContain('`optional` VARCHAR(100) NULL');

        // Expression default (CURRENT_TIMESTAMP)
        $expressionDefault = new Table(name: 't', columns: [
            new Column(name: 'created_at', type: 'datetime', default: 'CURRENT_TIMESTAMP'),
        ]);
        expect($generator->generateCreateTable($expressionDefault))->toContain('DEFAULT CURRENT_TIMESTAMP');
    });

    it('generates down SQL that reverses up SQL', function (): void {
        $generator = new MySqlGenerator();

        // Create a schema diff that creates a table
        $table = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new Column(name: 'title', type: 'string', length: 255),
            ],
            indexes: [
                new Index(name: 'idx_posts_title', columns: ['title']),
            ],
        );

        $diff = new SchemaDiff(
            tablesToCreate: [$table],
        );

        $upSql = $generator->generateUp($diff);
        $downSql = $generator->generateDown($diff);

        // Up should create table
        expect($upSql[0])->toContain('CREATE TABLE `posts`');

        // Down should drop table (reverse of create)
        expect($downSql)->toContain('DROP TABLE `posts`');
    });

    it('generates up SQL for all schema changes', function (): void {
        $generator = new MySqlGenerator();

        $newTable = new Table(
            name: 'categories',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new Column(name: 'name', type: 'string', length: 100),
            ],
        );

        $tableToDropColumns = new Table(name: 'old_table');

        $tableDiff = new TableDiff(
            tableName: 'posts',
            columnsToAdd: [new Column(name: 'category_id', type: 'integer')],
            columnsToDrop: [new Column(name: 'old_column', type: 'string')],
            indexesToAdd: [new Index(name: 'idx_posts_category', columns: ['category_id'])],
            foreignKeysToAdd: [new ForeignKey(
                name: 'fk_posts_category',
                columns: ['category_id'],
                referencedTable: 'categories',
                referencedColumns: ['id'],
            )],
        );

        $diff = new SchemaDiff(
            tablesToCreate: [$newTable],
            tablesToDrop: [$tableToDropColumns],
            tablesToAlter: ['posts' => $tableDiff],
        );

        $upSql = $generator->generateUp($diff);

        // Should contain create table
        expect(array_filter($upSql, fn ($s) => str_contains($s, 'CREATE TABLE `categories`')))->not->toBeEmpty();

        // Should contain drop table
        expect($upSql)->toContain('DROP TABLE `old_table`');

        // Should contain add column
        expect(array_filter($upSql, fn ($s) => str_contains($s, 'ADD COLUMN `category_id`')))->not->toBeEmpty();

        // Should contain drop column
        expect(array_filter($upSql, fn ($s) => str_contains($s, 'DROP COLUMN `old_column`')))->not->toBeEmpty();

        // Should contain create index
        expect(
            array_filter($upSql, fn ($s) => str_contains($s, 'CREATE INDEX `idx_posts_category`'))
        )->not->toBeEmpty();

        // Should contain add foreign key
        expect(
            array_filter($upSql, fn ($s) => str_contains($s, 'ADD CONSTRAINT `fk_posts_category`'))
        )->not->toBeEmpty();
    });

    it('generates down SQL that reverses table alterations', function (): void {
        $generator = new MySqlGenerator();

        $columnAdded = new Column(name: 'new_column', type: 'string', length: 100);
        $columnDropped = new Column(name: 'dropped_column', type: 'text');
        $indexAdded = new Index(name: 'idx_new', columns: ['new_column']);
        $indexDropped = new Index(name: 'idx_dropped', columns: ['dropped_column']);
        $fkAdded = new ForeignKey(
            name: 'fk_new',
            columns: ['ref_id'],
            referencedTable: 'refs',
            referencedColumns: ['id'],
        );
        $fkDropped = new ForeignKey(
            name: 'fk_dropped',
            columns: ['old_ref_id'],
            referencedTable: 'old_refs',
            referencedColumns: ['id'],
        );

        $tableDiff = new TableDiff(
            tableName: 'posts',
            columnsToAdd: [$columnAdded],
            columnsToDrop: [$columnDropped],
            indexesToAdd: [$indexAdded],
            indexesToDrop: [$indexDropped],
            foreignKeysToAdd: [$fkAdded],
            foreignKeysToDrop: [$fkDropped],
        );

        $diff = new SchemaDiff(
            tablesToAlter: ['posts' => $tableDiff],
        );

        $downSql = $generator->generateDown($diff);

        // Adding a column is reversed by dropping it
        expect(array_filter($downSql, fn ($s) => str_contains($s, 'DROP COLUMN `new_column`')))->not->toBeEmpty();

        // Dropping a column is reversed by adding it back
        expect(array_filter($downSql, fn ($s) => str_contains($s, 'ADD COLUMN `dropped_column`')))->not->toBeEmpty();

        // Adding an index is reversed by dropping it
        expect(array_filter($downSql, fn ($s) => str_contains($s, 'DROP INDEX `idx_new`')))->not->toBeEmpty();

        // Dropping an index is reversed by creating it
        expect(array_filter($downSql, fn ($s) => str_contains($s, 'CREATE INDEX `idx_dropped`')))->not->toBeEmpty();

        // Adding a foreign key is reversed by dropping it
        expect(array_filter($downSql, fn ($s) => str_contains($s, 'DROP FOREIGN KEY `fk_new`')))->not->toBeEmpty();

        // Dropping a foreign key is reversed by adding it back
        expect(array_filter($downSql, fn ($s) => str_contains($s, 'ADD CONSTRAINT `fk_dropped`')))->not->toBeEmpty();
    });

    it('generates multi-column index SQL', function (): void {
        $generator = new MySqlGenerator();

        $index = new Index(
            name: 'idx_posts_user_created',
            columns: ['user_id', 'created_at'],
        );

        $sql = $generator->generateAddIndex('posts', $index);

        expect($sql)->toBe('CREATE INDEX `idx_posts_user_created` ON `posts` (`user_id`, `created_at`)');
    });

    it('generates multi-column foreign key SQL', function (): void {
        $generator = new MySqlGenerator();

        $foreignKey = new ForeignKey(
            name: 'fk_order_items_composite',
            columns: ['order_id', 'product_id'],
            referencedTable: 'order_products',
            referencedColumns: ['order_id', 'product_id'],
            onDelete: 'CASCADE',
        );

        $sql = $generator->generateAddForeignKey('order_items', $foreignKey);

        expect($sql)->toBe(
            'ALTER TABLE `order_items` ADD CONSTRAINT `fk_order_items_composite` ' .
            'FOREIGN KEY (`order_id`, `product_id`) REFERENCES `order_products` (`order_id`, `product_id`) ON DELETE CASCADE',
        );
    });

    it('generates CREATE TABLE with indexes', function (): void {
        $generator = new MySqlGenerator();

        $table = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new Column(name: 'user_id', type: 'integer'),
                new Column(name: 'title', type: 'string', length: 255),
            ],
            indexes: [
                new Index(name: 'idx_posts_user_id', columns: ['user_id']),
            ],
        );

        $sql = $generator->generateCreateTable($table);

        expect($sql)->toContain('INDEX `idx_posts_user_id` (`user_id`)');
    });

    it('generates CREATE TABLE with foreign keys', function (): void {
        $generator = new MySqlGenerator();

        $table = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new Column(name: 'user_id', type: 'integer'),
            ],
            foreignKeys: [
                new ForeignKey(
                    name: 'fk_posts_user_id',
                    columns: ['user_id'],
                    referencedTable: 'users',
                    referencedColumns: ['id'],
                    onDelete: 'CASCADE',
                ),
            ],
        );

        $sql = $generator->generateCreateTable($table);

        expect($sql)->toContain(
            'CONSTRAINT `fk_posts_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
        );
    });
});
