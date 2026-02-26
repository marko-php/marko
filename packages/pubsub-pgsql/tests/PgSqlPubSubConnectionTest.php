<?php

declare(strict_types=1);

use Amp\Postgres\PostgresConfig;
use Amp\Postgres\PostgresConnection;
use Amp\Postgres\PostgresListener;
use Amp\Postgres\PostgresResult;
use Amp\Postgres\PostgresStatement;
use Amp\Postgres\PostgresTransaction;
use Amp\Sql\SqlTransactionIsolation;
use Marko\PubSub\PgSql\PgSqlPubSubConnection;

/**
 * Stub PostgresConnection for connection testing — minimal implementation.
 */
class ConnectionStubPostgresConnection implements PostgresConnection
{
    public function notify(string $channel, string $payload = ''): PostgresResult
    {
        throw new RuntimeException('Not implemented in stub');
    }

    public function listen(string $channel): PostgresListener
    {
        throw new RuntimeException('Not implemented in stub');
    }

    public function getConfig(): PostgresConfig
    {
        throw new RuntimeException('Not implemented in stub');
    }

    public function query(string $sql): PostgresResult
    {
        throw new RuntimeException('Not implemented in stub');
    }

    public function prepare(string $sql): PostgresStatement
    {
        throw new RuntimeException('Not implemented in stub');
    }

    public function execute(string $sql, array $params = []): PostgresResult
    {
        throw new RuntimeException('Not implemented in stub');
    }

    public function quoteLiteral(string $data): string
    {
        throw new RuntimeException('Not implemented in stub');
    }

    public function quoteIdentifier(string $name): string
    {
        throw new RuntimeException('Not implemented in stub');
    }

    public function escapeByteA(string $data): string
    {
        throw new RuntimeException('Not implemented in stub');
    }

    public function beginTransaction(): PostgresTransaction
    {
        throw new RuntimeException('Not implemented in stub');
    }

    public function isClosed(): bool
    {
        return false;
    }

    public function onClose(Closure $onClose): void {}

    public function close(): void {}

    public function getLastUsedAt(): int
    {
        return time();
    }

    public function getTransactionIsolation(): SqlTransactionIsolation
    {
        throw new RuntimeException('Not implemented in stub');
    }

    public function setTransactionIsolation(SqlTransactionIsolation $isolation): void {}
}

/**
 * Testable connection subclass that exposes createConfig() publicly.
 */
class ConfigExposingConnection extends PgSqlPubSubConnection
{
    public function __construct(
        string $host = '127.0.0.1',
        int $port = 5432,
        ?string $user = null,
        ?string $password = null,
        ?string $database = null,
    ) {
        parent::__construct(
            host: $host,
            port: $port,
            user: $user,
            password: $password,
            database: $database,
        );
    }

    public function exposeCreateConfig(): PostgresConfig
    {
        return $this->createConfig();
    }
}

function createTestablePgSqlConnection(
    ?ConnectionStubPostgresConnection $stub = null,
): PgSqlPubSubConnection {
    $stub ??= new ConnectionStubPostgresConnection();

    return new class ($stub) extends PgSqlPubSubConnection
    {
        public function __construct(
            private readonly ConnectionStubPostgresConnection $stub,
        ) {
            parent::__construct();
        }

        protected function createConnection(): PostgresConnection
        {
            return $this->stub;
        }
    };
}

it('creates PgSqlPubSubConnection with host, port, user, password, database properties', function (): void {
    $connection = new PgSqlPubSubConnection();

    expect($connection->host)->toBe('127.0.0.1')
        ->and($connection->port)->toBe(5432)
        ->and($connection->user)->toBeNull()
        ->and($connection->password)->toBeNull()
        ->and($connection->database)->toBeNull()
        ->and($connection->prefix)->toBe('marko_');
});

it('creates PostgresConfig lazily via protected createConfig hook', function (): void {
    $connection = new ConfigExposingConnection(
        host: 'db.example.com',
        port: 5433,
        user: 'myuser',
        password: 'mypass',
        database: 'mydb',
    );

    $config = $connection->exposeCreateConfig();

    expect($config)->toBeInstanceOf(PostgresConfig::class)
        ->and($config->getHost())->toBe('db.example.com')
        ->and($config->getPort())->toBe(5433)
        ->and($config->getUser())->toBe('myuser')
        ->and($config->getPassword())->toBe('mypass')
        ->and($config->getDatabase())->toBe('mydb');
});

it('creates connection lazily via protected createConnection hook', function (): void {
    $stub = new ConnectionStubPostgresConnection();
    $connection = createTestablePgSqlConnection($stub);

    expect($connection->isConnected())->toBeFalse();

    $conn = $connection->connection();

    expect($connection->isConnected())->toBeTrue()
        ->and($conn)->toBeInstanceOf(PostgresConnection::class);
});

it('provides disconnect and isConnected methods', function (): void {
    $connection = createTestablePgSqlConnection();

    expect($connection->isConnected())->toBeFalse();

    $connection->connection();
    expect($connection->isConnected())->toBeTrue();

    $connection->disconnect();
    expect($connection->isConnected())->toBeFalse();
});
