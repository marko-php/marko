<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Database\Connection\ConnectionFactoryInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Exceptions\TransactionException;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Sqlite\Connection\SqliteConnection;
use Marko\Database\Sqlite\Connection\SqliteConnectionFactory;
use Marko\Database\Sqlite\Introspection\SqliteIntrospector;
use Marko\Database\Sqlite\Query\SqliteQueryBuilder;
use Marko\Database\Sqlite\Query\SqliteQueryBuilderFactory;
use Marko\Database\Sqlite\Sql\SqliteGenerator;

// Marko-specific configuration for this module.
// Name and version come from composer.json.

return [
    'bindings' => [
        ConnectionInterface::class => SqliteConnection::class,
        ConnectionFactoryInterface::class => SqliteConnectionFactory::class,
        SqlGeneratorInterface::class => SqliteGenerator::class,
        IntrospectorInterface::class => SqliteIntrospector::class,
        QueryBuilderInterface::class => SqliteQueryBuilder::class,
        QueryBuilderFactoryInterface::class => SqliteQueryBuilderFactory::class,
        // Transactions run on the shared connection, so they cover every
        // repository, query builder and service that injects ConnectionInterface.
        TransactionInterface::class => static function (ContainerInterface $container): TransactionInterface {
            $connection = $container->get(ConnectionInterface::class);

            if (!$connection instanceof TransactionInterface) {
                throw TransactionException::connectionDoesNotSupportTransactions($connection::class);
            }

            return $connection;
        },
    ],
    // One connection (one PDO handle) per container, shared by every consumer.
    'singletons' => [
        ConnectionInterface::class,
    ],
];
