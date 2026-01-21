<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Factory;

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\PgSql\Connection\PgSqlConnection;

class PgSqlConnectionFactory
{
    public function __construct(
        private readonly DatabaseConfig $config,
    ) {}

    public function create(): ConnectionInterface
    {
        return new PgSqlConnection(
            host: $this->config->host,
            port: $this->config->port,
            database: $this->config->database,
            username: $this->config->username,
            password: $this->config->password,
        );
    }
}
