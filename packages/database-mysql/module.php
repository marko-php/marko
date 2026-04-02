<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\MySql\Connection\MySqlConnection;
use Marko\Database\MySql\Introspection\MySqlIntrospector;
use Marko\Database\MySql\Query\MySqlQueryBuilder;
use Marko\Database\MySql\Query\MySqlQueryBuilderFactory;
use Marko\Database\MySql\Sql\MySqlGenerator;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;

// Marko-specific configuration for this module.
// Name and version come from composer.json.

return [
    'bindings' => [
        ConnectionInterface::class => MySqlConnection::class,
        IntrospectorInterface::class => function (ContainerInterface $container): IntrospectorInterface {
            $config = $container->get(DatabaseConfig::class);

            return new MySqlIntrospector(
                $container->get(ConnectionInterface::class),
                $config->database,
            );
        },
        SqlGeneratorInterface::class => MySqlGenerator::class,
        QueryBuilderInterface::class => MySqlQueryBuilder::class,
        QueryBuilderFactoryInterface::class => MySqlQueryBuilderFactory::class,
    ],
];
