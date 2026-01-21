<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\MySql\Factory\MySqlConnectionFactory;

// Marko-specific configuration for this module.
// Name and version come from composer.json.

return [
    'enabled' => true,
    'bindings' => [
        ConnectionInterface::class => function (ContainerInterface $container): ConnectionInterface {
            return $container->get(MySqlConnectionFactory::class)->create();
        },
    ],
];
