<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\MySql\Connection\MySqlConnection;

// Marko-specific configuration for this module.
// Name and version come from composer.json.

return [
    'enabled' => true,
    'bindings' => [
        ConnectionInterface::class => MySqlConnection::class,
    ],
];
