<?php

declare(strict_types=1);

use Marko\Database\Introspection\IntrospectorInterface;

use Marko\Database\Schema\Table;

describe('IntrospectorInterface', function (): void {
    it('defines getTables() returning array of table names', function (): void {
        $reflection = new ReflectionClass(IntrospectorInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('getTables'))->toBeTrue();

        $method = $reflection->getMethod('getTables');
        expect($method->getReturnType()?->getName())->toBe('array')
            ->and($method->getParameters())->toHaveCount(0);
    });

    it('defines getTable(name) returning Table value object or null', function (): void {
        $reflection = new ReflectionClass(IntrospectorInterface::class);

        expect($reflection->hasMethod('getTable'))->toBeTrue();

        $method = $reflection->getMethod('getTable');
        $returnType = $method->getReturnType();
        $params = $method->getParameters();
        expect($returnType)->toBeInstanceOf(ReflectionNamedType::class)
            ->and($returnType->getName())->toBe(Table::class)
            ->and($returnType->allowsNull())->toBeTrue()
            ->and($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('name')
            ->and($params[0]->getType()?->getName())->toBe('string');
    });

    it('defines tableExists(name) returning boolean', function (): void {
        $reflection = new ReflectionClass(IntrospectorInterface::class);

        expect($reflection->hasMethod('tableExists'))->toBeTrue();

        $method = $reflection->getMethod('tableExists');
        $params = $method->getParameters();
        expect($method->getReturnType()?->getName())->toBe('bool')
            ->and($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('name')
            ->and($params[0]->getType()?->getName())->toBe('string');
    });

    it('defines getColumns(table) returning array of Column value objects', function (): void {
        $reflection = new ReflectionClass(IntrospectorInterface::class);

        expect($reflection->hasMethod('getColumns'))->toBeTrue();

        $method = $reflection->getMethod('getColumns');
        $params = $method->getParameters();
        expect($method->getReturnType()?->getName())->toBe('array')
            ->and($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('table')
            ->and($params[0]->getType()?->getName())->toBe('string');
    });

    it('defines getIndexes(table) returning array of Index value objects', function (): void {
        $reflection = new ReflectionClass(IntrospectorInterface::class);

        expect($reflection->hasMethod('getIndexes'))->toBeTrue();

        $method = $reflection->getMethod('getIndexes');
        $params = $method->getParameters();
        expect($method->getReturnType()?->getName())->toBe('array')
            ->and($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('table')
            ->and($params[0]->getType()?->getName())->toBe('string');
    });

    it('defines getForeignKeys(table) returning array of ForeignKey value objects', function (): void {
        $reflection = new ReflectionClass(IntrospectorInterface::class);

        expect($reflection->hasMethod('getForeignKeys'))->toBeTrue();

        $method = $reflection->getMethod('getForeignKeys');
        $params = $method->getParameters();
        expect($method->getReturnType()?->getName())->toBe('array')
            ->and($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('table')
            ->and($params[0]->getType()?->getName())->toBe('string');
    });

    it('defines getPrimaryKey(table) returning column names', function (): void {
        $reflection = new ReflectionClass(IntrospectorInterface::class);

        expect($reflection->hasMethod('getPrimaryKey'))->toBeTrue();

        $method = $reflection->getMethod('getPrimaryKey');
        $params = $method->getParameters();
        expect($method->getReturnType()?->getName())->toBe('array')
            ->and($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('table')
            ->and($params[0]->getType()?->getName())->toBe('string');
    });
});
