<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Sql;

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
use Marko\Database\PgSql\Sql\PgSqlGenerator;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Expression;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Literal;
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

        expect($sql)->toContain('CREATE TABLE "users"')
            ->and($sql)->toContain('"id" SERIAL PRIMARY KEY')
            ->and($sql)->toContain('"email" VARCHAR(255) NOT NULL UNIQUE')
            ->and($sql)->toContain('"name" VARCHAR(100)')
            ->and($sql)->toContain('"status" VARCHAR(20) NOT NULL DEFAULT \'active\'');
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

        expect($sql)->toContain('ALTER TABLE "users"')
            ->and($sql)->toContain('ALTER COLUMN "age" TYPE INTEGER');
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

    it('generates a partial index with a WHERE clause', function (): void {
        $index = new Index(
            name: 'shows_live_idx',
            columns: ['status'],
            where: "status = 'live'",
        );

        $sql = $this->generator->generateAddIndex('shows', $index);

        expect($sql)->toBe('CREATE INDEX "shows_live_idx" ON "shows" ("status") WHERE status = \'live\'');
    });

    it('recreates a dropped partial index with its WHERE clause in the down migration', function (): void {
        $diff = new SchemaDiff(
            tablesToAlter: [
                'shows' => new TableDiff(
                    tableName: 'shows',
                    indexesToDrop: [
                        new Index(name: 'shows_live_idx', columns: ['status'], where: "(status)::text = 'live'::text"),
                    ],
                ),
            ],
        );

        $up = $this->generator->generateUp($diff);
        $down = $this->generator->generateDown($diff);

        expect($up)->toContain('DROP INDEX "shows_live_idx"')
            ->and($down)->toContain(
                'CREATE INDEX "shows_live_idx" ON "shows" ("status") WHERE (status)::text = \'live\'::text',
            );
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

        expect($sql)->toContain('ALTER TABLE "posts" ADD CONSTRAINT "fk_posts_user_id"')
            ->and($sql)->toContain('FOREIGN KEY ("user_id") REFERENCES "users" ("id")')
            ->and($sql)->toContain('ON DELETE CASCADE')
            ->and($sql)->toContain('ON UPDATE NO ACTION');
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
            ['type' => 'timestamp', 'expected' => 'TIMESTAMP'],
            ['type' => 'date', 'expected' => 'DATE'],
            ['type' => 'time', 'expected' => 'TIME'],
            ['type' => 'decimal', 'expected' => 'DECIMAL(10,2)'],
            ['type' => 'float', 'expected' => 'REAL'],
            ['type' => 'double', 'expected' => 'DOUBLE PRECISION'],
            ['type' => 'json', 'expected' => 'JSONB'],
            ['type' => 'uuid', 'expected' => 'UUID'],
            ['type' => 'binary', 'expected' => 'BYTEA'],
            ['type' => 'enum', 'length' => 50, 'expected' => 'VARCHAR(50)'],
        ];

        foreach ($types as $type) {
            $column = new Column(
                name: 'test',
                type: $type['type'],
                length: $type['length'] ?? null,
            );

            $sql = $this->generator->generateAddColumn('test_table', $column);

            expect(str_contains($sql, $type['expected']))->toBeTrue(
                "Type '{$type['type']}' should map to '{$type['expected']}', got: $sql",
            );
        }
    });

    it('handles SERIAL for auto-increment columns', function (): void {
        $intColumn = new Column(name: 'id', type: 'integer', autoIncrement: true);
        $bigintColumn = new Column(name: 'id', type: 'bigint', autoIncrement: true);

        $intSql = $this->generator->generateAddColumn('test', $intColumn);
        $bigintSql = $this->generator->generateAddColumn('test', $bigintColumn);

        expect($intSql)->toContain('SERIAL')
            ->and($bigintSql)->toContain('BIGSERIAL');
    });

    it('generates proper DEFAULT expressions', function (): void {
        $stringDefault = new Column(name: 'status', type: 'string', length: 20, default: 'pending');
        $intDefault = new Column(name: 'count', type: 'integer', default: 0);
        $boolDefault = new Column(name: 'active', type: 'boolean', default: true);
        $nullDefault = new Column(name: 'notes', type: 'text', nullable: true);

        $stringSql = $this->generator->generateAddColumn('test', $stringDefault);
        $intSql = $this->generator->generateAddColumn('test', $intDefault);
        $boolSql = $this->generator->generateAddColumn('test', $boolDefault);
        $nullSql = $this->generator->generateAddColumn('test', $nullDefault);

        expect($stringSql)->toContain("DEFAULT 'pending'")
            ->and($intSql)->toContain('DEFAULT 0')
            ->and($boolSql)->toContain('DEFAULT TRUE')
            ->and($nullSql)->not->toContain('DEFAULT');
    });

    it('generates separate index and foreign key statements for new tables in up SQL', function (): void {
        $table = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new Column(name: 'user_id', type: 'integer'),
                new Column(name: 'title', type: 'string', length: 255),
            ],
            indexes: [
                new Index(name: 'idx_posts_title', columns: ['title']),
                new Index(name: 'idx_posts_user_id', columns: ['user_id'], type: IndexType::Btree),
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

        $diff = new SchemaDiff(tablesToCreate: [$table]);
        $sql = $this->generator->generateUp($diff);

        // CREATE TABLE + 2 CREATE INDEX + 1 ADD FOREIGN KEY
        expect($sql)->toHaveCount(4)
            ->and($sql[0])->toContain('CREATE TABLE "posts"')
            ->and($sql[1])->toContain('CREATE INDEX "idx_posts_title"')
            ->and($sql[2])->toContain('CREATE INDEX "idx_posts_user_id"')
            ->and($sql[3])->toContain('ADD CONSTRAINT "fk_posts_user_id"');
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
        expect($upSql[0])->toContain('CREATE TABLE "posts"')
            ->and($downSql)->toContain('DROP TABLE "posts"');
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

        expect($sql)->toContain('FOREIGN KEY ("product_id", "variant_id")')
            ->and($sql)->toContain('REFERENCES "product_variants" ("product_id", "id")');
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

        expect($sql)->toBeArray()
            ->and(count($sql))->toBeGreaterThanOrEqual(3)
            ->and($sql[0])->toContain('CREATE TABLE "categories"');
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
        expect($sql)->toContain('DROP TABLE "tags"')
            ->and(implode("\n", $sql))->toContain('CREATE TABLE "old_table"');
    });

    it('generates ALTER COLUMN SET NOT NULL and DROP NOT NULL', function (): void {
        // From nullable to non-nullable
        $newColumn = new Column(name: 'email', type: 'string', length: 255);
        $oldColumn = new Column(name: 'email', type: 'string', length: 255, nullable: true);

        $sql = $this->generator->generateModifyColumn('users', $newColumn, $oldColumn);

        expect($sql)->toContain('SET NOT NULL');

        // From non-nullable to nullable
        $newColumn2 = new Column(name: 'email', type: 'string', length: 255, nullable: true);
        $oldColumn2 = new Column(name: 'email', type: 'string', length: 255);

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

    it('defaults VARCHAR to 255 when no length specified', function (): void {
        $table = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'title', type: 'string'),  // No length specified
            ],
        );

        $sql = $this->generator->generateCreateTable($table);

        expect($sql)->toContain('"title" VARCHAR(255) NOT NULL');
    });

    it('handles primary key with SERIAL type', function (): void {
        // PostgreSQL uses SERIAL which implicitly handles NOT NULL
        $table = new Table(
            name: 'posts',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true, nullable: true),
                new Column(name: 'title', type: 'string', length: 255),
            ],
        );

        $sql = $this->generator->generateCreateTable($table);

        // SERIAL implies NOT NULL in PostgreSQL
        expect($sql)->toContain('"id" SERIAL PRIMARY KEY');
    });

    it('generates a valid PostgreSQL type for a tinyint column', function (): void {
        $column = new Column(name: 'flags', type: 'tinyint');

        $sql = $this->generator->generateAddColumn('t', $column);

        expect($sql)->toContain('SMALLINT');
    });

    it('generates a valid PostgreSQL type for a bool column', function (): void {
        $column = new Column(name: 'active', type: 'bool');

        $sql = $this->generator->generateAddColumn('t', $column);

        expect($sql)->toContain('BOOLEAN');
    });

    it('generates a valid PostgreSQL type for a blob column', function (): void {
        $column = new Column(name: 'data', type: 'blob');

        $sql = $this->generator->generateAddColumn('t', $column);

        expect($sql)->toContain('BYTEA');
    });

    it('generates DECIMAL with the shared precision for a decimal column', function (): void {
        $column = new Column(name: 'price', type: 'decimal');

        $sql = $this->generator->generateAddColumn('t', $column);

        expect($sql)->toContain('DECIMAL(10,2)');
    });

    it('emits PostgreSQL jsonb DDL type for #[Column(type: \'json\')]', function (): void {
        $table = new Table(
            name: 'products',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new Column(name: 'metadata', type: 'json'),
            ],
        );

        $sql = $this->generator->generateCreateTable($table);

        expect($sql)->toContain('"metadata" JSONB NOT NULL');
    });

    it('emits SET DEFAULT for a default-only change in an up migration', function (): void {
        $statements = $this->generator->generateUp(pgsqlModifyDiff(
            new Column(name: 'status', type: 'string', length: 20, default: 'live'),
            new Column(name: 'status', type: 'string', length: 20, default: 'draft'),
        ));

        expect($statements)->toBe(['ALTER TABLE "posts" ALTER COLUMN "status" SET DEFAULT \'live\'']);
    });

    it('emits DROP DEFAULT from generateModifyColumn when the new column has no default', function (): void {
        $sql = $this->generator->generateModifyColumn(
            'posts',
            new Column(name: 'status', type: 'string', length: 20),
            new Column(name: 'status', type: 'string', length: 20, default: 'draft'),
        );

        expect($sql)->toBe('ALTER TABLE "posts" ALTER COLUMN "status" DROP DEFAULT');
    });

    it('leaves the database default alone in an up migration when the entity declares no default', function (): void {
        $statements = $this->generator->generateUp(pgsqlModifyDiff(
            new Column(name: 'status', type: 'string', length: 20),
            new Column(name: 'status', type: 'string', length: 20, nullable: true, default: 'draft'),
        ));

        expect($statements)->toBe(['ALTER TABLE "posts" ALTER COLUMN "status" SET NOT NULL']);
    });

    it('keeps the database length in an up migration when the entity declares no length', function (): void {
        $statements = $this->generator->generateUp(pgsqlModifyDiff(
            new Column(name: 'title', type: 'string', default: 'Untitled'),
            new Column(name: 'title', type: 'string', length: 500),
        ));

        expect($statements)->toBe(['ALTER TABLE "posts" ALTER COLUMN "title" SET DEFAULT \'Untitled\'']);
    });

    it('does not drop NOT NULL on an auto-increment primary key declared nullable in PHP', function (): void {
        $statements = $this->generator->generateUp(pgsqlModifyDiff(
            new Column(name: 'id', type: 'bigint', nullable: true, primaryKey: true, autoIncrement: true),
            new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
        ));

        expect($statements)->toBe(
            [pgsqlSequenceTypeChange(
                'ALTER TABLE "posts" ALTER COLUMN "id" TYPE BIGINT USING "id"::BIGINT',
                'BIGINT',
            )],
        );
    });

    it('drops the default in a down migration when the old column had none', function (): void {
        $statements = $this->generator->generateDown(pgsqlModifyDiff(
            new Column(name: 'status', type: 'string', length: 20, default: 'live'),
            new Column(name: 'status', type: 'string', length: 20),
        ));

        expect($statements)->toBe(['ALTER TABLE "posts" ALTER COLUMN "status" DROP DEFAULT']);
    });

    it('restores a CURRENT_TIMESTAMP default unquoted in a down migration', function (): void {
        $statements = $this->generator->generateDown(pgsqlModifyDiff(
            new Column(name: 'created_at', type: 'timestamp', default: '2026-01-01 00:00:00'),
            new Column(name: 'created_at', type: 'timestamp', default: 'CURRENT_TIMESTAMP'),
        ));

        expect($statements)->toBe([
            'ALTER TABLE "posts" ALTER COLUMN "created_at" SET DEFAULT CURRENT_TIMESTAMP',
        ]);

        $sql = $this->generator->generateModifyColumn(
            'posts',
            new Column(name: 'created_at', type: 'timestamp', default: 'now()'),
            new Column(name: 'created_at', type: 'timestamp'),
        );

        expect($sql)->toBe('ALTER TABLE "posts" ALTER COLUMN "created_at" SET DEFAULT now()');
    });

    it('creates a CURRENT_TIMESTAMP default unquoted', function (): void {
        $sql = $this->generator->generateCreateTable(new Table(
            name: 'posts',
            columns: [new Column(name: 'created_at', type: 'timestamp', default: 'CURRENT_TIMESTAMP')],
        ));

        expect($sql)->toContain('"created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
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
                columnsToModify: ['author_id' => new Column(name: 'author_id', type: 'text')],
                foreignKeysToDrop: [$foreignKey],
                columnsToModifyFrom: ['author_id' => new Column(name: 'author_id', type: 'integer')],
            ),
        ]);

        expect($this->generator->generateDown($diff))->toBe([
            'ALTER TABLE "posts" ALTER COLUMN "author_id" TYPE INTEGER USING "author_id"::INTEGER',
            'ALTER TABLE "posts" ADD CONSTRAINT "posts_author_id_foreign" FOREIGN KEY ("author_id") '
            . 'REFERENCES "users" ("id")',
        ]);
    });

    it('emits SET NOT NULL when a column becomes required', function (): void {
        $statements = $this->generator->generateUp(pgsqlModifyDiff(
            new Column(name: 'title', type: 'text'),
            new Column(name: 'title', type: 'text', nullable: true),
        ));

        expect($statements)->toBe(['ALTER TABLE "posts" ALTER COLUMN "title" SET NOT NULL']);
    });

    it('emits DROP NOT NULL when a column becomes nullable', function (): void {
        $statements = $this->generator->generateUp(pgsqlModifyDiff(
            new Column(name: 'title', type: 'text', nullable: true),
            new Column(name: 'title', type: 'text'),
        ));

        expect($statements)->toBe(['ALTER TABLE "posts" ALTER COLUMN "title" DROP NOT NULL']);
    });

    it('combines a type change and a default change in one ALTER TABLE', function (): void {
        $statements = $this->generator->generateUp(pgsqlModifyDiff(
            new Column(name: 'views', type: 'bigint', default: 0),
            new Column(name: 'views', type: 'integer'),
        ));

        expect($statements)->toBe([
            'ALTER TABLE "posts" ALTER COLUMN "views" TYPE BIGINT USING "views"::BIGINT, '
            . 'ALTER COLUMN "views" SET DEFAULT 0',
        ]);
    });

    it('emits no statement for a column whose only difference is handled by the index diff', function (): void {
        $statements = $this->generator->generateUp(pgsqlModifyDiff(
            new Column(name: 'email', type: 'string'),
            new Column(name: 'email', type: 'string', unique: true),
        ));

        expect($statements)->toBeEmpty();
    });

    it('throws instead of returning an empty ALTER TABLE from generateModifyColumn', function (): void {
        $column = new Column(name: 'email', type: 'string');

        expect(fn () => $this->generator->generateModifyColumn('posts', $column, $column))->toThrow(
            MigrationException::class,
            "Column 'posts.email' has no type, nullability or default change for PostgreSQL to apply",
        );
    });

    it('throws when a modified column changes its auto-increment in place', function (): void {
        expect(fn () => $this->generator->generateUp(pgsqlModifyDiff(
            new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
            new Column(name: 'id', type: 'integer', primaryKey: true),
        )))->toThrow(
            MigrationException::class,
            "Cannot change the auto-increment of column 'posts.id' in place on PostgreSQL",
        );
    });

    it('throws when a modified column changes its primary key in place', function (): void {
        expect(fn () => $this->generator->generateUp(pgsqlModifyDiff(
            new Column(name: 'code', type: 'string', primaryKey: true),
            new Column(name: 'code', type: 'string'),
        )))->toThrow(
            MigrationException::class,
            "Cannot change the primary key of column 'posts.code' in place on PostgreSQL",
        );
    });

    it('restores the old type, nullability and default in a down migration', function (): void {
        $statements = $this->generator->generateDown(pgsqlModifyDiff(
            new Column(name: 'views', type: 'bigint', nullable: true, default: 5),
            new Column(name: 'views', type: 'integer', default: 0),
        ));

        expect($statements)->toBe([
            'ALTER TABLE "posts" ALTER COLUMN "views" DROP DEFAULT, '
            . 'ALTER COLUMN "views" TYPE INTEGER USING "views"::INTEGER, ALTER COLUMN "views" SET NOT NULL, '
            . 'ALTER COLUMN "views" SET DEFAULT 0',
        ]);
    });

    it('ignores native metadata on the previous column', function (): void {
        $diff = pgsqlModifyDiff(
            new Column(name: 'price', type: 'decimal', nullable: true),
            new Column(
                name: 'price',
                type: 'decimal',
                nativeType: 'numeric(12,4)',
                collation: 'C',
                onUpdateExpression: 'CURRENT_TIMESTAMP',
            ),
        );

        expect($this->generator->generateUp($diff))->toBe([
            'ALTER TABLE "posts" ALTER COLUMN "price" DROP NOT NULL',
        ])->and($this->generator->generateDown($diff))->toBe([
            'ALTER TABLE "posts" ALTER COLUMN "price" SET NOT NULL',
        ]);
    });

    it('emits an unquoted gen_random_uuid() default', function (): void {
        $sql = $this->generator->generateCreateTable(new Table(
            name: 'articles',
            columns: [new Column(name: 'id', type: 'uuid', primaryKey: true, default: 'gen_random_uuid()')],
        ));

        expect($sql)->toContain('"id" UUID DEFAULT gen_random_uuid() PRIMARY KEY');
    });

    it('emits an explicit expression default raw', function (): void {
        $sql = $this->generator->generateAddColumn(
            'posts',
            new Column(name: 'expires_at', type: 'timestamp', default: new Expression("now() + interval '1 day'")),
        );

        expect($sql)->toBe(
            'ALTER TABLE "posts" ADD COLUMN "expires_at" TIMESTAMP NOT NULL DEFAULT now() + interval \'1 day\'',
        );
    });

    it('quotes a literal default that looks like a function', function (): void {
        $sql = $this->generator->generateAddColumn(
            'posts',
            new Column(name: 'label', type: 'string', default: new Literal("it's now()")),
        );

        expect($sql)->toBe('ALTER TABLE "posts" ADD COLUMN "label" VARCHAR(255) NOT NULL DEFAULT \'it\'\'s now()\'');
    });

    it('keeps an existing CURRENT_TIMESTAMP default unquoted', function (): void {
        $sql = $this->generator->generateAddColumn(
            'posts',
            new Column(name: 'created_at', type: 'timestamp', default: 'CURRENT_TIMESTAMP(6)'),
        );

        expect($sql)->toBe(
            'ALTER TABLE "posts" ADD COLUMN "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(6)',
        );
    });

    it('casts with USING when changing varchar to integer', function (): void {
        $statements = $this->generator->generateUp(pgsqlModifyDiff(
            new Column(name: 'quantity', type: 'integer'),
            new Column(name: 'quantity', type: 'varchar', length: 20),
        ));

        expect($statements)->toBe([
            'ALTER TABLE "posts" ALTER COLUMN "quantity" TYPE INTEGER USING "quantity"::INTEGER',
        ]);
    });

    it('casts with USING when changing integer to varchar in the down migration', function (): void {
        $diff = pgsqlModifyDiff(
            new Column(name: 'quantity', type: 'integer'),
            new Column(name: 'quantity', type: 'varchar', length: 20),
        );

        expect($this->generator->generateDown($diff))->toBe([
            'ALTER TABLE "posts" ALTER COLUMN "quantity" TYPE VARCHAR(20) USING "quantity"::VARCHAR(20)',
        ]);
    });

    it('drops and restores the default around a type change', function (): void {
        $diff = pgsqlModifyDiff(
            new Column(name: 'quantity', type: 'integer', default: 0),
            new Column(name: 'quantity', type: 'varchar', length: 20, default: '0'),
        );

        expect($this->generator->generateUp($diff))->toBe([
            'ALTER TABLE "posts" ALTER COLUMN "quantity" DROP DEFAULT, '
            . 'ALTER COLUMN "quantity" TYPE INTEGER USING "quantity"::INTEGER, '
            . 'ALTER COLUMN "quantity" SET DEFAULT 0',
        ])->and($this->generator->generateDown($diff))->toBe([
            'ALTER TABLE "posts" ALTER COLUMN "quantity" DROP DEFAULT, '
            . 'ALTER COLUMN "quantity" TYPE VARCHAR(20) USING "quantity"::VARCHAR(20), '
            . 'ALTER COLUMN "quantity" SET DEFAULT \'0\'',
        ]);
    });

    it('only drops the default around a type change when the target column has no default', function (): void {
        $sql = $this->generator->generateModifyColumn(
            'posts',
            new Column(name: 'quantity', type: 'integer'),
            new Column(name: 'quantity', type: 'varchar', length: 20, default: '0'),
        );

        expect($sql)->toBe(
            'ALTER TABLE "posts" ALTER COLUMN "quantity" DROP DEFAULT, '
            . 'ALTER COLUMN "quantity" TYPE INTEGER USING "quantity"::INTEGER',
        );
    });

    it('emits DROP DEFAULT, TYPE ... USING and SET DEFAULT in that order within one ALTER TABLE', function (): void {
        $sql = $this->generator->generateModifyColumn(
            'posts',
            new Column(name: 'ref', type: 'uuid', nullable: true, default: new Expression('gen_random_uuid()')),
            new Column(name: 'ref', type: 'varchar', length: 36, default: 'none'),
        );

        expect($sql)->toBe(
            'ALTER TABLE "posts" ALTER COLUMN "ref" DROP DEFAULT, '
            . 'ALTER COLUMN "ref" TYPE UUID USING "ref"::UUID, '
            . 'ALTER COLUMN "ref" DROP NOT NULL, '
            . 'ALTER COLUMN "ref" SET DEFAULT gen_random_uuid()',
        );
    });

    it(
        'does not drop or restore the sequence default when changing the type of an auto-increment column',
        function (): void {
            $diff = pgsqlModifyDiff(
                new Column(name: 'id', type: 'bigint', primaryKey: true, autoIncrement: true),
                new Column(
                    name: 'id',
                    type: 'integer',
                    primaryKey: true,
                    autoIncrement: true,
                    default: new Expression("nextval('posts_id_seq'::regclass)"),
                ),
            );

            expect($this->generator->generateUp($diff))->toBe([
                pgsqlSequenceTypeChange(
                    'ALTER TABLE "posts" ALTER COLUMN "id" TYPE BIGINT USING "id"::BIGINT',
                    'BIGINT',
                ),
            ])->and($this->generator->generateDown($diff))->toBe([
                pgsqlSequenceTypeChange(
                    'ALTER TABLE "posts" ALTER COLUMN "id" TYPE INTEGER USING "id"::INTEGER',
                    'INTEGER',
                ),
            ]);
        },
    );

    it('does not alter the default when only the default\'s representation differs', function (): void {
        $diff = pgsqlModifyDiff(
            new Column(name: 'created_at', type: 'timestamp', nullable: true, default: 'NOW()'),
            new Column(name: 'created_at', type: 'timestamp', default: new Expression('now()')),
        );

        expect($this->generator->generateUp($diff))->toBe([
            'ALTER TABLE "posts" ALTER COLUMN "created_at" DROP NOT NULL',
        ]);
    });

    it('throws a MigrationException naming the column when columnsToModifyFrom is missing it', function (): void {
        $diff = new SchemaDiff(tablesToAlter: [
            'posts' => new TableDiff(
                tableName: 'posts',
                columnsToModify: ['status' => new Column(name: 'status', type: 'string', default: 'live')],
            ),
        ]);

        expect(fn () => $this->generator->generateUp($diff))->toThrow(
            MigrationException::class,
            "Column 'posts.status' is modified, but the diff holds no previous definition for it",
        )->and(fn () => $this->generator->generateDown($diff))->toThrow(
            MigrationException::class,
            "Column 'posts.status' is modified, but the diff holds no previous definition for it",
        );
    });
    it('drops a unique constraint with DROP CONSTRAINT', function (): void {
        $constraint = new Index(
            name: 'users_email_key',
            columns: ['email'],
            type: IndexType::Unique,
            constraint: true,
        );
        $diff = new SchemaDiff(
            tablesToAlter: ['users' => new TableDiff(tableName: 'users', indexesToDrop: [$constraint])],
        );

        expect($this->generator->generateUp($diff))->toBe(['ALTER TABLE "users" DROP CONSTRAINT "users_email_key"']);
    });

    it('restores a dropped unique constraint with ADD CONSTRAINT in down', function (): void {
        $constraint = new Index(
            name: 'users_email_key',
            columns: ['email'],
            type: IndexType::Unique,
            constraint: true,
        );
        $diff = new SchemaDiff(
            tablesToAlter: ['users' => new TableDiff(tableName: 'users', indexesToDrop: [$constraint])],
        );

        expect($this->generator->generateDown($diff))
            ->toBe(['ALTER TABLE "users" ADD CONSTRAINT "users_email_key" UNIQUE ("email")']);
    });

    it('adds a unique index for a column that becomes unique and drops it in down', function (): void {
        $index = new Index(name: 'users_email_unique', columns: ['email'], type: IndexType::Unique);
        $diff = new SchemaDiff(tablesToAlter: ['users' => new TableDiff(tableName: 'users', indexesToAdd: [$index])]);

        expect($this->generator->generateUp($diff))
            ->toBe(['CREATE UNIQUE INDEX "users_email_unique" ON "users" ("email")'])
            ->and($this->generator->generateDown($diff))->toBe(['DROP INDEX "users_email_unique"']);
    });
});

/**
 * A schema diff that modifies one column of the posts table.
 */
function pgsqlModifyDiff(
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

/**
 * The DO block that changes the type of the posts.id auto-increment key together with its owned sequence.
 */
function pgsqlSequenceTypeChange(
    string $alterTable,
    string $sequenceType,
): string {
    return <<<SQL
        DO \$\$
        DECLARE
            sequence_name text := pg_get_serial_sequence('"posts"', 'id');
        BEGIN
            IF sequence_name IS NULL THEN
                RAISE EXCEPTION USING MESSAGE = 'Column "id" of table "posts" is auto-increment, but no sequence is owned by it, so its sequence cannot change type with it. Make the sequence that feeds it owned by the column (ALTER SEQUENCE ... OWNED BY "posts"."id"), then run the migration again.';
            END IF;
            $alterTable;
            EXECUTE format('ALTER SEQUENCE %s AS $sequenceType', sequence_name);
        END
        \$\$
        SQL;
}

describe('PgSqlGenerator auto-increment sequence type changes', function (): void {
    beforeEach(function (): void {
        $this->generator = new PgSqlGenerator();
    });

    it('widens the owned sequence with an auto-increment key in one statement', function (): void {
        $statements = $this->generator->generateUp(pgsqlModifyDiff(
            new Column(name: 'id', type: 'bigint', primaryKey: true, autoIncrement: true),
            new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
        ));

        expect($statements)->toHaveCount(1)
            ->and($statements[0])->toStartWith("DO \$\$\n")
            ->and($statements[0])->toContain("pg_get_serial_sequence('\"posts\"', 'id')")
            ->and($statements[0])->toContain('ALTER TABLE "posts" ALTER COLUMN "id" TYPE BIGINT USING "id"::BIGINT;')
            ->and($statements[0])->toContain("EXECUTE format('ALTER SEQUENCE %s AS BIGINT', sequence_name);")
            ->and($statements[0])->toEndWith("\n\$\$");
    });

    it('narrows the owned sequence back in the down migration', function (): void {
        $statements = $this->generator->generateDown(pgsqlModifyDiff(
            new Column(name: 'id', type: 'bigint', primaryKey: true, autoIncrement: true),
            new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
        ));

        expect($statements)->toBe([
            pgsqlSequenceTypeChange(
                'ALTER TABLE "posts" ALTER COLUMN "id" TYPE INTEGER USING "id"::INTEGER',
                'INTEGER',
            ),
        ]);
    });

    it('changes the sequence of a smallint auto-increment key widened to integer', function (): void {
        $diff = pgsqlModifyDiff(
            new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
            new Column(name: 'id', type: 'smallint', primaryKey: true, autoIncrement: true),
        );

        expect($this->generator->generateUp($diff))->toBe([
            pgsqlSequenceTypeChange(
                'ALTER TABLE "posts" ALTER COLUMN "id" TYPE INTEGER USING "id"::INTEGER',
                'INTEGER',
            ),
        ])->and($this->generator->generateDown($diff))->toBe([
            pgsqlSequenceTypeChange(
                'ALTER TABLE "posts" ALTER COLUMN "id" TYPE SMALLINT USING "id"::SMALLINT',
                'SMALLINT',
            ),
        ]);
    });

    it('raises an error naming the table and column when the key owns no sequence', function (): void {
        $statement = $this->generator->generateUp(pgsqlModifyDiff(
            new Column(name: 'id', type: 'bigint', primaryKey: true, autoIncrement: true),
            new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
        ))[0];

        expect($statement)->toContain("IF sequence_name IS NULL THEN\n        RAISE EXCEPTION USING MESSAGE = ")
            ->and($statement)->toContain(
                'Column "id" of table "posts" is auto-increment, but no sequence is owned by it',
            )
            ->and($statement)->toContain('ALTER SEQUENCE ... OWNED BY "posts"."id"');
    });

    it('keeps a plain ALTER TABLE for an auto-increment column whose type does not change', function (): void {
        $statements = $this->generator->generateUp(pgsqlModifyDiff(
            new Column(name: 'id', type: 'int', nullable: true, autoIncrement: true),
            new Column(name: 'id', type: 'integer', autoIncrement: true),
        ));

        expect($statements)->toBe(['ALTER TABLE "posts" ALTER COLUMN "id" DROP NOT NULL']);
    });

    it('keeps a plain ALTER TABLE for a type change on a column that is not auto-increment', function (): void {
        $statements = $this->generator->generateUp(pgsqlModifyDiff(
            new Column(name: 'views', type: 'bigint'),
            new Column(name: 'views', type: 'integer'),
        ));

        expect($statements)->toBe(['ALTER TABLE "posts" ALTER COLUMN "views" TYPE BIGINT USING "views"::BIGINT']);
    });

    it('puts a nullability change of the auto-increment column inside the same DO block', function (): void {
        $statements = $this->generator->generateUp(pgsqlModifyDiff(
            new Column(name: 'id', type: 'bigint', nullable: true, autoIncrement: true),
            new Column(name: 'id', type: 'integer', autoIncrement: true),
        ));

        expect($statements)->toBe([
            pgsqlSequenceTypeChange(
                'ALTER TABLE "posts" ALTER COLUMN "id" TYPE BIGINT USING "id"::BIGINT, ALTER COLUMN "id" DROP NOT NULL',
                'BIGINT',
            ),
        ]);
    });

    it(
        'double-quotes the table name passed to pg_get_serial_sequence so a mixed-case table is found',
        function (): void {
            $statement = $this->generator->generateUp(new SchemaDiff(tablesToAlter: [
                'BlogPosts' => new TableDiff(
                    tableName: 'BlogPosts',
                    columnsToModify: [
                        'PostId' => new Column(name: 'PostId', type: 'bigint', primaryKey: true, autoIncrement: true),
                    ],
                    columnsToModifyFrom: [
                        'PostId' => new Column(name: 'PostId', type: 'integer', primaryKey: true, autoIncrement: true),
                    ],
                ),
            ]))[0];

            expect($statement)->toContain("pg_get_serial_sequence('\"BlogPosts\"', 'PostId')");
        },
    );

    it('escapes single quotes in the table and column names it embeds in string literals', function (): void {
        $statement = $this->generator->generateUp(new SchemaDiff(tablesToAlter: [
            "o'brien" => new TableDiff(
                tableName: "o'brien",
                columnsToModify: [
                    "o'id" => new Column(name: "o'id", type: 'bigint', primaryKey: true, autoIncrement: true),
                ],
                columnsToModifyFrom: [
                    "o'id" => new Column(name: "o'id", type: 'integer', primaryKey: true, autoIncrement: true),
                ],
            ),
        ]))[0];

        expect($statement)->toContain("pg_get_serial_sequence('\"o''brien\"', 'o''id')")
            ->and($statement)->toContain('Column "o\'\'id" of table "o\'\'brien"');
    });

    it('dollar-quotes the sequence DO block with a tag that does not occur in its body', function (): void {
        $statement = $this->generator->generateUp(new SchemaDiff(tablesToAlter: [
            'pri$$ces' => new TableDiff(
                tableName: 'pri$$ces',
                columnsToModify: [
                    'i$$d' => new Column(name: 'i$$d', type: 'bigint', primaryKey: true, autoIncrement: true),
                ],
                columnsToModifyFrom: [
                    'i$$d' => new Column(name: 'i$$d', type: 'integer', primaryKey: true, autoIncrement: true),
                ],
            ),
        ]))[0];

        $body = substr($statement, strlen("DO \$marko\$\n"), -strlen("\n\$marko\$"));

        expect($statement)->toStartWith("DO \$marko\$\n")
            ->and($statement)->toEndWith("\n\$marko\$")
            ->and($body)->toContain('ALTER TABLE "pri$$ces" ALTER COLUMN "i$$d" TYPE BIGINT')
            ->and($body)->not->toContain('$marko$');
    });

    it('picks another tag when a name contains the default tag', function (): void {
        $statement = $this->generator->generateUp(new SchemaDiff(tablesToAlter: [
            'a$$b$marko$c' => new TableDiff(
                tableName: 'a$$b$marko$c',
                columnsToModify: [
                    'id' => new Column(name: 'id', type: 'bigint', primaryKey: true, autoIncrement: true),
                ],
                columnsToModifyFrom: [
                    'id' => new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                ],
            ),
        ]))[0];

        expect($statement)->toStartWith("DO \$marko_1\$\n")
            ->and($statement)->toEndWith("\n\$marko_1\$")
            ->and(substr_count($statement, '$marko_1$'))->toBe(2);
    });
});

describe('PgSqlGenerator identifier quoting', function (): void {
    beforeEach(function (): void {
        $this->generator = new PgSqlGenerator();
    });

    it('escapes a double quote in a table name in CREATE TABLE', function (): void {
        $sql = $this->generator->generateCreateTable(new Table(
            name: 'we"ird',
            columns: [new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true)],
        ));

        expect($sql)->toStartWith('CREATE TABLE "we""ird" (')
            ->and($this->generator->generateDropTable('we"ird'))->toBe('DROP TABLE "we""ird"');
    });

    it('escapes a double quote in column, index, constraint and referenced names', function (): void {
        $foreignKey = $this->generator->generateAddForeignKey('posts', new ForeignKey(
            name: 'fk"a',
            columns: ['user"id'],
            referencedTable: 'us"ers',
            referencedColumns: ['i"d'],
        ));

        expect($this->generator->generateAddColumn('posts', new Column(name: 'ti"tle', type: 'string')))
            ->toStartWith('ALTER TABLE "posts" ADD COLUMN "ti""tle" VARCHAR(255)')
            ->and($this->generator->generateDropColumn('posts', 'ti"tle'))
            ->toBe('ALTER TABLE "posts" DROP COLUMN "ti""tle"')
            ->and($this->generator->generateAddIndex('posts', new Index(name: 'idx"a', columns: ['col"a'])))
            ->toBe('CREATE INDEX "idx""a" ON "posts" ("col""a")')
            ->and($this->generator->generateAddIndex(
                'posts',
                new Index(name: 'uq"a', columns: ['col"a'], type: IndexType::Unique, constraint: true),
            ))
            ->toBe('ALTER TABLE "posts" ADD CONSTRAINT "uq""a" UNIQUE ("col""a")')
            ->and($this->generator->generateDropIndex('posts', 'idx"a'))->toBe('DROP INDEX "idx""a"')
            ->and($foreignKey)->toBe(
                'ALTER TABLE "posts" ADD CONSTRAINT "fk""a" FOREIGN KEY ("user""id") REFERENCES "us""ers" ("i""d")',
            )
            ->and($this->generator->generateDropForeignKey('posts', 'fk"a'))
            ->toBe('ALTER TABLE "posts" DROP CONSTRAINT "fk""a"');
    });

    it('escapes a double quote in ALTER COLUMN statements', function (): void {
        $sql = $this->generator->generateModifyColumn(
            'po"sts',
            new Column(name: 'vi"ews', type: 'bigint', nullable: true, default: 0),
            new Column(name: 'vi"ews', type: 'integer'),
        );

        expect($sql)->toBe(
            'ALTER TABLE "po""sts" ALTER COLUMN "vi""ews" TYPE BIGINT USING "vi""ews"::BIGINT, '
            . 'ALTER COLUMN "vi""ews" DROP NOT NULL, ALTER COLUMN "vi""ews" SET DEFAULT 0',
        );
    });

    it('quotes reserved-word and mixed-case columns in generated DDL', function (): void {
        $sql = $this->generator->generateCreateTable(new Table(
            name: 'permissions',
            columns: [
                new Column(name: 'key', type: 'string'),
                new Column(name: 'group', type: 'string'),
                new Column(name: 'sortOrder', type: 'integer'),
            ],
        ));

        expect($sql)->toContain('"key" VARCHAR(255) NOT NULL')
            ->and($sql)->toContain('"group" VARCHAR(255) NOT NULL')
            ->and($sql)->toContain('"sortOrder" INTEGER NOT NULL');
    });

    it(
        'has no inline double-quote identifier quoting in the generator, query builder or introspector',
        function (): void {
            $source = dirname(__DIR__, 2) . '/src';

            foreach (['Sql/PgSqlGenerator.php', 'Query/PgSqlQueryBuilder.php', 'Introspection/PgSqlIntrospector.php'] as $file) {
                $code = (string) file_get_contents("$source/$file");

                // An escaped double quote around an interpolation, or a double-quote string literal concatenated onto a name
                expect(preg_match('/\\\\"\$|\'"\'\s*\.|\.\s*\'"\'/', $code))->toBe(
                    0,
                    "$file quotes an identifier inline",
                );
            }
        },
    );
});

describe('PgSqlGenerator derived names over 63 bytes', function (): void {
    beforeEach(function (): void {
        $this->statements = new PgSqlGenerator()->generateUp(pgsqlLongNameDiff());
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

describe('PgSqlGenerator primary key columns on existing tables', function (): void {
    beforeEach(function (): void {
        $this->generator = new PgSqlGenerator();
        $this->up = fn (TableDiff $tableDiff): array => $this->generator->generateUp(
            new SchemaDiff(tablesToAlter: [$tableDiff->tableName => $tableDiff]),
        );
        $this->down = fn (TableDiff $tableDiff): array => $this->generator->generateDown(
            new SchemaDiff(tablesToAlter: [$tableDiff->tableName => $tableDiff]),
        );
        $this->serialId = new Column(name: 'id', type: 'int', primaryKey: true, autoIncrement: true);
    });

    it('adds a serial primary key column and its key in one statement', function (): void {
        $statements = ($this->up)(new TableDiff(tableName: 'admin_user_roles', columnsToAdd: [$this->serialId]));

        expect($statements)->toBe(['ALTER TABLE "admin_user_roles" ADD COLUMN "id" SERIAL, ADD PRIMARY KEY ("id")']);
    });

    it('adds the key with a primary key column passed to generateAddColumn', function (): void {
        expect($this->generator->generateAddColumn('admin_user_roles', $this->serialId))
            ->toBe('ALTER TABLE "admin_user_roles" ADD COLUMN "id" SERIAL, ADD PRIMARY KEY ("id")');
    });

    it('adds every column of a composite primary key and the key in one statement', function (): void {
        $statements = ($this->up)(new TableDiff(tableName: 'post_tags', columnsToAdd: [
            new Column(name: 'post_id', type: 'int', primaryKey: true),
            new Column(name: 'tag_id', type: 'int', primaryKey: true),
        ]));

        expect($statements)->toBe([
            'ALTER TABLE "post_tags" ADD COLUMN "post_id" INTEGER, ADD COLUMN "tag_id" INTEGER, '
            . 'ADD PRIMARY KEY ("post_id", "tag_id")',
        ]);
    });

    it('adds a uuid primary key column with its default and key in one statement', function (): void {
        $statements = ($this->up)(new TableDiff(tableName: 'tokens', columnsToAdd: [
            new Column(name: 'id', type: 'uuid', default: new Expression('gen_random_uuid()'), primaryKey: true),
        ]));

        expect($statements)->toBe([
            'ALTER TABLE "tokens" ADD COLUMN "id" UUID DEFAULT gen_random_uuid(), ADD PRIMARY KEY ("id")',
        ]);
    });

    it('keeps added columns that are not part of the key in their own statements', function (): void {
        $statements = ($this->up)(new TableDiff(tableName: 'posts', columnsToAdd: [
            new Column(name: 'title', type: 'string', length: 100),
            $this->serialId,
        ]));

        expect($statements)->toBe([
            'ALTER TABLE "posts" ADD COLUMN "title" VARCHAR(100) NOT NULL',
            'ALTER TABLE "posts" ADD COLUMN "id" SERIAL, ADD PRIMARY KEY ("id")',
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

        expect($statements)->toBe(['ALTER TABLE "posts" ADD COLUMN "body" TEXT']);
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
            "Cannot change the primary key of column 'post_tags.tag_id' in place on PostgreSQL",
        );
    });

    it('drops an added primary key column in down', function (): void {
        $statements = ($this->down)(new TableDiff(tableName: 'admin_user_roles', columnsToAdd: [$this->serialId]));

        expect($statements)->toBe(['ALTER TABLE "admin_user_roles" DROP COLUMN "id"']);
    });

    it('restores a dropped primary key column with its key in down', function (): void {
        $statements = ($this->down)(new TableDiff(tableName: 'admin_user_roles', columnsToDrop: [$this->serialId]));

        expect($statements)->toBe(['ALTER TABLE "admin_user_roles" ADD COLUMN "id" SERIAL, ADD PRIMARY KEY ("id")']);
    });
});

/**
 * The diff that makes an existing column of a long-named table unique and a foreign key, so both derived names are
 * over 63 bytes before shortening.
 */
function pgsqlLongNameDiff(): SchemaDiff
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

describe('PgSqlGenerator with a current table', function (): void {
    it('leaves pgsql and mysql generator output unchanged', function (): void {
        $generator = new PgSqlGenerator();
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
