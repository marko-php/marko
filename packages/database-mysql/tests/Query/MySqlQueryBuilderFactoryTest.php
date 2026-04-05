<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Query;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\MySql\Query\MySqlQueryBuilder;
use Marko\Database\MySql\Query\MySqlQueryBuilderFactory;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;
use ReflectionClass;

describe('MySqlQueryBuilderFactory', function (): void {
    it('implements QueryBuilderFactoryInterface', function (): void {
        $reflection = new ReflectionClass(MySqlQueryBuilderFactory::class);

        expect($reflection->implementsInterface(QueryBuilderFactoryInterface::class))->toBeTrue();
    });

    it('accepts ConnectionInterface via constructor', function (): void {
        $reflection = new ReflectionClass(MySqlQueryBuilderFactory::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();

        expect($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('connection')
            ->and($params[0]->getType()->getName())->toBe(ConnectionInterface::class);
    });

    it('creates MySqlQueryBuilder instances', function (): void {
        $connection = $this->createMock(ConnectionInterface::class);
        $factory = new MySqlQueryBuilderFactory($connection);

        $builder = $factory->create();

        expect($builder)->toBeInstanceOf(QueryBuilderInterface::class)
            ->and($builder)->toBeInstanceOf(MySqlQueryBuilder::class);
    });

    it('creates a new instance on each call', function (): void {
        $connection = $this->createMock(ConnectionInterface::class);
        $factory = new MySqlQueryBuilderFactory($connection);

        $builder1 = $factory->create();
        $builder2 = $factory->create();

        expect($builder1)->not->toBe($builder2);
    });
});
