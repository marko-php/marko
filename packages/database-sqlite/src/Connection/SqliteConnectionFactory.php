<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Connection;

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionFactoryInterface;
use Marko\Database\Connection\ConnectionInterface;

readonly class SqliteConnectionFactory implements ConnectionFactoryInterface
{
    public function make(DatabaseConfig $config): ConnectionInterface
    {
        return new SqliteConnection($config);
    }
}
