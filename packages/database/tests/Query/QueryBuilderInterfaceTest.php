<?php

declare(strict_types=1);

use Marko\Database\Query\QueryBuilderInterface;

describe('QueryBuilderInterface', function (): void {
    it('defines table() method to set target table', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('table'))->toBeTrue();

        $method = $reflection->getMethod('table');
        $params = $method->getParameters();

        expect($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('table')
            ->and($params[0]->getType()?->getName())->toBe('string');

        // Returns self for fluent chaining
        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('static');
    });

    it('defines select() method for column selection', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('select'))->toBeTrue();

        $method = $reflection->getMethod('select');
        $params = $method->getParameters();

        expect($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('columns')
            ->and($params[0]->isVariadic())->toBeTrue()
            ->and($params[0]->getType()?->getName())->toBe('string');

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('static');
    });

    it('defines where() method with column, operator, value', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('where'))->toBeTrue();

        $method = $reflection->getMethod('where');
        $params = $method->getParameters();

        expect($params)->toHaveCount(3)
            ->and($params[0]->getName())->toBe('column')
            ->and($params[0]->getType()?->getName())->toBe('string')
            ->and($params[1]->getName())->toBe('operator')
            ->and($params[1]->getType()?->getName())->toBe('string')
            ->and($params[2]->getName())->toBe('value')
            ->and($params[2]->getType()?->getName())->toBe('mixed');

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('static');
    });

    it('defines whereIn() method for IN clauses', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('whereIn'))->toBeTrue();

        $method = $reflection->getMethod('whereIn');
        $params = $method->getParameters();

        expect($params)->toHaveCount(2)
            ->and($params[0]->getName())->toBe('column')
            ->and($params[0]->getType()?->getName())->toBe('string')
            ->and($params[1]->getName())->toBe('values')
            ->and($params[1]->getType()?->getName())->toBe('array');

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('static');
    });

    it('defines whereNull() and whereNotNull() methods', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('whereNull'))->toBeTrue()
            ->and($reflection->hasMethod('whereNotNull'))->toBeTrue();

        $whereNull = $reflection->getMethod('whereNull');
        $whereNullParams = $whereNull->getParameters();
        expect($whereNullParams)->toHaveCount(1)
            ->and($whereNullParams[0]->getName())->toBe('column')
            ->and($whereNullParams[0]->getType()?->getName())->toBe('string')
            ->and($whereNull->getReturnType()?->getName())->toBe('static');

        $whereNotNull = $reflection->getMethod('whereNotNull');
        $whereNotNullParams = $whereNotNull->getParameters();
        expect($whereNotNullParams)->toHaveCount(1)
            ->and($whereNotNullParams[0]->getName())->toBe('column')
            ->and($whereNotNullParams[0]->getType()?->getName())->toBe('string')
            ->and($whereNotNull->getReturnType()?->getName())->toBe('static');
    });

    it('defines orWhere() for OR conditions', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('orWhere'))->toBeTrue();

        $method = $reflection->getMethod('orWhere');
        $params = $method->getParameters();

        expect($params)->toHaveCount(3)
            ->and($params[0]->getName())->toBe('column')
            ->and($params[0]->getType()?->getName())->toBe('string')
            ->and($params[1]->getName())->toBe('operator')
            ->and($params[1]->getType()?->getName())->toBe('string')
            ->and($params[2]->getName())->toBe('value')
            ->and($params[2]->getType()?->getName())->toBe('mixed');

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('static');
    });

    it('defines join(), leftJoin(), rightJoin() methods', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('join'))->toBeTrue()
            ->and($reflection->hasMethod('leftJoin'))->toBeTrue()
            ->and($reflection->hasMethod('rightJoin'))->toBeTrue();

        // Check join() method
        $join = $reflection->getMethod('join');
        $joinParams = $join->getParameters();
        expect($joinParams)->toHaveCount(4)
            ->and($joinParams[0]->getName())->toBe('table')
            ->and($joinParams[0]->getType()?->getName())->toBe('string')
            ->and($joinParams[1]->getName())->toBe('first')
            ->and($joinParams[1]->getType()?->getName())->toBe('string')
            ->and($joinParams[2]->getName())->toBe('operator')
            ->and($joinParams[2]->getType()?->getName())->toBe('string')
            ->and($joinParams[3]->getName())->toBe('second')
            ->and($joinParams[3]->getType()?->getName())->toBe('string')
            ->and($join->getReturnType()?->getName())->toBe('static');

        // Check leftJoin() method
        $leftJoin = $reflection->getMethod('leftJoin');
        $leftJoinParams = $leftJoin->getParameters();
        expect($leftJoinParams)->toHaveCount(4)
            ->and($leftJoinParams[0]->getName())->toBe('table')
            ->and($leftJoinParams[1]->getName())->toBe('first')
            ->and($leftJoinParams[2]->getName())->toBe('operator')
            ->and($leftJoinParams[3]->getName())->toBe('second')
            ->and($leftJoin->getReturnType()?->getName())->toBe('static');

        // Check rightJoin() method
        $rightJoin = $reflection->getMethod('rightJoin');
        $rightJoinParams = $rightJoin->getParameters();
        expect($rightJoinParams)->toHaveCount(4)
            ->and($rightJoinParams[0]->getName())->toBe('table')
            ->and($rightJoinParams[1]->getName())->toBe('first')
            ->and($rightJoinParams[2]->getName())->toBe('operator')
            ->and($rightJoinParams[3]->getName())->toBe('second')
            ->and($rightJoin->getReturnType()?->getName())->toBe('static');
    });

    it('defines orderBy() method with direction', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('orderBy'))->toBeTrue();

        $method = $reflection->getMethod('orderBy');
        $params = $method->getParameters();

        expect($params)->toHaveCount(2)
            ->and($params[0]->getName())->toBe('column')
            ->and($params[0]->getType()?->getName())->toBe('string')
            ->and($params[1]->getName())->toBe('direction')
            ->and($params[1]->getType()?->getName())->toBe('string')
            ->and($params[1]->isDefaultValueAvailable())->toBeTrue()
            ->and($params[1]->getDefaultValue())->toBe('ASC');

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('static');
    });

    it('defines limit() and offset() methods', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('limit'))->toBeTrue()
            ->and($reflection->hasMethod('offset'))->toBeTrue();

        $limit = $reflection->getMethod('limit');
        $limitParams = $limit->getParameters();
        expect($limitParams)->toHaveCount(1)
            ->and($limitParams[0]->getName())->toBe('limit')
            ->and($limitParams[0]->getType()?->getName())->toBe('int')
            ->and($limit->getReturnType()?->getName())->toBe('static');

        $offset = $reflection->getMethod('offset');
        $offsetParams = $offset->getParameters();
        expect($offsetParams)->toHaveCount(1)
            ->and($offsetParams[0]->getName())->toBe('offset')
            ->and($offsetParams[0]->getType()?->getName())->toBe('int')
            ->and($offset->getReturnType()?->getName())->toBe('static');
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

        expect($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('data')
            ->and($params[0]->getType()?->getName())->toBe('array');

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('int');
    });

    it('defines update() method with data array', function (): void {
        $reflection = new ReflectionClass(QueryBuilderInterface::class);

        expect($reflection->hasMethod('update'))->toBeTrue();

        $method = $reflection->getMethod('update');
        $params = $method->getParameters();

        expect($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('data')
            ->and($params[0]->getType()?->getName())->toBe('array');

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

        expect($params)->toHaveCount(2)
            ->and($params[0]->getName())->toBe('sql')
            ->and($params[0]->getType()?->getName())->toBe('string')
            ->and($params[1]->getName())->toBe('bindings')
            ->and($params[1]->getType()?->getName())->toBe('array')
            ->and($params[1]->isDefaultValueAvailable())->toBeTrue()
            ->and($params[1]->getDefaultValue())->toBe([]);

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('array');
    });
});
