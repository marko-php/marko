<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Feature;

use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\MySql\Sql\MySqlGenerator;
use Marko\Database\PgSql\Sql\PgSqlGenerator;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;

describe('Driver Parity', function (): void {
    beforeEach(function (): void {
        $this->mysqlGenerator = new MySqlGenerator();
        $this->pgsqlGenerator = new PgSqlGenerator();
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
            ->and($this->pgsqlGenerator)->toBeInstanceOf(SqlGeneratorInterface::class);

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
                ->and(method_exists($this->pgsqlGenerator, $method))->toBeTrue();
        }
    });

    it('handles empty diffs identically', function (): void {
        $emptyDiff = new SchemaDiff();

        $mysqlUp = $this->mysqlGenerator->generateUp($emptyDiff);
        $pgsqlUp = $this->pgsqlGenerator->generateUp($emptyDiff);

        expect($mysqlUp)
            ->toBe([])
            ->and($pgsqlUp)->toBe([]);

        $mysqlDown = $this->mysqlGenerator->generateDown($emptyDiff);
        $pgsqlDown = $this->pgsqlGenerator->generateDown($emptyDiff);

        expect($mysqlDown)
            ->toBe([])
            ->and($pgsqlDown)->toBe([]);
    });
});
