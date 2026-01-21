<?php

declare(strict_types=1);

use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\Table;

describe('SqlGeneratorInterface', function (): void {
    it('defines generateUp(SchemaDiff) returning array of SQL statements', function (): void {
        $reflection = new ReflectionClass(SqlGeneratorInterface::class);

        expect($reflection->isInterface())->toBeTrue();
        expect($reflection->hasMethod('generateUp'))->toBeTrue();

        $method = $reflection->getMethod('generateUp');
        expect($method->getReturnType()?->getName())->toBe('array');

        $params = $method->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('diff');
        expect($params[0]->getType()?->getName())->toBe(SchemaDiff::class);
    });

    it('defines generateDown(SchemaDiff) returning array of SQL statements', function (): void {
        $reflection = new ReflectionClass(SqlGeneratorInterface::class);

        expect($reflection->hasMethod('generateDown'))->toBeTrue();

        $method = $reflection->getMethod('generateDown');
        expect($method->getReturnType()?->getName())->toBe('array');

        $params = $method->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('diff');
        expect($params[0]->getType()?->getName())->toBe(SchemaDiff::class);
    });

    it('defines generateCreateTable(Table) returning SQL string', function (): void {
        $reflection = new ReflectionClass(SqlGeneratorInterface::class);

        expect($reflection->hasMethod('generateCreateTable'))->toBeTrue();

        $method = $reflection->getMethod('generateCreateTable');
        expect($method->getReturnType()?->getName())->toBe('string');

        $params = $method->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('table');
        expect($params[0]->getType()?->getName())->toBe(Table::class);
    });

    it('defines generateDropTable(tableName) returning SQL string', function (): void {
        $reflection = new ReflectionClass(SqlGeneratorInterface::class);

        expect($reflection->hasMethod('generateDropTable'))->toBeTrue();

        $method = $reflection->getMethod('generateDropTable');
        expect($method->getReturnType()?->getName())->toBe('string');

        $params = $method->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('tableName');
        expect($params[0]->getType()?->getName())->toBe('string');
    });

    it('defines generateAddColumn(table, Column) returning SQL string', function (): void {
        $reflection = new ReflectionClass(SqlGeneratorInterface::class);

        expect($reflection->hasMethod('generateAddColumn'))->toBeTrue();

        $method = $reflection->getMethod('generateAddColumn');
        expect($method->getReturnType()?->getName())->toBe('string');

        $params = $method->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('table');
        expect($params[0]->getType()?->getName())->toBe('string');
        expect($params[1]->getName())->toBe('column');
        expect($params[1]->getType()?->getName())->toBe(Column::class);
    });

    it('defines generateDropColumn(table, columnName) returning SQL string', function (): void {
        $reflection = new ReflectionClass(SqlGeneratorInterface::class);

        expect($reflection->hasMethod('generateDropColumn'))->toBeTrue();

        $method = $reflection->getMethod('generateDropColumn');
        expect($method->getReturnType()?->getName())->toBe('string');

        $params = $method->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('table');
        expect($params[0]->getType()?->getName())->toBe('string');
        expect($params[1]->getName())->toBe('columnName');
        expect($params[1]->getType()?->getName())->toBe('string');
    });

    it('defines generateModifyColumn(table, Column, oldColumn) returning SQL string', function (): void {
        $reflection = new ReflectionClass(SqlGeneratorInterface::class);

        expect($reflection->hasMethod('generateModifyColumn'))->toBeTrue();

        $method = $reflection->getMethod('generateModifyColumn');
        expect($method->getReturnType()?->getName())->toBe('string');

        $params = $method->getParameters();
        expect($params)->toHaveCount(3);
        expect($params[0]->getName())->toBe('table');
        expect($params[0]->getType()?->getName())->toBe('string');
        expect($params[1]->getName())->toBe('column');
        expect($params[1]->getType()?->getName())->toBe(Column::class);
        expect($params[2]->getName())->toBe('oldColumn');
        expect($params[2]->getType()?->getName())->toBe(Column::class);
    });

    it('defines generateAddIndex(table, Index) returning SQL string', function (): void {
        $reflection = new ReflectionClass(SqlGeneratorInterface::class);

        expect($reflection->hasMethod('generateAddIndex'))->toBeTrue();

        $method = $reflection->getMethod('generateAddIndex');
        expect($method->getReturnType()?->getName())->toBe('string');

        $params = $method->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('table');
        expect($params[0]->getType()?->getName())->toBe('string');
        expect($params[1]->getName())->toBe('index');
        expect($params[1]->getType()?->getName())->toBe(Index::class);
    });

    it('defines generateDropIndex(table, indexName) returning SQL string', function (): void {
        $reflection = new ReflectionClass(SqlGeneratorInterface::class);

        expect($reflection->hasMethod('generateDropIndex'))->toBeTrue();

        $method = $reflection->getMethod('generateDropIndex');
        expect($method->getReturnType()?->getName())->toBe('string');

        $params = $method->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('table');
        expect($params[0]->getType()?->getName())->toBe('string');
        expect($params[1]->getName())->toBe('indexName');
        expect($params[1]->getType()?->getName())->toBe('string');
    });

    it('defines generateAddForeignKey(table, ForeignKey) returning SQL string', function (): void {
        $reflection = new ReflectionClass(SqlGeneratorInterface::class);

        expect($reflection->hasMethod('generateAddForeignKey'))->toBeTrue();

        $method = $reflection->getMethod('generateAddForeignKey');
        expect($method->getReturnType()?->getName())->toBe('string');

        $params = $method->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('table');
        expect($params[0]->getType()?->getName())->toBe('string');
        expect($params[1]->getName())->toBe('foreignKey');
        expect($params[1]->getType()?->getName())->toBe(ForeignKey::class);
    });

    it('defines generateDropForeignKey(table, keyName) returning SQL string', function (): void {
        $reflection = new ReflectionClass(SqlGeneratorInterface::class);

        expect($reflection->hasMethod('generateDropForeignKey'))->toBeTrue();

        $method = $reflection->getMethod('generateDropForeignKey');
        expect($method->getReturnType()?->getName())->toBe('string');

        $params = $method->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('table');
        expect($params[0]->getType()?->getName())->toBe('string');
        expect($params[1]->getName())->toBe('keyName');
        expect($params[1]->getType()?->getName())->toBe('string');
    });
});
