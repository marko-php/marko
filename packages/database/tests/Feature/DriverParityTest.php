<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Feature;

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Diff\TableDiff;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\MySql\Sql\MySqlGenerator;
use Marko\Database\PgSql\Sql\PgSqlGenerator;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;
use Marko\Database\Sqlite\Sql\SqliteGenerator;

describe('Driver Parity', function (): void {
    beforeEach(function (): void {
        $this->mysqlGenerator = new MySqlGenerator();
        $this->pgsqlGenerator = new PgSqlGenerator();
        $this->sqliteGenerator = new SqliteGenerator(DatabaseTimezoneConfig::fromName('UTC'));
    });

    it('works identically on MySQL and PostgreSQL for table creation', function (): void {
        $table = new Table(
            name: 'users',
            columns: [
                new Column(name: 'id', type: 'INT', primaryKey: true, autoIncrement: true),
                new Column(name: 'name', type: 'VARCHAR', length: 255),
                new Column(name: 'email', type: 'VARCHAR', length: 255, unique: true),
                new Column(name: 'is_active', type: 'BOOLEAN', default: true),
            ],
            indexes: [
                new Index(name: 'idx_name', columns: ['name'], type: IndexType::Btree),
            ],
        );

        $diff = new SchemaDiff(tablesToCreate: [$table]);

        // MySQL includes indexes inline in CREATE TABLE (1 statement)
        // PostgreSQL generates separate CREATE INDEX statements (1 CREATE TABLE + 1 CREATE INDEX)
        $mysqlUp = $this->mysqlGenerator->generateUp($diff);
        $pgsqlUp = $this->pgsqlGenerator->generateUp($diff);

        expect($mysqlUp)
            ->toHaveCount(1)
            ->and($mysqlUp[0])->toContain('CREATE TABLE')
            ->and($pgsqlUp[0])->toContain('CREATE TABLE')
            ->and($pgsqlUp)->toHaveCount(2)
            ->and($pgsqlUp[1])->toContain('CREATE INDEX');

        // Both should generate exactly 1 DROP TABLE for down
        $mysqlDown = $this->mysqlGenerator->generateDown($diff);
        $pgsqlDown = $this->pgsqlGenerator->generateDown($diff);

        expect($mysqlDown)
            ->toHaveCount(1)
            ->and($pgsqlDown)->toHaveCount(1)
            ->and($mysqlDown[0])->toContain('DROP TABLE')
            ->and($pgsqlDown[0])->toContain('DROP TABLE');
    });

    it('generates equivalent column definitions with different syntax', function (): void {
        $table = new Table(
            name: 'test',
            columns: [
                new Column(name: 'id', type: 'INT', primaryKey: true),
                new Column(name: 'status', type: 'VARCHAR', length: 50, default: 'active'),
                new Column(name: 'count', type: 'INT', default: 0, nullable: true),
            ],
            indexes: [],
        );

        $mysqlSql = $this->mysqlGenerator->generateCreateTable($table);
        $pgsqlSql = $this->pgsqlGenerator->generateCreateTable($table);

        // Both should define the same columns (with different quoting/types)
        // MySQL uses backticks, PostgreSQL uses double quotes
        expect($mysqlSql)
            ->toContain('`id`')
            ->toContain('`status`')
            ->toContain('DEFAULT')
            ->and($pgsqlSql)
            ->toContain('"id"')
            ->toContain('"status"')
            ->toContain('DEFAULT');
    });

    it('generates equivalent index operations', function (): void {
        $index = new Index(
            name: 'idx_email',
            columns: ['email'],
            type: IndexType::Unique,
        );

        $mysqlSql = $this->mysqlGenerator->generateAddIndex('users', $index);
        $pgsqlSql = $this->pgsqlGenerator->generateAddIndex('users', $index);

        // Both should create a unique index and reference the same index name
        expect($mysqlSql)
            ->toContain('CREATE UNIQUE INDEX')
            ->toContain('idx_email')
            ->and($pgsqlSql)
            ->toContain('CREATE UNIQUE INDEX')
            ->toContain('idx_email');
    });

    it('generates equivalent foreign key operations', function (): void {
        $foreignKey = new ForeignKey(
            name: 'fk_author_id',
            columns: ['author_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
            onDelete: 'CASCADE',
        );

        $mysqlSql = $this->mysqlGenerator->generateAddForeignKey('posts', $foreignKey);
        $pgsqlSql = $this->pgsqlGenerator->generateAddForeignKey('posts', $foreignKey);

        // Both should add the foreign key constraint
        expect($mysqlSql)
            ->toContain('ADD CONSTRAINT')
            ->toContain('fk_author_id')
            ->toContain('FOREIGN KEY')
            ->toContain('REFERENCES')
            ->toContain('ON DELETE CASCADE')
            ->and($pgsqlSql)
            ->toContain('ADD CONSTRAINT')
            ->toContain('fk_author_id')
            ->toContain('FOREIGN KEY')
            ->toContain('REFERENCES')
            ->toContain('ON DELETE CASCADE');
    });

    it('generates correct statements for complex migrations on both drivers', function (): void {
        $table1 = new Table(
            name: 'categories',
            columns: [
                new Column(name: 'id', type: 'INT', primaryKey: true, autoIncrement: true),
                new Column(name: 'name', type: 'VARCHAR', length: 100),
            ],
            indexes: [],
        );

        $table2 = new Table(
            name: 'products',
            columns: [
                new Column(name: 'id', type: 'INT', primaryKey: true, autoIncrement: true),
                new Column(name: 'category_id', type: 'INT'),
                new Column(name: 'name', type: 'VARCHAR', length: 255),
                new Column(name: 'price', type: 'DECIMAL'),
            ],
            indexes: [
                new Index(name: 'idx_category', columns: ['category_id'], type: IndexType::Btree),
            ],
        );

        $diff = new SchemaDiff(
            tablesToCreate: [$table1, $table2],
        );

        // MySQL includes indexes inline: 2 CREATE TABLE statements
        // PostgreSQL generates separate indexes: 2 CREATE TABLE + 1 CREATE INDEX
        $mysqlUp = $this->mysqlGenerator->generateUp($diff);
        $pgsqlUp = $this->pgsqlGenerator->generateUp($diff);

        expect($mysqlUp)->toHaveCount(2)
            ->and($pgsqlUp)->toHaveCount(3);

        // Down statements are the same count (just DROP TABLE)
        $mysqlDown = $this->mysqlGenerator->generateDown($diff);
        $pgsqlDown = $this->pgsqlGenerator->generateDown($diff);

        expect(count($mysqlDown))->toBe(count($pgsqlDown));
    });

    it('implements SqlGeneratorInterface consistently', function (): void {
        // Both generators should implement the same interface
        expect($this->mysqlGenerator)
            ->toBeInstanceOf(SqlGeneratorInterface::class)
            ->and($this->pgsqlGenerator)->toBeInstanceOf(SqlGeneratorInterface::class)
            ->and($this->sqliteGenerator)->toBeInstanceOf(SqlGeneratorInterface::class);

        // Both should have all required methods
        $requiredMethods = [
            'generateUp',
            'generateDown',
            'generateCreateTable',
            'generateDropTable',
            'generateAddColumn',
            'generateDropColumn',
            'generateModifyColumn',
            'generateAddIndex',
            'generateDropIndex',
            'generateAddForeignKey',
            'generateDropForeignKey',
        ];

        foreach ($requiredMethods as $method) {
            expect(method_exists($this->mysqlGenerator, $method))
                ->toBeTrue()
                ->and(method_exists($this->pgsqlGenerator, $method))->toBeTrue()
                ->and(method_exists($this->sqliteGenerator, $method))->toBeTrue();
        }
    });

    it('handles empty diffs identically', function (): void {
        $emptyDiff = new SchemaDiff();

        $mysqlUp = $this->mysqlGenerator->generateUp($emptyDiff);
        $pgsqlUp = $this->pgsqlGenerator->generateUp($emptyDiff);

        expect($mysqlUp)
            ->toBeEmpty()
            ->and($pgsqlUp)->toBeEmpty();

        $mysqlDown = $this->mysqlGenerator->generateDown($emptyDiff);
        $pgsqlDown = $this->pgsqlGenerator->generateDown($emptyDiff);

        expect($mysqlDown)
            ->toBeEmpty()
            ->and($pgsqlDown)->toBeEmpty();
    });

    it('includes sqlite in the cross-driver parity tests', function (): void {
        $table = new Table(
            name: 'users',
            columns: [
                new Column(name: 'id', type: 'INT', primaryKey: true, autoIncrement: true),
                new Column(name: 'name', type: 'VARCHAR', length: 255),
                new Column(name: 'is_active', type: 'BOOLEAN', default: true),
            ],
            indexes: [
                new Index(name: 'idx_name', columns: ['name'], type: IndexType::Btree),
            ],
        );
        $diff = new SchemaDiff(tablesToCreate: [$table]);
        $index = new Index(name: 'idx_email', columns: ['email'], type: IndexType::Unique);
        $foreignKey = new ForeignKey(
            name: 'fk_author_id',
            columns: ['author_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
            onDelete: 'CASCADE',
        );
        $up = $this->sqliteGenerator->generateUp($diff);

        // Like PostgreSQL: one CREATE TABLE plus a separate CREATE INDEX, and one DROP TABLE for down
        expect($up)->toHaveCount(2)
            ->and($up[0])->toContain('CREATE TABLE')->toContain('"id"')->toContain('DEFAULT 1')
            ->and($up[1])->toContain('CREATE INDEX')
            ->and($this->sqliteGenerator->generateDown($diff))->toHaveCount(1)
            ->and($this->sqliteGenerator->generateDown($diff)[0])->toContain('DROP TABLE')
            ->and($this->sqliteGenerator->generateUp(new SchemaDiff()))->toBeEmpty()
            ->and($this->sqliteGenerator->generateAddIndex('users', $index))
            ->toContain('CREATE UNIQUE INDEX')->toContain('idx_email')
            // SQLite changes foreign keys and column definitions only by rebuilding the table
            ->and(fn () => $this->sqliteGenerator->generateAddForeignKey('posts', $foreignKey))
            ->toThrow(MigrationException::class)
            ->and(fn () => $this->sqliteGenerator->generateDropForeignKey('posts', 'fk_author_id'))
            ->toThrow(MigrationException::class)
            ->and(fn () => $this->sqliteGenerator->generateModifyColumn(
                'posts',
                new Column(name: 'title', type: 'varchar', length: 500),
                new Column(name: 'title', type: 'varchar', nullable: true),
            ))->toThrow(MigrationException::class);
    });

    describe('column modifications', function (): void {
        it('keeps an undeclared length on both drivers', function (): void {
            $diff = parityModifyDiff(
                new Column(name: 'title', type: 'varchar', nullable: true),
                new Column(name: 'title', type: 'varchar', length: 500),
            );

            expect($this->mysqlGenerator->generateUp($diff))
                ->toBe(['ALTER TABLE `posts` MODIFY COLUMN `title` VARCHAR(500) NULL'])
                ->and($this->pgsqlGenerator->generateUp($diff))
                ->toBe(['ALTER TABLE "posts" ALTER COLUMN "title" DROP NOT NULL']);
        });

        it('keeps an undeclared default on both drivers', function (): void {
            $diff = parityModifyDiff(
                new Column(name: 'status', type: 'varchar', length: 20, nullable: true),
                new Column(name: 'status', type: 'varchar', length: 20, default: 'draft'),
            );

            expect($this->mysqlGenerator->generateUp($diff))
                ->toBe(["ALTER TABLE `posts` MODIFY COLUMN `status` VARCHAR(20) NULL DEFAULT 'draft'"])
                ->and($this->pgsqlGenerator->generateUp($diff))
                ->toBe(['ALTER TABLE "posts" ALTER COLUMN "status" DROP NOT NULL']);
        });

        it('emits nothing on either driver when only an accepted difference remains', function (): void {
            $diff = parityModifyDiff(
                new Column(name: 'email', type: 'varchar', unique: true),
                new Column(name: 'email', type: 'varchar', length: 500, default: 'none'),
            );

            expect($this->mysqlGenerator->generateUp($diff))->toBeEmpty()
                ->and($this->pgsqlGenerator->generateUp($diff))->toBeEmpty()
                ->and($this->mysqlGenerator->generateDown($diff))->toBeEmpty()
                ->and($this->pgsqlGenerator->generateDown($diff))->toBeEmpty();
        });
    });
});

/**
 * A schema diff that modifies one column of the posts table.
 */
function parityModifyDiff(
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
