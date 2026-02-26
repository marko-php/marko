<?php

declare(strict_types=1);

namespace Marko\PubSub\PgSql;

use Amp\Postgres\PostgresConfig;
use Amp\Postgres\PostgresConnection;

use function Amp\Postgres\connect;

class PgSqlPubSubConnection
{
    private ?PostgresConnection $connection = null;

    public function __construct(
        public readonly string $host = '127.0.0.1',
        public readonly int $port = 5432,
        public readonly ?string $user = null,
        public readonly ?string $password = null,
        public readonly ?string $database = null,
        public readonly string $prefix = 'marko_',
    ) {}

    public function connection(): PostgresConnection
    {
        if ($this->connection === null) {
            $this->connection = $this->createConnection();
        }

        return $this->connection;
    }

    public function disconnect(): void
    {
        $this->connection = null;
    }

    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    protected function createConfig(): PostgresConfig
    {
        return new PostgresConfig(
            host: $this->host,
            port: $this->port,
            user: $this->user,
            password: $this->password,
            database: $this->database,
        );
    }

    protected function createConnection(): PostgresConnection
    {
        return connect($this->createConfig());
    }
}
