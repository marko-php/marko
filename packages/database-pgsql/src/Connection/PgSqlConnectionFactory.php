<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Connection;

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionFactoryInterface;
use Marko\Database\Connection\ConnectionInterface;

readonly class PgSqlConnectionFactory implements ConnectionFactoryInterface
{
    public function __construct(private string $charset = 'utf8') {}

    public function make(DatabaseConfig $config): ConnectionInterface
    {
        return new PgSqlConnection($config, $this->charset);
    }
}
