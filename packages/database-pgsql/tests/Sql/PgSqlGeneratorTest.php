<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Sql;

use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Diff\TableDiff;
use Marko\Database\PgSql\Sql\PgSqlGenerator;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;

describe('PgSqlGenerator', function (): void {
    beforeEach(function (): void {
        $this->generator = new PgSqlGenerator();
    });

    it('implements SqlGeneratorInterface', function (): void {
        expect($this->generator)->toBeInstanceOf(SqlGeneratorInterface::class);
    });

    it('generates CREATE TABLE with all column definitions', function (): void {
        $table = new Table(
            name: 'users',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new Column(name: 'email', type: 'string', length: 255, unique: true),
                new Column(name: 'name', type: 'string', length: 100, nullable: true),
                new Column(name: 'status', type: 'string', length: 20, default: 'active'),
            ],
        );

        $sql = $this->generator->generateCreateTable($table);

        expect($sql)->toContain('CREATE TABLE "users"');
        expect($sql)->toContain('"id" SERIAL PRIMARY KEY');
        expect($sql)->toContain('"email" VARCHAR(255) NOT NULL UNIQUE');
        expect($sql)->toContain('"name" VARCHAR(100)');
        expect($sql)->toContain('"status" VARCHAR(20) NOT NULL DEFAULT \'active\'');
    });

    it('generates DROP TABLE statements', function (): void {
        $sql = $this->generator->generateDropTable('users');

        expect($sql)->toBe('DROP TABLE "users"');
    });

    it('generates ALTER TABLE ADD COLUMN', function (): void {
        $column = new Column(
            name: 'bio',
            type: 'text',
            nullable: true,
        );

        $sql = $this->generator->generateAddColumn('users', $column);

        expect($sql)->toBe('ALTER TABLE "users" ADD COLUMN "bio" TEXT');
    });

    it('generates ALTER TABLE DROP COLUMN', function (): void {
        $sql = $this->generator->generateDropColumn('users', 'bio');

        expect($sql)->toBe('ALTER TABLE "users" DROP COLUMN "bio"');
    });

    it('generates ALTER TABLE ALTER COLUMN for type changes', function (): void {
        $newColumn = new Column(name: 'age', type: 'integer');
        $oldColumn = new Column(name: 'age', type: 'string', length: 10);

        $sql = $this->generator->generateModifyColumn('users', $newColumn, $oldColumn);

        expect($sql)->toContain('ALTER TABLE "users"');
        expect($sql)->toContain('ALTER COLUMN "age" TYPE INTEGER');
    });

    it('generates CREATE INDEX statements', function (): void {
        $index = new Index(
            name: 'idx_users_email',
            columns: ['email'],
            type: IndexType::Btree,
        );

        $sql = $this->generator->generateAddIndex('users', $index);

        expect($sql)->toBe('CREATE INDEX "idx_users_email" ON "users" ("email")');
    });

    it('generates DROP INDEX statements', function (): void {
        $sql = $this->generator->generateDropIndex('users', 'idx_users_email');

        expect($sql)->toBe('DROP INDEX "idx_users_email"');
    });

    it('generates ALTER TABLE ADD CONSTRAINT for foreign keys', function (): void {
        $foreignKey = new ForeignKey(
            name: 'fk_posts_user_id',
            columns: ['user_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
            onDelete: 'CASCADE',
            onUpdate: 'NO ACTION',
        );

        $sql = $this->generator->generateAddForeignKey('posts', $foreignKey);

        expect($sql)->toContain('ALTER TABLE "posts" ADD CONSTRAINT "fk_posts_user_id"');
        expect($sql)->toContain('FOREIGN KEY ("user_id") REFERENCES "users" ("id")');
        expect($sql)->toContain('ON DELETE CASCADE');
        expect($sql)->toContain('ON UPDATE NO ACTION');
    });

    it('generates ALTER TABLE DROP CONSTRAINT for foreign keys', function (): void {
        $sql = $this->generator->generateDropForeignKey('posts', 'fk_posts_user_id');

        expect($sql)->toBe('ALTER TABLE "posts" DROP CONSTRAINT "fk_posts_user_id"');
    });

    it('maps Column types to PostgreSQL data types', function (): void {
        $types = [
            ['type' => 'integer', 'expected' => 'INTEGER'],
            ['type' => 'bigint', 'expected' => 'BIGINT'],
            ['type' => 'smallint', 'expected' => 'SMALLINT'],
            ['type' => 'string', 'length' => 255, 'expected' => 'VARCHAR(255)'],
            ['type' => 'text', 'expected' => 'TEXT'],
            ['type' => 'boolean', 'expected' => 'BOOLEAN'],
            ['type' => 'datetime', 'expected' => 'TIMESTAMP'],
            ['type' => 'date', 'expected' => 'DATE'],
            ['type' => 'time', 'expected' => 'TIME'],
            ['type' => 'decimal', 'expected' => 'DECIMAL'],
            ['type' => 'float', 'expected' => 'REAL'],
            ['type' => 'double', 'expected' => 'DOUBLE PRECISION'],
            ['type' => 'json', 'expected' => 'JSONB'],
            ['type' => 'uuid', 'expected' => 'UUID'],
            ['type' => 'binary', 'expected' => 'BYTEA'],
        ];

        foreach ($types as $type) {
            $column = new Column(
                name: 'test',
                type: $type['type'],
                length: $type['length'] ?? null,
            );

            $sql = $this->generator->generateAddColumn('test_table', $column);

            expect(str_contains($sql, $type['expected']))->toBeTrue(
                "Type '{$type['type']}' should map to '{$type['expected']}', got: {$sql}",
            );
        }
    });

    it('handles SERIAL for auto-increment columns', function (): void {
        $intColumn = new Column(name: 'id', type: 'integer', autoIncrement: true);
        $bigintColumn = new Column(name: 'id', type: 'bigint', autoIncrement: true);

        $intSql = $this->generator->generateAddColumn('test', $intColumn);
        $bigintSql = $this->generator->generateAddColumn('test', $bigintColumn);

        expect($intSql)->toContain('SERIAL');
        expect($bigintSql)->toContain('BIGSERIAL');
    });

    it('generates proper DEFAULT expressions', function (): void {
        $stringDefault = new Column(name: 'status', type: 'string', length: 20, default: 'pending');
        $intDefault = new Column(name: 'count', type: 'integer', default: 0);
        $boolDefault = new Column(name: 'active', type: 'boolean', default: true);
        $nullDefault = new Column(name: 'notes', type: 'text', nullable: true, default: null);

        $stringSql = $this->generator->generateAddColumn('test', $stringDefault);
        $intSql = $this->generator->generateAddColumn('test', $intDefault);
        $boolSql = $this->generator->generateAddColumn('test', $boolDefault);
        $nullSql = $this->generator->generateAddColumn('test', $nullDefault);

        expect($stringSql)->toContain("DEFAULT 'pending'");
        expect($intSql)->toContain('DEFAULT 0');
        expect($boolSql)->toContain('DEFAULT TRUE');
        expect($nullSql)->not->toContain('DEFAULT');
    });

    it('generates down SQL that reverses up SQL', function (): void {
        $table = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new Column(name: 'title', type: 'string', length: 200),
            ],
            indexes: [
                new Index(name: 'idx_posts_title', columns: ['title']),
            ],
        );

        $diff = new SchemaDiff(
            tablesToCreate: [$table],
            tablesToDrop: [],
            tablesToAlter: [],
        );

        $upSql = $this->generator->generateUp($diff);
        $downSql = $this->generator->generateDown($diff);

        // Up should create, down should drop
        expect($upSql[0])->toContain('CREATE TABLE "posts"');
        expect($downSql)->toContain('DROP TABLE "posts"');
    });

    it('generates unique index for unique index type', function (): void {
        $index = new Index(
            name: 'idx_users_email_unique',
            columns: ['email'],
            type: IndexType::Unique,
        );

        $sql = $this->generator->generateAddIndex('users', $index);

        expect($sql)->toBe('CREATE UNIQUE INDEX "idx_users_email_unique" ON "users" ("email")');
    });

    it('generates multi-column indexes', function (): void {
        $index = new Index(
            name: 'idx_posts_user_created',
            columns: ['user_id', 'created_at'],
            type: IndexType::Btree,
        );

        $sql = $this->generator->generateAddIndex('posts', $index);

        expect($sql)->toBe('CREATE INDEX "idx_posts_user_created" ON "posts" ("user_id", "created_at")');
    });

    it('generates multi-column foreign keys', function (): void {
        $foreignKey = new ForeignKey(
            name: 'fk_order_items_product',
            columns: ['product_id', 'variant_id'],
            referencedTable: 'product_variants',
            referencedColumns: ['product_id', 'id'],
            onDelete: 'RESTRICT',
        );

        $sql = $this->generator->generateAddForeignKey('order_items', $foreignKey);

        expect($sql)->toContain('FOREIGN KEY ("product_id", "variant_id")');
        expect($sql)->toContain('REFERENCES "product_variants" ("product_id", "id")');
    });

    it('generates complete up SQL from schema diff', function (): void {
        $newTable = new Table(
            name: 'categories',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new Column(name: 'name', type: 'string', length: 100),
            ],
        );

        $tableDiff = new TableDiff(
            tableName: 'users',
            columnsToAdd: [
                new Column(name: 'phone', type: 'string', length: 20, nullable: true),
            ],
            indexesToAdd: [
                new Index(name: 'idx_users_phone', columns: ['phone']),
            ],
        );

        $diff = new SchemaDiff(
            tablesToCreate: [$newTable],
            tablesToAlter: ['users' => $tableDiff],
        );

        $sql = $this->generator->generateUp($diff);

        expect($sql)->toBeArray();
        expect(count($sql))->toBeGreaterThanOrEqual(3);
        expect($sql[0])->toContain('CREATE TABLE "categories"');
    });

    it('generates complete down SQL from schema diff', function (): void {
        $tableToCreate = new Table(
            name: 'tags',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
            ],
        );

        $tableToDrop = new Table(
            name: 'old_table',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true),
            ],
        );

        $diff = new SchemaDiff(
            tablesToCreate: [$tableToCreate],
            tablesToDrop: [$tableToDrop],
        );

        $sql = $this->generator->generateDown($diff);

        // Down should reverse: drop created tables, recreate dropped tables
        expect($sql)->toContain('DROP TABLE "tags"');
        expect(implode("\n", $sql))->toContain('CREATE TABLE "old_table"');
    });

    it('generates ALTER COLUMN SET NOT NULL and DROP NOT NULL', function (): void {
        // From nullable to non-nullable
        $newColumn = new Column(name: 'email', type: 'string', length: 255, nullable: false);
        $oldColumn = new Column(name: 'email', type: 'string', length: 255, nullable: true);

        $sql = $this->generator->generateModifyColumn('users', $newColumn, $oldColumn);

        expect($sql)->toContain('SET NOT NULL');

        // From non-nullable to nullable
        $newColumn2 = new Column(name: 'email', type: 'string', length: 255, nullable: true);
        $oldColumn2 = new Column(name: 'email', type: 'string', length: 255, nullable: false);

        $sql2 = $this->generator->generateModifyColumn('users', $newColumn2, $oldColumn2);

        expect($sql2)->toContain('DROP NOT NULL');
    });

    it('generates ALTER COLUMN SET DEFAULT and DROP DEFAULT', function (): void {
        // Adding a default
        $newColumn = new Column(name: 'status', type: 'string', length: 20, default: 'active');
        $oldColumn = new Column(name: 'status', type: 'string', length: 20);

        $sql = $this->generator->generateModifyColumn('users', $newColumn, $oldColumn);

        expect($sql)->toContain("SET DEFAULT 'active'");

        // Removing a default
        $newColumn2 = new Column(name: 'status', type: 'string', length: 20);
        $oldColumn2 = new Column(name: 'status', type: 'string', length: 20, default: 'active');

        $sql2 = $this->generator->generateModifyColumn('users', $newColumn2, $oldColumn2);

        expect($sql2)->toContain('DROP DEFAULT');
    });
});
