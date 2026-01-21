<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Factory;

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\MySql\Connection\MySqlConnection;

class MySqlConnectionFactory
{
    public function __construct(
        private readonly DatabaseConfig $config,
    ) {}

    public function create(): MySqlConnection
    {
        return new MySqlConnection(
            host: $this->config->host,
            port: $this->config->port,
            database: $this->config->database,
            username: $this->config->username,
            password: $this->config->password,
        );
    }
}
