<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Feature;

use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\TableDiff;
use Marko\Database\MySql\Sql\MySqlGenerator;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;

describe('MySQL Integration', function (): void {
    it('generates MySQL-specific CREATE TABLE syntax', function (): void {
        $generator = new MySqlGenerator();

        $table = new Table(
            name: 'users',
            columns: [
                new Column(
                    name: 'id',
                    type: 'INT',
                    primaryKey: true,
                    autoIncrement: true,
                ),
                new Column(
                    name: 'email',
                    type: 'VARCHAR',
                    length: 255,
                    unique: true,
                ),
                new Column(
                    name: 'is_active',
                    type: 'BOOLEAN',
                    default: true,
                ),
            ],
            indexes: [],
        );

        $sql = $generator->generateCreateTable($table);

        // MySQL uses backticks for identifiers
        expect($sql)->toContain('`users`');
        expect($sql)->toContain('`id`');
        expect($sql)->toContain('`email`');
        expect($sql)->toContain('AUTO_INCREMENT');
        expect($sql)->toContain('PRIMARY KEY');
    });

    it('generates MySQL-specific data types', function (): void {
        $generator = new MySqlGenerator();

        $table = new Table(
            name: 'test_types',
            columns: [
                new Column(name: 'id', type: 'INT', primaryKey: true),
                new Column(name: 'is_active', type: 'BOOLEAN'),
                new Column(name: 'data', type: 'JSON'),
                new Column(name: 'created_at', type: 'DATETIME'),
            ],
            indexes: [],
        );

        $sql = $generator->generateCreateTable($table);

        // MySQL uses TINYINT(1) for boolean
        expect($sql)->toContain('TINYINT(1)');
        // MySQL supports native JSON type
        expect($sql)->toContain('JSON');
        expect($sql)->toContain('DATETIME');
    });

    it('generates MySQL-specific ALTER TABLE statements', function (): void {
        $generator = new MySqlGenerator();

        $diff = new SchemaDiff(
            tablesToCreate: [],
            tablesToDrop: [],
            tablesToAlter: [
                'users' => new TableDiff(
                    tableName: 'users',
                    columnsToAdd: [
                        new Column(name: 'phone', type: 'VARCHAR', length: 20),
                    ],
                    columnsToDrop: [],
                    columnsToModify: [],
                    indexesToAdd: [],
                    indexesToDrop: [],
                    foreignKeysToAdd: [],
                    foreignKeysToDrop: [],
                ),
            ],
        );

        $statements = $generator->generateUp($diff);

        expect($statements)->toHaveCount(1);
        expect($statements[0])->toContain('ALTER TABLE `users`');
        expect($statements[0])->toContain('ADD COLUMN');
        expect($statements[0])->toContain('`phone`');
    });

    it('generates MySQL-specific index syntax', function (): void {
        $generator = new MySqlGenerator();

        $index = new Index(
            name: 'idx_email',
            columns: ['email'],
            type: IndexType::Unique,
        );

        $sql = $generator->generateAddIndex('users', $index);

        expect($sql)->toContain('CREATE UNIQUE INDEX');
        expect($sql)->toContain('`idx_email`');
        expect($sql)->toContain('ON `users`');
    });

    it('generates MySQL-specific foreign key syntax', function (): void {
        $generator = new MySqlGenerator();

        $foreignKey = new ForeignKey(
            name: 'fk_user_id',
            columns: ['user_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
            onDelete: 'CASCADE',
            onUpdate: 'SET NULL',
        );

        $sql = $generator->generateAddForeignKey('posts', $foreignKey);

        expect($sql)->toContain('ALTER TABLE `posts`');
        expect($sql)->toContain('ADD CONSTRAINT `fk_user_id`');
        expect($sql)->toContain('FOREIGN KEY (`user_id`)');
        expect($sql)->toContain('REFERENCES `users` (`id`)');
        expect($sql)->toContain('ON DELETE CASCADE');
        expect($sql)->toContain('ON UPDATE SET NULL');
    });

    it('generates MySQL-specific DROP FOREIGN KEY syntax', function (): void {
        $generator = new MySqlGenerator();

        $sql = $generator->generateDropForeignKey('posts', 'fk_user_id');

        // MySQL uses DROP FOREIGN KEY (not DROP CONSTRAINT)
        expect($sql)->toContain('ALTER TABLE `posts`');
        expect($sql)->toContain('DROP FOREIGN KEY `fk_user_id`');
    });

    it('generates complete migration with up and down', function (): void {
        $generator = new MySqlGenerator();

        $table = new Table(
            name: 'products',
            columns: [
                new Column(name: 'id', type: 'INT', primaryKey: true, autoIncrement: true),
                new Column(name: 'name', type: 'VARCHAR', length: 255),
                new Column(name: 'price', type: 'DECIMAL'),
                new Column(name: 'stock', type: 'INT', default: 0),
            ],
            indexes: [
                new Index(name: 'idx_name', columns: ['name'], type: IndexType::Btree),
            ],
        );

        $diff = new SchemaDiff(tablesToCreate: [$table]);

        $upStatements = $generator->generateUp($diff);
        $downStatements = $generator->generateDown($diff);

        expect($upStatements)->toHaveCount(1);
        expect($upStatements[0])->toContain('CREATE TABLE `products`');

        expect($downStatements)->toHaveCount(1);
        expect($downStatements[0])->toContain('DROP TABLE `products`');
    });

    it('generates MySQL-specific column modifications', function (): void {
        $generator = new MySqlGenerator();

        $oldColumn = new Column(name: 'status', type: 'VARCHAR', length: 20);
        $newColumn = new Column(name: 'status', type: 'VARCHAR', length: 50);

        $sql = $generator->generateModifyColumn('orders', $newColumn, $oldColumn);

        // MySQL uses MODIFY COLUMN
        expect($sql)->toContain('ALTER TABLE `orders`');
        expect($sql)->toContain('MODIFY COLUMN');
        expect($sql)->toContain('`status`');
        expect($sql)->toContain('VARCHAR(50)');
    });

    it('handles nullable columns correctly', function (): void {
        $generator = new MySqlGenerator();

        $table = new Table(
            name: 'nullable_test',
            columns: [
                new Column(name: 'id', type: 'INT', primaryKey: true),
                new Column(name: 'optional_field', type: 'VARCHAR', length: 100, nullable: true),
                new Column(name: 'required_field', type: 'VARCHAR', length: 100, nullable: false),
            ],
            indexes: [],
        );

        $sql = $generator->generateCreateTable($table);

        expect($sql)->toContain('`optional_field` VARCHAR(100) NULL');
        expect($sql)->toContain('`required_field` VARCHAR(100) NOT NULL');
    });

    it('handles default values with proper escaping', function (): void {
        $generator = new MySqlGenerator();

        $table = new Table(
            name: 'defaults_test',
            columns: [
                new Column(name: 'id', type: 'INT', primaryKey: true),
                new Column(name: 'status', type: 'VARCHAR', length: 20, default: 'pending'),
                new Column(name: 'count', type: 'INT', default: 0),
                new Column(name: 'is_active', type: 'BOOLEAN', default: true),
            ],
            indexes: [],
        );

        $sql = $generator->generateCreateTable($table);

        expect($sql)->toContain("DEFAULT 'pending'");
        expect($sql)->toContain('DEFAULT 0');
        expect($sql)->toContain('DEFAULT 1'); // MySQL uses 1 for true
    });
});
