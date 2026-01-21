<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\PgSql\Factory\PgSqlConnectionFactory;
use Marko\Database\PgSql\Introspection\PgSqlIntrospector;
use Marko\Database\PgSql\Query\PgSqlQueryBuilder;
use Marko\Database\PgSql\Sql\PgSqlGenerator;
use Marko\Database\Query\QueryBuilderInterface;

// Marko-specific configuration for this module.
// Name and version come from composer.json.

return [
    'enabled' => true,
    'bindings' => [
        ConnectionInterface::class => function (ContainerInterface $container): ConnectionInterface {
            return $container->get(PgSqlConnectionFactory::class)->create();
        },
        SqlGeneratorInterface::class => PgSqlGenerator::class,
        IntrospectorInterface::class => PgSqlIntrospector::class,
        QueryBuilderInterface::class => PgSqlQueryBuilder::class,
    ],
];
