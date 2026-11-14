<?php

declare(strict_types=1);

namespace Marko\PubSub\PgSql;

use function Amp\Postgres\connect;

use Amp\Postgres\PostgresConfig;

use Amp\Postgres\PostgresConnection;
use Marko\PubSub\Exceptions\PubSubException;

class PgSqlPubSubConnection
{
    /**
     * The libpq sslmode values. Only verify-full both encrypts and checks that the server certificate
     * belongs to the host; prefer (libpq's default when unset) falls back to plain text silently.
     */
    public const array SSL_MODES = ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'];

    private ?PostgresConnection $connection = null;

    /**
     * @throws PubSubException
     */
    public function __construct(
        public readonly string $host = '127.0.0.1',
        public readonly int $port = 5432,
        public readonly ?string $user = null,
        public readonly ?string $password = null,
        public readonly ?string $database = null,
        public readonly string $prefix = 'marko_',
        public readonly ?string $sslMode = null,
    ) {
        if (
            $sslMode !== null
            && !in_array($sslMode, self::SSL_MODES, true)
        ) {
            throw PubSubException::invalidConnectionOption('pubsub-pgsql.sslmode', $sslMode, self::SSL_MODES);
        }
    }

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
            sslMode: $this->sslMode,
        );
    }

    protected function createConnection(): PostgresConnection
    {
        return connect($this->createConfig());
    }
}
