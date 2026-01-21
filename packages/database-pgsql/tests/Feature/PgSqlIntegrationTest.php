<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Feature;

use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\TableDiff;
use Marko\Database\PgSql\Sql\PgSqlGenerator;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;

describe('PostgreSQL Integration', function (): void {
    it('generates PostgreSQL-specific CREATE TABLE syntax', function (): void {
        $generator = new PgSqlGenerator();

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

        // PostgreSQL uses double quotes for identifiers
        expect($sql)->toContain('"users"');
        expect($sql)->toContain('"id"');
        expect($sql)->toContain('"email"');
        // PostgreSQL uses SERIAL for auto-increment
        expect($sql)->toContain('SERIAL');
        expect($sql)->toContain('PRIMARY KEY');
    });

    it('generates PostgreSQL-specific data types', function (): void {
        $generator = new PgSqlGenerator();

        // Use lowercase types to trigger the type mapping
        $table = new Table(
            name: 'test_types',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true),
                new Column(name: 'is_active', type: 'boolean'),
                new Column(name: 'data', type: 'json'),
                new Column(name: 'created_at', type: 'datetime'),
                new Column(name: 'content', type: 'binary'),
            ],
            indexes: [],
        );

        $sql = $generator->generateCreateTable($table);

        // PostgreSQL uses native BOOLEAN
        expect($sql)->toContain('BOOLEAN');
        // PostgreSQL uses JSONB for JSON (mapped from lowercase 'json')
        expect($sql)->toContain('JSONB');
        // PostgreSQL uses TIMESTAMP for DATETIME
        expect($sql)->toContain('TIMESTAMP');
        // PostgreSQL uses BYTEA for binary
        expect($sql)->toContain('BYTEA');
    });

    it('generates PostgreSQL-specific SERIAL types for auto-increment', function (): void {
        $generator = new PgSqlGenerator();

        // INT -> SERIAL
        $table1 = new Table(
            name: 'test1',
            columns: [
                new Column(name: 'id', type: 'INT', autoIncrement: true, primaryKey: true),
            ],
            indexes: [],
        );

        $sql1 = $generator->generateCreateTable($table1);
        expect($sql1)->toContain('SERIAL');

        // BIGINT -> BIGSERIAL
        $table2 = new Table(
            name: 'test2',
            columns: [
                new Column(name: 'id', type: 'bigint', autoIncrement: true, primaryKey: true),
            ],
            indexes: [],
        );

        $sql2 = $generator->generateCreateTable($table2);
        expect($sql2)->toContain('BIGSERIAL');
    });

    it('generates PostgreSQL-specific ALTER TABLE statements', function (): void {
        $generator = new PgSqlGenerator();

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
        expect($statements[0])->toContain('ALTER TABLE "users"');
        expect($statements[0])->toContain('ADD COLUMN');
        expect($statements[0])->toContain('"phone"');
    });

    it('generates PostgreSQL-specific index syntax', function (): void {
        $generator = new PgSqlGenerator();

        $index = new Index(
            name: 'idx_email',
            columns: ['email'],
            type: IndexType::Unique,
        );

        $sql = $generator->generateAddIndex('users', $index);

        expect($sql)->toContain('CREATE UNIQUE INDEX');
        expect($sql)->toContain('"idx_email"');
        expect($sql)->toContain('ON "users"');
    });

    it('generates PostgreSQL-specific DROP INDEX syntax', function (): void {
        $generator = new PgSqlGenerator();

        $sql = $generator->generateDropIndex('users', 'idx_email');

        // PostgreSQL indexes are not table-scoped
        expect($sql)->toBe('DROP INDEX "idx_email"');
        expect($sql)->not->toContain('ON "users"');
    });

    it('generates PostgreSQL-specific foreign key syntax', function (): void {
        $generator = new PgSqlGenerator();

        $foreignKey = new ForeignKey(
            name: 'fk_user_id',
            columns: ['user_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
            onDelete: 'CASCADE',
            onUpdate: 'SET NULL',
        );

        $sql = $generator->generateAddForeignKey('posts', $foreignKey);

        expect($sql)->toContain('ALTER TABLE "posts"');
        expect($sql)->toContain('ADD CONSTRAINT "fk_user_id"');
        expect($sql)->toContain('FOREIGN KEY ("user_id")');
        expect($sql)->toContain('REFERENCES "users" ("id")');
        expect($sql)->toContain('ON DELETE CASCADE');
        expect($sql)->toContain('ON UPDATE SET NULL');
    });

    it('generates PostgreSQL-specific DROP CONSTRAINT syntax', function (): void {
        $generator = new PgSqlGenerator();

        $sql = $generator->generateDropForeignKey('posts', 'fk_user_id');

        // PostgreSQL uses DROP CONSTRAINT (not DROP FOREIGN KEY like MySQL)
        expect($sql)->toContain('ALTER TABLE "posts"');
        expect($sql)->toContain('DROP CONSTRAINT "fk_user_id"');
    });

    it('generates complete migration with up and down', function (): void {
        $generator = new PgSqlGenerator();

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
        expect($upStatements[0])->toContain('CREATE TABLE "products"');

        expect($downStatements)->toHaveCount(1);
        expect($downStatements[0])->toContain('DROP TABLE "products"');
    });

    it('handles nullable columns correctly', function (): void {
        $generator = new PgSqlGenerator();

        // Use 'string' type to trigger VARCHAR with length
        $table = new Table(
            name: 'nullable_test',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true),
                new Column(name: 'optional_field', type: 'string', length: 100, nullable: true),
                new Column(name: 'required_field', type: 'string', length: 100, nullable: false),
            ],
            indexes: [],
        );

        $sql = $generator->generateCreateTable($table);

        // Nullable columns should not have NOT NULL
        expect($sql)->toContain('"optional_field" VARCHAR(100)');
        expect($sql)->toContain('"required_field" VARCHAR(100) NOT NULL');
    });

    it('handles default values with proper escaping', function (): void {
        $generator = new PgSqlGenerator();

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
        // PostgreSQL uses TRUE/FALSE for boolean
        expect($sql)->toContain('DEFAULT TRUE');
    });

    it('escapes single quotes in string defaults', function (): void {
        $generator = new PgSqlGenerator();

        $table = new Table(
            name: 'escape_test',
            columns: [
                new Column(name: 'id', type: 'INT', primaryKey: true),
                new Column(name: 'description', type: 'VARCHAR', length: 100, default: "It's a test"),
            ],
            indexes: [],
        );

        $sql = $generator->generateCreateTable($table);

        // PostgreSQL escapes single quotes by doubling them
        expect($sql)->toContain("'It''s a test'");
    });
});
