<?php

declare(strict_types=1);

use Marko\Database\Query\QueryBuilderInterface;

describe('QueryBuilderInterface', function (): void {
    it('defines table() method to set target table', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->isInterface())->toBeTrue();
        expect($reflection->hasMethod('table'))->toBeTrue();

        $method = $reflection->getMethod('table');
        $params = $method->getParameters();

        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('table');
        expect($params[0]->getType()?->getName())->toBe('string');

        // Returns self for fluent chaining
        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('static');
    });

    it('defines select() method for column selection', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('select'))->toBeTrue();

        $method = $reflection->getMethod('select');
        $params = $method->getParameters();

        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('columns');
        expect($params[0]->isVariadic())->toBeTrue();
        expect($params[0]->getType()?->getName())->toBe('string');

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('static');
    });

    it('defines where() method with column, operator, value', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('where'))->toBeTrue();

        $method = $reflection->getMethod('where');
        $params = $method->getParameters();

        expect($params)->toHaveCount(3);
        expect($params[0]->getName())->toBe('column');
        expect($params[0]->getType()?->getName())->toBe('string');
        expect($params[1]->getName())->toBe('operator');
        expect($params[1]->getType()?->getName())->toBe('string');
        expect($params[2]->getName())->toBe('value');
        expect($params[2]->getType()?->getName())->toBe('mixed');

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('static');
    });

    it('defines whereIn() method for IN clauses', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('whereIn'))->toBeTrue();

        $method = $reflection->getMethod('whereIn');
        $params = $method->getParameters();

        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('column');
        expect($params[0]->getType()?->getName())->toBe('string');
        expect($params[1]->getName())->toBe('values');
        expect($params[1]->getType()?->getName())->toBe('array');

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('static');
    });

    it('defines whereNull() and whereNotNull() methods', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('whereNull'))->toBeTrue();
        expect($reflection->hasMethod('whereNotNull'))->toBeTrue();

        $whereNull = $reflection->getMethod('whereNull');
        $params = $whereNull->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('column');
        expect($params[0]->getType()?->getName())->toBe('string');
        expect($whereNull->getReturnType()?->getName())->toBe('static');

        $whereNotNull = $reflection->getMethod('whereNotNull');
        $params = $whereNotNull->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('column');
        expect($params[0]->getType()?->getName())->toBe('string');
        expect($whereNotNull->getReturnType()?->getName())->toBe('static');
    });

    it('defines orWhere() for OR conditions', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('orWhere'))->toBeTrue();

        $method = $reflection->getMethod('orWhere');
        $params = $method->getParameters();

        expect($params)->toHaveCount(3);
        expect($params[0]->getName())->toBe('column');
        expect($params[0]->getType()?->getName())->toBe('string');
        expect($params[1]->getName())->toBe('operator');
        expect($params[1]->getType()?->getName())->toBe('string');
        expect($params[2]->getName())->toBe('value');
        expect($params[2]->getType()?->getName())->toBe('mixed');

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('static');
    });

    it('defines join(), leftJoin(), rightJoin() methods', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('join'))->toBeTrue();
        expect($reflection->hasMethod('leftJoin'))->toBeTrue();
        expect($reflection->hasMethod('rightJoin'))->toBeTrue();

        // Check join() method
        $join = $reflection->getMethod('join');
        $params = $join->getParameters();
        expect($params)->toHaveCount(4);
        expect($params[0]->getName())->toBe('table');
        expect($params[0]->getType()?->getName())->toBe('string');
        expect($params[1]->getName())->toBe('first');
        expect($params[1]->getType()?->getName())->toBe('string');
        expect($params[2]->getName())->toBe('operator');
        expect($params[2]->getType()?->getName())->toBe('string');
        expect($params[3]->getName())->toBe('second');
        expect($params[3]->getType()?->getName())->toBe('string');
        expect($join->getReturnType()?->getName())->toBe('static');

        // Check leftJoin() method
        $leftJoin = $reflection->getMethod('leftJoin');
        $params = $leftJoin->getParameters();
        expect($params)->toHaveCount(4);
        expect($params[0]->getName())->toBe('table');
        expect($params[1]->getName())->toBe('first');
        expect($params[2]->getName())->toBe('operator');
        expect($params[3]->getName())->toBe('second');
        expect($leftJoin->getReturnType()?->getName())->toBe('static');

        // Check rightJoin() method
        $rightJoin = $reflection->getMethod('rightJoin');
        $params = $rightJoin->getParameters();
        expect($params)->toHaveCount(4);
        expect($params[0]->getName())->toBe('table');
        expect($params[1]->getName())->toBe('first');
        expect($params[2]->getName())->toBe('operator');
        expect($params[3]->getName())->toBe('second');
        expect($rightJoin->getReturnType()?->getName())->toBe('static');
    });

    it('defines orderBy() method with direction', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('orderBy'))->toBeTrue();

        $method = $reflection->getMethod('orderBy');
        $params = $method->getParameters();

        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('column');
        expect($params[0]->getType()?->getName())->toBe('string');
        expect($params[1]->getName())->toBe('direction');
        expect($params[1]->getType()?->getName())->toBe('string');
        expect($params[1]->isDefaultValueAvailable())->toBeTrue();
        expect($params[1]->getDefaultValue())->toBe('ASC');

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('static');
    });

    it('defines limit() and offset() methods', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('limit'))->toBeTrue();
        expect($reflection->hasMethod('offset'))->toBeTrue();

        $limit = $reflection->getMethod('limit');
        $params = $limit->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('limit');
        expect($params[0]->getType()?->getName())->toBe('int');
        expect($limit->getReturnType()?->getName())->toBe('static');

        $offset = $reflection->getMethod('offset');
        $params = $offset->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('offset');
        expect($params[0]->getType()?->getName())->toBe('int');
        expect($offset->getReturnType()?->getName())->toBe('static');
    });

    it('defines get() method returning array of rows', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('get'))->toBeTrue();

        $method = $reflection->getMethod('get');
        $params = $method->getParameters();

        expect($params)->toHaveCount(0);

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('array');
    });

    it('defines first() method returning single row or null', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('first'))->toBeTrue();

        $method = $reflection->getMethod('first');
        $params = $method->getParameters();

        expect($params)->toHaveCount(0);

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('array');
        expect($returnType?->allowsNull())->toBeTrue();
    });

    it('defines insert() method with data array', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('insert'))->toBeTrue();

        $method = $reflection->getMethod('insert');
        $params = $method->getParameters();

        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('data');
        expect($params[0]->getType()?->getName())->toBe('array');

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('int');
    });

    it('defines update() method with data array', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('update'))->toBeTrue();

        $method = $reflection->getMethod('update');
        $params = $method->getParameters();

        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('data');
        expect($params[0]->getType()?->getName())->toBe('array');

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('int');
    });

    it('defines delete() method', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('delete'))->toBeTrue();

        $method = $reflection->getMethod('delete');
        $params = $method->getParameters();

        expect($params)->toHaveCount(0);

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('int');
    });

    it('defines count() method returning integer', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('count'))->toBeTrue();

        $method = $reflection->getMethod('count');
        $params = $method->getParameters();

        expect($params)->toHaveCount(0);

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('int');
    });

    it('defines raw() method for raw SQL with bindings', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('raw'))->toBeTrue();

        $method = $reflection->getMethod('raw');
        $params = $method->getParameters();

        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('sql');
        expect($params[0]->getType()?->getName())->toBe('string');
        expect($params[1]->getName())->toBe('bindings');
        expect($params[1]->getType()?->getName())->toBe('array');
        expect($params[1]->isDefaultValueAvailable())->toBeTrue();
        expect($params[1]->getDefaultValue())->toBe([]);

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('array');
    });
});
