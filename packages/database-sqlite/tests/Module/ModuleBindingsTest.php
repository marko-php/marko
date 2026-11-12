<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Module;

use Marko\Database\Connection\ConnectionFactoryInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionBackoff;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Sqlite\Connection\SqliteConnection;
use Marko\Database\Sqlite\Connection\SqliteConnectionFactory;
use Marko\Database\Sqlite\Introspection\SqliteIntrospector;
use Marko\Database\Sqlite\Query\SqliteQueryBuilder;
use Marko\Database\Sqlite\Query\SqliteQueryBuilderFactory;
use Marko\Database\Sqlite\Sql\SqliteGenerator;
use Marko\Database\Sqlite\Tests\Fixtures\SqliteContainer;
use ReflectionProperty;

describe('SQLite module.php bindings', function (): void {
    it('binds every database interface to the sqlite implementation and shares one connection', function (): void {
        $container = SqliteContainer::build(SqliteContainer::config());
        $connection = $container->get(ConnectionInterface::class);

        $queryBuilderFactory = $container->get(QueryBuilderFactoryInterface::class);
        $factoryConnection = new ReflectionProperty($queryBuilderFactory, 'connection')->getValue(
            $queryBuilderFactory,
        );
        $introspector = $container->get(IntrospectorInterface::class);
        $introspectorConnection = new ReflectionProperty($introspector, 'connection')->getValue($introspector);

        expect($connection)->toBeInstanceOf(SqliteConnection::class)
            ->and($container->get(ConnectionInterface::class))->toBe($connection)
            ->and($container->get(TransactionInterface::class))->toBe($connection)
            ->and($container->get(ConnectionFactoryInterface::class))->toBeInstanceOf(SqliteConnectionFactory::class)
            ->and($container->get(SqlGeneratorInterface::class))->toBeInstanceOf(SqliteGenerator::class)
            ->and($introspector)->toBeInstanceOf(SqliteIntrospector::class)
            ->and($introspectorConnection)->toBe($connection)
            ->and($container->get(QueryBuilderInterface::class))->toBeInstanceOf(SqliteQueryBuilder::class)
            ->and($queryBuilderFactory)->toBeInstanceOf(SqliteQueryBuilderFactory::class)
            ->and($factoryConnection)->toBe($connection);
    });

    it('builds a driver connection from the database module without binding TransactionBackoff', function (): void {
        $container = SqliteContainer::build(SqliteContainer::config());
        $connection = $container->get(ConnectionInterface::class);
        $backoff = new ReflectionProperty($connection, 'transactionBackoff')->getValue($connection);
        $databaseModule = require dirname(__DIR__, 3) . '/database/module.php';

        expect($databaseModule['bindings'])->not->toHaveKey(TransactionBackoff::class)
            ->and($backoff)->toBeInstanceOf(TransactionBackoff::class);
    });
});
