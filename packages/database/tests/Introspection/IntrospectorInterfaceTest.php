<?php

declare(strict_types=1);

use Marko\Database\Introspection\IntrospectorInterface;

use Marko\Database\Schema\Table;

describe('IntrospectorInterface', function (): void {
    it('defines getTables() returning array of table names', function (): void {
        $reflection = new ReflectionClass(IntrospectorInterface::class);

        expect($reflection->isInterface())->toBeTrue();
        expect($reflection->hasMethod('getTables'))->toBeTrue();

        $method = $reflection->getMethod('getTables');
        expect($method->getReturnType()?->getName())->toBe('array');
        expect($method->getParameters())->toHaveCount(0);
    });

    it('defines getTable(name) returning Table value object or null', function (): void {
        $reflection = new ReflectionClass(IntrospectorInterface::class);

        expect($reflection->hasMethod('getTable'))->toBeTrue();

        $method = $reflection->getMethod('getTable');
        $returnType = $method->getReturnType();
        expect($returnType)->toBeInstanceOf(ReflectionNamedType::class);
        expect($returnType->getName())->toBe(Table::class);
        expect($returnType->allowsNull())->toBeTrue();

        $params = $method->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('name');
        expect($params[0]->getType()?->getName())->toBe('string');
    });

    it('defines tableExists(name) returning boolean', function (): void {
        $reflection = new ReflectionClass(IntrospectorInterface::class);

        expect($reflection->hasMethod('tableExists'))->toBeTrue();

        $method = $reflection->getMethod('tableExists');
        expect($method->getReturnType()?->getName())->toBe('bool');

        $params = $method->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('name');
        expect($params[0]->getType()?->getName())->toBe('string');
    });

    it('defines getColumns(table) returning array of Column value objects', function (): void {
        $reflection = new ReflectionClass(IntrospectorInterface::class);

        expect($reflection->hasMethod('getColumns'))->toBeTrue();

        $method = $reflection->getMethod('getColumns');
        expect($method->getReturnType()?->getName())->toBe('array');

        $params = $method->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('table');
        expect($params[0]->getType()?->getName())->toBe('string');
    });

    it('defines getIndexes(table) returning array of Index value objects', function (): void {
        $reflection = new ReflectionClass(IntrospectorInterface::class);

        expect($reflection->hasMethod('getIndexes'))->toBeTrue();

        $method = $reflection->getMethod('getIndexes');
        expect($method->getReturnType()?->getName())->toBe('array');

        $params = $method->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('table');
        expect($params[0]->getType()?->getName())->toBe('string');
    });

    it('defines getForeignKeys(table) returning array of ForeignKey value objects', function (): void {
        $reflection = new ReflectionClass(IntrospectorInterface::class);

        expect($reflection->hasMethod('getForeignKeys'))->toBeTrue();

        $method = $reflection->getMethod('getForeignKeys');
        expect($method->getReturnType()?->getName())->toBe('array');

        $params = $method->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('table');
        expect($params[0]->getType()?->getName())->toBe('string');
    });

    it('defines getPrimaryKey(table) returning column names', function (): void {
        $reflection = new ReflectionClass(IntrospectorInterface::class);

        expect($reflection->hasMethod('getPrimaryKey'))->toBeTrue();

        $method = $reflection->getMethod('getPrimaryKey');
        expect($method->getReturnType()?->getName())->toBe('array');

        $params = $method->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('table');
        expect($params[0]->getType()?->getName())->toBe('string');
    });
});
