<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\PgSql\Connection\PgSqlConnection;

// Marko-specific configuration for this module.
// Name and version come from composer.json.

return [
    'enabled' => true,
    'bindings' => [
        ConnectionInterface::class => PgSqlConnection::class,
    ],
];
