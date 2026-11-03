<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Sql;

use Marko\Database\Attributes\Column as ColumnAttribute;
use Marko\Database\Attributes\Table as TableAttribute;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Diff\TableDiff;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\MySql\Sql\MySqlGenerator;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Expression;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Literal;
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

        expect($sql)
            ->toContain('CREATE TABLE `users`')
            ->and($sql)->toContain('`id` INT NOT NULL AUTO_INCREMENT')
            ->and($sql)->toContain('`email` VARCHAR(255) NOT NULL UNIQUE')
            ->and($sql)->toContain('`name` VARCHAR(100) NULL')
            ->and($sql)->toContain('`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP')
            ->and($sql)->toContain('PRIMARY KEY (`id`)');
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
        $newColumn = new Column(name: 'name', type: 'string', length: 255);

        $sql = $generator->generateModifyColumn('users', $newColumn, $oldColumn);

        expect($sql)->toBe('ALTER TABLE `users` MODIFY COLUMN `name` VARCHAR(255) NOT NULL');
    });

    it('throws when adding a partial index on mysql', function (): void {
        $index = new Index(name: 'shows_live_idx', columns: ['status'], where: "status = 'live'");

        expect(fn () => new MySqlGenerator()->generateAddIndex('shows', $index))
            ->toThrow(MigrationException::class);
    });

    it('throws when creating a table with a partial index on mysql', function (): void {
        $table = new Table(
            name: 'shows',
            columns: [new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true)],
            indexes: [new Index(name: 'shows_live_idx', columns: ['id'], where: 'id > 0')],
        );

        expect(fn () => new MySqlGenerator()->generateCreateTable($table))
            ->toThrow(MigrationException::class);
    });

    it('names the index and suggests an alternative in the exception', function (): void {
        $index = new Index(name: 'shows_live_idx', columns: ['status'], where: "status = 'live'");

        try {
            new MySqlGenerator()->generateAddIndex('shows', $index);
            $this->fail('Expected MigrationException');
        } catch (MigrationException $e) {
            expect($e->getMessage())->toContain('shows_live_idx')
                ->and($e->getMessage())->toContain('MySQL')
                ->and($e->getSuggestion())->toContain('unmanagedIndexes');
        }
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

    it('maps Column types to MySQL data types', function (Column $column, string $expected): void {
        $table = new Table(name: 't', columns: [$column]);

        expect(new MySqlGenerator()->generateCreateTable($table))->toContain($expected);
    })->with([
        'integer' => [new Column(name: 'c', type: 'integer'), '`c` INT NOT NULL'],
        'bigint' => [new Column(name: 'c', type: 'bigint'), '`c` BIGINT NOT NULL'],
        'smallint' => [new Column(name: 'c', type: 'smallint'), '`c` SMALLINT NOT NULL'],
        'tinyint' => [new Column(name: 'c', type: 'tinyint'), '`c` TINYINT NOT NULL'],
        'string' => [new Column(name: 'c', type: 'string', length: 100), '`c` VARCHAR(100) NOT NULL'],
        'text' => [new Column(name: 'c', type: 'text'), '`c` TEXT NOT NULL'],
        'boolean' => [new Column(name: 'c', type: 'boolean'), '`c` TINYINT(1) NOT NULL'],
        'datetime' => [new Column(name: 'c', type: 'datetime'), '`c` DATETIME NOT NULL'],
        'date' => [new Column(name: 'c', type: 'date'), '`c` DATE NOT NULL'],
        'time' => [new Column(name: 'c', type: 'time'), '`c` TIME NOT NULL'],
        'timestamp' => [new Column(name: 'c', type: 'timestamp'), '`c` TIMESTAMP NOT NULL'],
        'decimal' => [new Column(name: 'c', type: 'decimal'), '`c` DECIMAL(10,2) NOT NULL'],
        'float' => [new Column(name: 'c', type: 'float'), '`c` FLOAT NOT NULL'],
        'blob' => [new Column(name: 'c', type: 'blob'), '`c` BLOB NOT NULL'],
        'json' => [new Column(name: 'c', type: 'json'), '`c` JSON NOT NULL'],
    ]);

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

        expect($sql)
            ->toContain('`id` INT NOT NULL AUTO_INCREMENT')
            ->and($sql)->toContain('PRIMARY KEY (`id`)');
    });

    it('generates proper DEFAULT expressions', function (Column $column, string $expected): void {
        $table = new Table(name: 't', columns: [$column]);

        expect(new MySqlGenerator()->generateCreateTable($table))->toContain($expected);
    })->with([
        'string' => [new Column(name: 'status', type: 'string', length: 20, default: 'active'), "DEFAULT 'active'"],
        'numeric' => [new Column(name: 'count', type: 'integer', default: 0), 'DEFAULT 0'],
        'boolean' => [new Column(name: 'is_active', type: 'boolean', default: true), 'DEFAULT 1'],
        'null on a nullable column' => [
            new Column(name: 'optional', type: 'string', length: 100, nullable: true),
            '`optional` VARCHAR(100) NULL',
        ],
        'CURRENT_TIMESTAMP expression' => [
            new Column(name: 'created_at', type: 'datetime', default: 'CURRENT_TIMESTAMP'),
            'DEFAULT CURRENT_TIMESTAMP',
        ],
    ]);

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

        // Up should create table, down should drop table (reverse of create)
        expect($upSql[0])
            ->toContain('CREATE TABLE `posts`')
            ->and($downSql)->toContain('DROP TABLE `posts`');
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

        // Should contain: create table, drop table, add column, drop column, create index, add foreign key
        expect(array_filter($upSql, fn ($s) => str_contains($s, 'CREATE TABLE `categories`')))
            ->not->toBeEmpty()
            ->and($upSql)->toContain('DROP TABLE `old_table`')
            ->and(array_filter($upSql, fn ($s) => str_contains($s, 'ADD COLUMN `category_id`')))->not->toBeEmpty()
            ->and(array_filter($upSql, fn ($s) => str_contains($s, 'DROP COLUMN `old_column`')))->not->toBeEmpty()
            ->and(
                array_filter($upSql, fn ($s) => str_contains($s, 'CREATE INDEX `idx_posts_category`')),
            )->not->toBeEmpty()
            ->and(
                array_filter($upSql, fn ($s) => str_contains($s, 'ADD CONSTRAINT `fk_posts_category`')),
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

        // Verify all reversals: add→drop, drop→add for columns, indexes, and foreign keys
        expect(array_filter($downSql, fn ($s) => str_contains($s, 'DROP COLUMN `new_column`')))
            ->not->toBeEmpty()
            ->and(array_filter($downSql, fn ($s) => str_contains($s, 'ADD COLUMN `dropped_column`')))->not->toBeEmpty()
            ->and(array_filter($downSql, fn ($s) => str_contains($s, 'DROP INDEX `idx_new`')))->not->toBeEmpty()
            ->and(array_filter($downSql, fn ($s) => str_contains($s, 'CREATE INDEX `idx_dropped`')))->not->toBeEmpty()
            ->and(array_filter($downSql, fn ($s) => str_contains($s, 'DROP FOREIGN KEY `fk_new`')))->not->toBeEmpty()
            ->and(
                array_filter($downSql, fn ($s) => str_contains($s, 'ADD CONSTRAINT `fk_dropped`')),
            )->not->toBeEmpty();
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

    it('defaults VARCHAR to 255 when no length specified', function (): void {
        $generator = new MySqlGenerator();

        $table = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'title', type: 'string'),  // No length specified
            ],
        );

        $sql = $generator->generateCreateTable($table);

        expect($sql)->toContain('`title` VARCHAR(255) NOT NULL');
    });

    it('forces NOT NULL for primary key columns even when marked nullable', function (): void {
        $generator = new MySqlGenerator();

        // Simulates entity with ?int $id = null (nullable in PHP but must be NOT NULL in DB)
        $table = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true, nullable: true),
                new Column(name: 'title', type: 'string', length: 255),
            ],
        );

        $sql = $generator->generateCreateTable($table);

        expect($sql)->toContain('`id` INT NOT NULL AUTO_INCREMENT');
    });

    it('forces NOT NULL for auto-increment columns even when marked nullable', function (): void {
        $generator = new MySqlGenerator();

        $table = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'id', type: 'integer', autoIncrement: true, nullable: true),
            ],
        );

        $sql = $generator->generateCreateTable($table);

        expect($sql)->toContain('`id` INT NOT NULL AUTO_INCREMENT');
    });

    it('generates a valid MySQL type for a uuid column', function (): void {
        $generator = new MySqlGenerator();

        $table = new Table(
            name: 't',
            columns: [new Column(name: 'id', type: 'uuid')],
        );

        $sql = $generator->generateCreateTable($table);

        expect($sql)->toContain('`id` CHAR(36) NOT NULL');
    });

    it('generates a valid MySQL type for an enum column', function (): void {
        $generator = new MySqlGenerator();

        $table = new Table(
            name: 't',
            columns: [new Column(name: 'status', type: 'enum', length: 50)],
        );

        $sql = $generator->generateCreateTable($table);

        expect($sql)->toContain('`status` VARCHAR(50) NOT NULL');
    });

    it('generates DECIMAL with the shared precision for a decimal column', function (): void {
        $generator = new MySqlGenerator();

        $table = new Table(
            name: 't',
            columns: [new Column(name: 'price', type: 'decimal')],
        );

        $sql = $generator->generateCreateTable($table);

        expect($sql)->toContain('`price` DECIMAL(10,2) NOT NULL');
    });

    it('emits MySQL JSON DDL type for #[Column(type: \'json\')]', function (): void {
        $generator = new MySqlGenerator();

        $table = new Table(
            name: 'products',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new Column(name: 'metadata', type: 'json'),
            ],
        );

        $sql = $generator->generateCreateTable($table);

        expect($sql)->toContain('`metadata` JSON NOT NULL');
    });

    it('generates an up MODIFY COLUMN from the new column definition', function (): void {
        $statements = new MySqlGenerator()->generateUp(mysqlModifyDiff(
            new Column(name: 'views', type: 'bigint', nullable: true, default: 0),
            new Column(name: 'views', type: 'integer'),
        ));

        expect($statements)->toBe(['ALTER TABLE `posts` MODIFY COLUMN `views` BIGINT NULL DEFAULT 0']);
    });

    it('restores the old type, nullability and default of a modified column in a down migration', function (): void {
        $statements = new MySqlGenerator()->generateDown(mysqlModifyDiff(
            new Column(name: 'status', type: 'text', nullable: true),
            new Column(name: 'status', type: 'string', length: 20, default: 'draft'),
        ));

        expect($statements)->toBe(["ALTER TABLE `posts` MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'draft'"]);
    });

    it('restores a CURRENT_TIMESTAMP default unquoted in a down migration', function (): void {
        $statements = new MySqlGenerator()->generateDown(mysqlModifyDiff(
            new Column(name: 'created_at', type: 'timestamp', nullable: true),
            new Column(name: 'created_at', type: 'timestamp', default: 'CURRENT_TIMESTAMP'),
        ));

        expect($statements)->toBe([
            'ALTER TABLE `posts` MODIFY COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ]);
    });

    it('restores modified columns before re-adding dropped foreign keys in a down migration', function (): void {
        $foreignKey = new ForeignKey(
            name: 'posts_author_id_foreign',
            columns: ['author_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
        );
        $diff = new SchemaDiff(tablesToAlter: [
            'posts' => new TableDiff(
                tableName: 'posts',
                columnsToModify: ['author_id' => new Column(name: 'author_id', type: 'bigint')],
                foreignKeysToDrop: [$foreignKey],
                columnsToModifyFrom: ['author_id' => new Column(name: 'author_id', type: 'integer')],
            ),
        ]);

        $statements = new MySqlGenerator()->generateDown($diff);

        expect($statements)->toHaveCount(2)
            ->and($statements[0])->toBe('ALTER TABLE `posts` MODIFY COLUMN `author_id` INT NOT NULL')
            ->and($statements[1])->toContain('ADD CONSTRAINT `posts_author_id_foreign`');
    });

    it('throws a MigrationException naming the column when the diff lacks the previous column', function (): void {
        $diff = new SchemaDiff(tablesToAlter: [
            'posts' => new TableDiff(
                tableName: 'posts',
                columnsToModify: ['status' => new Column(name: 'status', type: 'string', default: 'live')],
            ),
        ]);

        expect(fn () => new MySqlGenerator()->generateDown($diff))->toThrow(
            MigrationException::class,
            "Column 'posts.status' is modified, but the diff holds no previous definition for it",
        )->and(fn () => new MySqlGenerator()->generateUp($diff))->toThrow(
            MigrationException::class,
            "Column 'posts.status' is modified, but the diff holds no previous definition for it",
        );
    });

    describe('MODIFY COLUMN fidelity', function (): void {
        it('keeps the existing VARCHAR length when the entity declares none', function (): void {
            $statements = new MySqlGenerator()->generateUp(mysqlModifyDiff(
                new Column(name: 'title', type: 'varchar', nullable: true),
                new Column(name: 'title', type: 'VARCHAR', length: 500, nativeType: 'varchar(500)'),
            ));

            expect($statements)->toBe(['ALTER TABLE `posts` MODIFY COLUMN `title` varchar(500) NULL']);
        });

        it('keeps the existing default when the entity declares none', function (): void {
            $statements = new MySqlGenerator()->generateUp(mysqlModifyDiff(
                new Column(name: 'status', type: 'varchar', length: 20, nullable: true),
                new Column(name: 'status', type: 'VARCHAR', length: 20, default: 'draft'),
            ));

            expect($statements)->toBe(["ALTER TABLE `posts` MODIFY COLUMN `status` VARCHAR(20) NULL DEFAULT 'draft'"]);
        });

        it('emits no MODIFY COLUMN when the target matches the previous column', function (): void {
            $diff = mysqlModifyDiff(
                new Column(name: 'views', type: 'integer'),
                new Column(name: 'views', type: 'INT', nativeType: 'int unsigned', default: '0'),
            );

            expect(new MySqlGenerator()->generateUp($diff))->toBeEmpty()
                ->and(new MySqlGenerator()->generateDown($diff))->toBeEmpty();
        });

        it('emits no MODIFY COLUMN when only the uniqueness differs', function (): void {
            $diff = mysqlModifyDiff(
                new Column(name: 'email', type: 'varchar', length: 100, unique: true),
                new Column(name: 'email', type: 'VARCHAR', length: 100),
            );

            expect(new MySqlGenerator()->generateUp($diff))->toBeEmpty();
        });

        it('omits inline UNIQUE from MODIFY COLUMN', function (): void {
            $statements = new MySqlGenerator()->generateUp(mysqlModifyDiff(
                new Column(name: 'email', type: 'varchar', length: 100, nullable: true, unique: true),
                new Column(name: 'email', type: 'VARCHAR', length: 100, unique: true),
            ));

            expect($statements)->toBe(['ALTER TABLE `posts` MODIFY COLUMN `email` VARCHAR(100) NULL']);
        });

        it('keeps DECIMAL precision and UNSIGNED when the entity does not redefine the type', function (): void {
            $statements = new MySqlGenerator()->generateUp(mysqlModifyDiff(
                new Column(name: 'price', type: 'decimal', nullable: true),
                new Column(name: 'price', type: 'DECIMAL', nativeType: 'decimal(12,4) unsigned'),
            ));

            expect($statements)->toBe(['ALTER TABLE `posts` MODIFY COLUMN `price` decimal(12,4) unsigned NULL']);
        });

        it('keeps the collation and ON UPDATE when the entity does not redefine the type', function (): void {
            $statements = new MySqlGenerator()->generateUp(mysqlModifyDiff(
                new Column(name: 'updated_at', type: 'timestamp', nullable: true),
                new Column(
                    name: 'updated_at',
                    type: 'TIMESTAMP',
                    default: 'CURRENT_TIMESTAMP',
                    nativeType: 'timestamp',
                    onUpdateExpression: 'CURRENT_TIMESTAMP',
                ),
            ));

            expect($statements)->toBe([
                'ALTER TABLE `posts` MODIFY COLUMN `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP '
                . 'ON UPDATE CURRENT_TIMESTAMP',
            ]);
        });

        it('emits the entity type and keeps the collation when a string column changes length', function (): void {
            $statements = new MySqlGenerator()->generateUp(mysqlModifyDiff(
                new Column(name: 'code', type: 'varchar', length: 64),
                new Column(
                    name: 'code',
                    type: 'VARCHAR',
                    length: 32,
                    nativeType: 'varchar(32)',
                    collation: 'utf8mb4_bin',
                ),
            ));

            expect($statements)->toBe([
                'ALTER TABLE `posts` MODIFY COLUMN `code` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL',
            ]);
        });

        it('drops the collation when a string column becomes non-string', function (): void {
            $statements = new MySqlGenerator()->generateUp(mysqlModifyDiff(
                new Column(name: 'code', type: 'integer'),
                new Column(
                    name: 'code',
                    type: 'VARCHAR',
                    length: 32,
                    nativeType: 'varchar(32)',
                    collation: 'utf8mb4_bin',
                ),
            ));

            expect($statements)->toBe(['ALTER TABLE `posts` MODIFY COLUMN `code` INT NOT NULL']);
        });

        it('emits the entity type when the entity changes an unsigned integer to bigint', function (): void {
            $statements = new MySqlGenerator()->generateUp(mysqlModifyDiff(
                new Column(name: 'author_id', type: 'bigint'),
                new Column(name: 'author_id', type: 'INT', nativeType: 'int unsigned'),
            ));

            expect($statements)->toBe(['ALTER TABLE `posts` MODIFY COLUMN `author_id` BIGINT NOT NULL']);
        });

        it(
            'restores DECIMAL precision, UNSIGNED, collation and ON UPDATE in a down migration',
            function (): void {
                $diff = new SchemaDiff(tablesToAlter: [
                    'posts' => new TableDiff(
                        tableName: 'posts',
                        columnsToModify: [
                            'price' => new Column(name: 'price', type: 'bigint'),
                            'code' => new Column(name: 'code', type: 'text'),
                            'updated_at' => new Column(name: 'updated_at', type: 'date'),
                        ],
                        columnsToModifyFrom: [
                            'price' => new Column(
                                name: 'price',
                                type: 'DECIMAL',
                                nativeType: 'decimal(12,4) unsigned',
                            ),
                            'code' => new Column(
                                name: 'code',
                                type: 'VARCHAR',
                                length: 32,
                                nativeType: 'varchar(32)',
                                collation: 'utf8mb4_bin',
                            ),
                            'updated_at' => new Column(
                                name: 'updated_at',
                                type: 'TIMESTAMP',
                                nullable: true,
                                default: 'CURRENT_TIMESTAMP',
                                nativeType: 'timestamp',
                                onUpdateExpression: 'CURRENT_TIMESTAMP',
                            ),
                        ],
                    ),
                ]);

                expect(new MySqlGenerator()->generateDown($diff))->toBe([
                    'ALTER TABLE `posts` MODIFY COLUMN `price` decimal(12,4) unsigned NOT NULL',
                    'ALTER TABLE `posts` MODIFY COLUMN `code` varchar(32) COLLATE utf8mb4_bin NOT NULL',
                    'ALTER TABLE `posts` MODIFY COLUMN `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP '
                    . 'ON UPDATE CURRENT_TIMESTAMP',
                ]);
            },
        );

        it('restores a native enum and binary column in a down migration', function (): void {
            $diff = new SchemaDiff(tablesToAlter: [
                'posts' => new TableDiff(
                    tableName: 'posts',
                    columnsToModify: [
                        'status' => new Column(name: 'status', type: 'text'),
                        'hash' => new Column(name: 'hash', type: 'text'),
                    ],
                    columnsToModifyFrom: [
                        'status' => new Column(name: 'status', type: 'ENUM', nativeType: "enum('a','b')"),
                        'hash' => new Column(name: 'hash', type: 'BINARY', length: 16, nativeType: 'binary(16)'),
                    ],
                ),
            ]);

            expect(new MySqlGenerator()->generateDown($diff))->toBe([
                "ALTER TABLE `posts` MODIFY COLUMN `status` enum('a','b') NOT NULL",
                'ALTER TABLE `posts` MODIFY COLUMN `hash` binary(16) NOT NULL',
            ]);
        });

        it('does not inherit a TEXT length when a column becomes a string without a length', function (): void {
            $statements = new MySqlGenerator()->generateUp(mysqlModifyDiff(
                new Column(name: 'body', type: 'varchar'),
                new Column(name: 'body', type: 'TEXT', length: 65535, nativeType: 'text'),
            ));

            expect($statements)->toBe(['ALTER TABLE `posts` MODIFY COLUMN `body` VARCHAR(255) NOT NULL']);
        });

        it('drops a kept default the new type cannot hold', function (): void {
            $diff = mysqlModifyDiff(
                new Column(name: 'title', type: 'text'),
                new Column(
                    name: 'title',
                    type: 'VARCHAR',
                    length: 500,
                    default: 'untitled',
                    nativeType: 'varchar(500)',
                ),
            );

            expect(new MySqlGenerator()->generateUp($diff))
                ->toBe(['ALTER TABLE `posts` MODIFY COLUMN `title` TEXT NOT NULL'])
                ->and(new MySqlGenerator()->generateDown($diff))
                ->toBe(["ALTER TABLE `posts` MODIFY COLUMN `title` varchar(500) NOT NULL DEFAULT 'untitled'"]);
        });

        it('keeps inline UNIQUE in CREATE TABLE and ADD COLUMN', function (): void {
            $column = new Column(name: 'email', type: 'varchar', length: 100, unique: true);

            expect(new MySqlGenerator()->generateAddColumn('posts', $column))
                ->toBe('ALTER TABLE `posts` ADD COLUMN `email` VARCHAR(100) NOT NULL UNIQUE')
                ->and(new MySqlGenerator()->generateCreateTable(new Table(name: 'posts', columns: [$column])))
                ->toContain('`email` VARCHAR(100) NOT NULL UNIQUE');
        });

        it('keeps a native timestamp when the entity declares datetime', function (): void {
            $statements = new MySqlGenerator()->generateUp(mysqlModifyDiff(
                new Column(name: 'seen_at', type: 'datetime', nullable: true),
                new Column(name: 'seen_at', type: 'TIMESTAMP', nativeType: 'timestamp(3)'),
            ));

            expect($statements)->toBe(['ALTER TABLE `posts` MODIFY COLUMN `seen_at` timestamp(3) NULL']);
        });

        it('keeps a native ENUM when the entity declares an enum column', function (): void {
            $statements = new MySqlGenerator()->generateUp(mysqlModifyDiff(
                new Column(name: 'status', type: 'enum', nullable: true),
                new Column(name: 'status', type: 'ENUM', nativeType: "enum('draft','live')"),
            ));

            expect($statements)->toBe(["ALTER TABLE `posts` MODIFY COLUMN `status` enum('draft','live') NULL"]);
        });
    });
    it('adds the replacement index before dropping the unique index it replaces', function (): void {
        $diff = new SchemaDiff(tablesToAlter: ['users' => new TableDiff(
            tableName: 'users',
            indexesToAdd: [new Index(name: 'users_team_id_index', columns: ['team_id'])],
            indexesToDrop: [new Index(name: 'team_id', columns: ['team_id'], type: IndexType::Unique)],
        )]);

        expect(new MySqlGenerator()->generateUp($diff))->toBe([
            'CREATE INDEX `users_team_id_index` ON `users` (`team_id`)',
            'DROP INDEX `team_id` ON `users`',
        ]);
    });

    it('restores the unique index before dropping the replacement index in down', function (): void {
        $diff = new SchemaDiff(tablesToAlter: ['users' => new TableDiff(
            tableName: 'users',
            indexesToAdd: [new Index(name: 'users_team_id_index', columns: ['team_id'])],
            indexesToDrop: [new Index(name: 'team_id', columns: ['team_id'], type: IndexType::Unique)],
        )]);

        expect(new MySqlGenerator()->generateDown($diff))->toBe([
            'CREATE UNIQUE INDEX `team_id` ON `users` (`team_id`)',
            'DROP INDEX `users_team_id_index` ON `users`',
        ]);
    });

    it('keeps dropping indexes before dropping columns and adding indexes after adding columns', function (): void {
        $diff = new SchemaDiff(tablesToAlter: ['users' => new TableDiff(
            tableName: 'users',
            columnsToAdd: [new Column(name: 'slug', type: 'varchar')],
            columnsToDrop: [new Column(name: 'legacy', type: 'varchar')],
            indexesToAdd: [new Index(name: 'users_slug_index', columns: ['slug'])],
            indexesToDrop: [new Index(name: 'users_legacy_index', columns: ['legacy'])],
        )]);

        expect(new MySqlGenerator()->generateUp($diff))->toBe([
            'DROP INDEX `users_legacy_index` ON `users`',
            'ALTER TABLE `users` DROP COLUMN `legacy`',
            'ALTER TABLE `users` ADD COLUMN `slug` VARCHAR(255) NOT NULL',
            'CREATE INDEX `users_slug_index` ON `users` (`slug`)',
        ]);
    });
});

describe('MySqlGenerator expression defaults', function (): void {
    beforeEach(function (): void {
        $this->generator = new MySqlGenerator();
    });

    it('emits a parenthesized UUID() expression default', function (): void {
        $sql = $this->generator->generateAddColumn(
            'posts',
            new Column(name: 'ref', type: 'uuid', default: new Expression('(UUID())')),
        );

        expect($sql)->toBe('ALTER TABLE `posts` ADD COLUMN `ref` CHAR(36) NOT NULL DEFAULT (UUID())');
    });

    it('emits CURRENT_TIMESTAMP(6) unquoted and unwrapped', function (): void {
        $sql = $this->generator->generateAddColumn(
            'posts',
            new Column(name: 'created_at', type: 'datetime', default: 'CURRENT_TIMESTAMP(6)'),
        );

        expect($sql)->toBe(
            'ALTER TABLE `posts` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(6)',
        );
    });

    it('wraps a function call shortcut default in parentheses', function (): void {
        $shortcut = $this->generator->generateAddColumn(
            'posts',
            new Column(name: 'ref', type: 'uuid', default: 'UUID()'),
        );
        $expression = $this->generator->generateAddColumn(
            'posts',
            new Column(name: 'tags', type: 'json', default: new Expression('JSON_ARRAY()')),
        );

        expect($shortcut)->toBe('ALTER TABLE `posts` ADD COLUMN `ref` CHAR(36) NOT NULL DEFAULT (UUID())')
            ->and($expression)->toBe('ALTER TABLE `posts` ADD COLUMN `tags` JSON NOT NULL DEFAULT (JSON_ARRAY())');
    });

    it('quotes a literal default that looks like a function', function (): void {
        $sql = $this->generator->generateAddColumn(
            'posts',
            new Column(name: 'label', type: 'varchar', default: new Literal('UUID()')),
        );

        expect($sql)->toBe("ALTER TABLE `posts` ADD COLUMN `label` VARCHAR(255) NOT NULL DEFAULT 'UUID()'");
    });

    it('quotes a string that merely starts with a keyword', function (): void {
        $nullish = $this->generator->generateAddColumn(
            'posts',
            new Column(name: 'mode', type: 'varchar', default: 'Nullify'),
        );
        $nowish = $this->generator->generateAddColumn(
            'posts',
            new Column(name: 'label', type: 'varchar', default: 'NOW() or later'),
        );

        expect($nullish)->toBe("ALTER TABLE `posts` ADD COLUMN `mode` VARCHAR(255) NOT NULL DEFAULT 'Nullify'")
            ->and($nowish)->toBe(
                "ALTER TABLE `posts` ADD COLUMN `label` VARCHAR(255) NOT NULL DEFAULT 'NOW() or later'",
            );
    });

    it('keeps an expression default when the type changes to one that cannot hold a literal', function (): void {
        $statements = $this->generator->generateUp(mysqlModifyDiff(
            new Column(name: 'ref', type: 'text'),
            new Column(name: 'ref', type: 'varchar', length: 36, default: new Expression('uuid()')),
        ));

        expect($statements)->toBe(['ALTER TABLE `posts` MODIFY COLUMN `ref` TEXT NOT NULL DEFAULT (uuid())']);
    });
});

describe('MySqlGenerator derived names over 63 bytes', function (): void {
    beforeEach(function (): void {
        $this->statements = new MySqlGenerator()->generateUp(mysqlLongNameDiff());
    });

    it('creates the unique index of a long table and column under its shortened name', function (): void {
        $statement = array_find(
            $this->statements,
            fn (string $sql): bool => str_starts_with($sql, 'CREATE UNIQUE INDEX'),
        );

        expect($statement)->toContain('customer_subscription_event_ledger_external_bil_4456c35c_unique');
    });

    it('adds a long foreign key under its shortened name', function (): void {
        $statement = array_find($this->statements, fn (string $sql): bool => str_contains($sql, 'ADD CONSTRAINT'));

        expect($statement)->toContain('fk_customer_subscription_event_ledger_external_billing_f7ee0a8b');
    });
});

/**
 * The diff that makes an existing column of a long-named table unique and a foreign key, so both derived names are
 * over 63 bytes before shortening.
 */
function mysqlLongNameDiff(): SchemaDiff
{
    $entityTable = new SchemaBuilder()->build(new EntityMetadataFactory()->parse(
        (new #[TableAttribute('customer_subscription_event_ledger')]
        class () extends Entity
        {
            #[ColumnAttribute(primaryKey: true, autoIncrement: true)]
            public int $id;

            #[ColumnAttribute(unique: true, references: 'billing_accounts.id')]
            public int $externalBillingReferenceId;
        })::class,
    ));
    $databaseTable = new Table(
        name: 'customer_subscription_event_ledger',
        columns: [
            new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
            new Column(name: 'external_billing_reference_id', type: 'integer'),
        ],
    );

    return new DiffCalculator()->calculate(
        [$entityTable->name => $entityTable],
        [$databaseTable->name => $databaseTable],
    );
}

/**
 * A schema diff that modifies one column of the posts table.
 */
function mysqlModifyDiff(
    Column $column,
    Column $previous,
): SchemaDiff {
    return new SchemaDiff(tablesToAlter: [
        'posts' => new TableDiff(
            tableName: 'posts',
            columnsToModify: [$column->name => $column],
            columnsToModifyFrom: [$column->name => $previous],
        ),
    ]);
}

describe('MySqlGenerator identifier quoting', function (): void {
    beforeEach(function (): void {
        $this->generator = new MySqlGenerator();
    });

    it('escapes a backtick in a table name in CREATE TABLE', function (): void {
        $sql = $this->generator->generateCreateTable(new Table(
            name: 'we`ird',
            columns: [new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true)],
        ));

        expect($sql)->toContain('CREATE TABLE `we``ird`')
            ->and($this->generator->generateDropTable('we`ird'))->toBe('DROP TABLE `we``ird`');
    });

    it('escapes a backtick in a column name in ADD COLUMN', function (): void {
        $sql = $this->generator->generateAddColumn('posts', new Column(name: 'ti`tle', type: 'string'));

        expect($sql)->toStartWith('ALTER TABLE `posts` ADD COLUMN `ti``tle` VARCHAR(255)')
            ->and($this->generator->generateDropColumn('posts', 'ti`tle'))
            ->toBe('ALTER TABLE `posts` DROP COLUMN `ti``tle`');
    });

    it('escapes a backtick in index, foreign key and referenced names', function (): void {
        $index = $this->generator->generateAddIndex('posts', new Index(name: 'idx`a', columns: ['col`a']));
        $foreignKey = $this->generator->generateAddForeignKey('posts', new ForeignKey(
            name: 'fk`a',
            columns: ['user`id'],
            referencedTable: 'us`ers',
            referencedColumns: ['i`d'],
        ));

        expect($index)->toBe('CREATE INDEX `idx``a` ON `posts` (`col``a`)')
            ->and($foreignKey)->toBe(
                'ALTER TABLE `posts` ADD CONSTRAINT `fk``a` FOREIGN KEY (`user``id`) REFERENCES `us``ers` (`i``d`)',
            )
            ->and($this->generator->generateDropIndex('posts', 'idx`a'))->toBe('DROP INDEX `idx``a` ON `posts`')
            ->and($this->generator->generateDropForeignKey('posts', 'fk`a'))
            ->toBe('ALTER TABLE `posts` DROP FOREIGN KEY `fk``a`');
    });

    it('quotes reserved-word columns in generated DDL', function (): void {
        $sql = $this->generator->generateCreateTable(new Table(
            name: 'permissions',
            columns: [
                new Column(name: 'key', type: 'string'),
                new Column(name: 'group', type: 'string'),
                new Column(name: 'order', type: 'integer'),
            ],
        ));

        expect($sql)->toContain('`key` VARCHAR(255) NOT NULL')
            ->and($sql)->toContain('`group` VARCHAR(255) NOT NULL')
            ->and($sql)->toContain('`order` INT NOT NULL');
    });

    it('has no inline backtick identifier quoting in the generator, query builder or introspector', function (): void {
        $source = dirname(__DIR__, 2) . '/src';

        foreach (['Sql/MySqlGenerator.php', 'Query/MySqlQueryBuilder.php', 'Introspection/MySqlIntrospector.php'] as $file) {
            $code = (string) file_get_contents("$source/$file");

            // A backtick concatenated onto a name, a backtick-wrapped %s placeholder or a backtick-wrapped interpolation
            expect(preg_match('/`\'\s*\.|\.\s*\'`|`%s`|`\$/', $code))->toBe(0, "$file quotes an identifier inline");
        }
    });
});

describe('MySqlGenerator primary key columns on existing tables', function (): void {
    beforeEach(function (): void {
        $this->generator = new MySqlGenerator();
        $this->up = fn (TableDiff $tableDiff): array => $this->generator->generateUp(
            new SchemaDiff(tablesToAlter: [$tableDiff->tableName => $tableDiff]),
        );
        $this->down = fn (TableDiff $tableDiff): array => $this->generator->generateDown(
            new SchemaDiff(tablesToAlter: [$tableDiff->tableName => $tableDiff]),
        );
        $this->serialId = new Column(name: 'id', type: 'int', primaryKey: true, autoIncrement: true);
    });

    it('adds an auto-increment primary key column and its key in one statement', function (): void {
        $statements = ($this->up)(new TableDiff(tableName: 'admin_user_roles', columnsToAdd: [$this->serialId]));

        expect($statements)->toBe([
            'ALTER TABLE `admin_user_roles` ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)',
        ]);
    });

    it('adds the key with a primary key column passed to generateAddColumn', function (): void {
        expect($this->generator->generateAddColumn('admin_user_roles', $this->serialId))
            ->toBe(
                'ALTER TABLE `admin_user_roles` ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)',
            );
    });

    it('adds every column of a composite primary key and the key in one statement', function (): void {
        $statements = ($this->up)(new TableDiff(tableName: 'post_tags', columnsToAdd: [
            new Column(name: 'post_id', type: 'int', primaryKey: true),
            new Column(name: 'tag_id', type: 'int', primaryKey: true),
        ]));

        expect($statements)->toBe([
            'ALTER TABLE `post_tags` ADD COLUMN `post_id` INT NOT NULL, ADD COLUMN `tag_id` INT NOT NULL, '
            . 'ADD PRIMARY KEY (`post_id`, `tag_id`)',
        ]);
    });

    it('adds a non-auto-increment primary key column and its key in one statement', function (): void {
        $statements = ($this->up)(new TableDiff(tableName: 'tokens', columnsToAdd: [
            new Column(name: 'id', type: 'uuid', default: new Expression('UUID()'), primaryKey: true),
        ]));

        expect($statements)->toBe([
            'ALTER TABLE `tokens` ADD COLUMN `id` CHAR(36) NOT NULL DEFAULT (UUID()), ADD PRIMARY KEY (`id`)',
        ]);
    });

    it('keeps added columns that are not part of the key in their own statements', function (): void {
        $statements = ($this->up)(new TableDiff(tableName: 'posts', columnsToAdd: [
            new Column(name: 'title', type: 'string', length: 100),
            $this->serialId,
            new Column(name: 'body', type: 'text', nullable: true),
        ]));

        expect($statements)->toBe([
            'ALTER TABLE `posts` ADD COLUMN `title` VARCHAR(100) NOT NULL',
            'ALTER TABLE `posts` ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)',
            'ALTER TABLE `posts` ADD COLUMN `body` TEXT NULL',
        ]);
    });

    it('throws when a primary key column is added to a table that already has a primary key', function (): void {
        $tableDiff = new TableDiff(
            tableName: 'post_tags',
            columnsToAdd: [$this->serialId],
            currentPrimaryKey: ['post_id', 'tag_id'],
        );

        expect(fn () => ($this->up)($tableDiff))->toThrow(
            MigrationException::class,
            "Cannot add primary key column 'id' to table 'post_tags', which already has a primary key on "
            . "'post_id', 'tag_id'",
        );
    });

    it('adds a column that is not a key to a table that has a primary key', function (): void {
        $statements = ($this->up)(new TableDiff(
            tableName: 'posts',
            columnsToAdd: [new Column(name: 'body', type: 'text', nullable: true)],
            currentPrimaryKey: ['id'],
        ));

        expect($statements)->toBe(['ALTER TABLE `posts` ADD COLUMN `body` TEXT NULL']);
    });

    it('throws when a modified column only changes its primary key', function (): void {
        $tableDiff = new TableDiff(
            tableName: 'tokens',
            columnsToModify: ['id' => new Column(name: 'id', type: 'uuid', primaryKey: true)],
            columnsToModifyFrom: ['id' => new Column(name: 'id', type: 'uuid')],
        );

        expect(fn () => ($this->up)($tableDiff))->toThrow(
            MigrationException::class,
            "Cannot change the primary key of column 'tokens.id' in place on MySQL",
        );
    });

    it('throws when a modified column changes its primary key along with its type', function (): void {
        $tableDiff = new TableDiff(
            tableName: 'tokens',
            columnsToModify: ['code' => new Column(name: 'code', type: 'string', length: 64, primaryKey: true)],
            columnsToModifyFrom: ['code' => new Column(name: 'code', type: 'string', length: 32)],
        );

        expect(fn () => ($this->up)($tableDiff))->toThrow(
            MigrationException::class,
            "Cannot change the primary key of column 'tokens.code' in place on MySQL",
        );
    });

    it('throws when a primary key column is added while the current key columns are dropped', function (): void {
        $tableDiff = new TableDiff(
            tableName: 'tokens',
            columnsToAdd: [$this->serialId],
            columnsToDrop: [new Column(name: 'code', type: 'string', primaryKey: true)],
            currentPrimaryKey: ['code'],
        );

        expect(fn () => ($this->up)($tableDiff))->toThrow(
            MigrationException::class,
            "Cannot add primary key column 'id' to table 'tokens', which already has a primary key on 'code'",
        );
    });

    it('throws when a diff drops part of a composite primary key', function (): void {
        $tableDiff = new TableDiff(
            tableName: 'post_tags',
            columnsToDrop: [new Column(name: 'tag_id', type: 'int', primaryKey: true)],
            currentPrimaryKey: ['post_id', 'tag_id'],
        );

        expect(fn () => ($this->up)($tableDiff))->toThrow(
            MigrationException::class,
            "Cannot change the primary key of column 'post_tags.tag_id' in place on MySQL",
        );
    });

    it('drops an added primary key column in down', function (): void {
        $statements = ($this->down)(new TableDiff(tableName: 'admin_user_roles', columnsToAdd: [$this->serialId]));

        expect($statements)->toBe(['ALTER TABLE `admin_user_roles` DROP COLUMN `id`']);
    });

    it('restores a dropped primary key column with its key in down', function (): void {
        $statements = ($this->down)(new TableDiff(tableName: 'admin_user_roles', columnsToDrop: [$this->serialId]));

        expect($statements)->toBe([
            'ALTER TABLE `admin_user_roles` ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)',
        ]);
    });
});

describe('MySqlGenerator with a current table', function (): void {
    it('leaves pgsql and mysql generator output unchanged', function (): void {
        $generator = new MySqlGenerator();
        $columnsToAdd = [new Column(name: 'slug', type: 'string', length: 100)];
        $indexesToAdd = [new Index(name: 'idx_slug', columns: ['slug'])];
        $current = new Table(name: 'posts', columns: [new Column(name: 'id', type: 'integer', primaryKey: true)]);

        $without = new SchemaDiff(tablesToAlter: ['posts' => new TableDiff(
            tableName: 'posts',
            columnsToAdd: $columnsToAdd,
            indexesToAdd: $indexesToAdd,
        )]);
        $with = new SchemaDiff(tablesToAlter: [
            'posts' => new TableDiff(
                tableName: 'posts',
                columnsToAdd: $columnsToAdd,
                indexesToAdd: $indexesToAdd,
                currentTable: $current,
            ),
        ]);

        expect($generator->generateUp($with))->toBe($generator->generateUp($without))
            ->and($generator->generateDown($with))->toBe($generator->generateDown($without));
    });
});
