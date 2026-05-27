<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Connection;

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionFactoryInterface;
use Marko\Database\Connection\ConnectionInterface;

readonly class MySqlConnectionFactory implements ConnectionFactoryInterface
{
    public function __construct(private string $charset = 'utf8mb4') {}

    public function make(DatabaseConfig $config): ConnectionInterface
    {
        return new MySqlConnection($config, $this->charset);
    }
}
