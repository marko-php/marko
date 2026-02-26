<?php

declare(strict_types=1);

use Amp\Postgres\PostgresConfig;
use Amp\Postgres\PostgresConnection;
use Amp\Postgres\PostgresListener;
use Amp\Postgres\PostgresResult;
use Amp\Postgres\PostgresStatement;
use Amp\Postgres\PostgresTransaction;
use Amp\Sql\SqlTransactionIsolation;
use Marko\PubSub\Message;
use Marko\PubSub\PgSql\Driver\PgSqlPublisher;
use Marko\PubSub\PgSql\PgSqlPubSubConnection;
use Marko\PubSub\PublisherInterface;
use Marko\PubSub\PubSubConfig;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * Stub PostgresConnection for publisher testing — records notify calls.
 */
class PublisherStubPostgresConnection implements PostgresConnection
{
    /** @var array<int, array{channel: string, payload: string}> */
    public array $notifyCalls = [];

    public function notify(string $channel, string $payload = ''): PostgresResult
    {
        $this->notifyCalls[] = ['channel' => $channel, 'payload' => $payload];

        return new PublisherStubPostgresResult();
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
 * Stub PostgresResult for publisher testing.
 */
class PublisherStubPostgresResult implements PostgresResult, IteratorAggregate
{
    public function fetchRow(): ?array
    {
        return null;
    }

    public function getNextResult(): ?PostgresResult
    {
        return null;
    }

    public function getRowCount(): ?int
    {
        return null;
    }

    public function getColumnCount(): ?int
    {
        return null;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator([]);
    }
}

function createPgSqlPubSubConfig(string $prefix = 'marko_'): PubSubConfig
{
    return new PubSubConfig(new FakeConfigRepository([
        'pubsub.driver' => 'pgsql',
        'pubsub.prefix' => $prefix,
    ]));
}

function createPgSqlPublisherWithStub(
    ?PublisherStubPostgresConnection $stub = null,
    string $prefix = 'marko_',
): PgSqlPublisher {
    $stub ??= new PublisherStubPostgresConnection();

    $connection = new class ($stub) extends PgSqlPubSubConnection
    {
        public function __construct(
            private readonly PublisherStubPostgresConnection $stub,
        ) {
            parent::__construct();
        }

        protected function createConnection(): PostgresConnection
        {
            return $this->stub;
        }
    };

    $config = createPgSqlPubSubConfig($prefix);

    return new PgSqlPublisher($connection, $config);
}

it('creates PgSqlPublisher implementing PublisherInterface', function (): void {
    $publisher = createPgSqlPublisherWithStub();

    expect($publisher)->toBeInstanceOf(PublisherInterface::class);
});

it('publishes message via NOTIFY with prefixed channel name', function (): void {
    $stub = new PublisherStubPostgresConnection();
    $publisher = createPgSqlPublisherWithStub($stub, 'marko_');

    $message = new Message(channel: 'events', payload: '{"type":"user.created"}');
    $publisher->publish('events', $message);

    expect($stub->notifyCalls)->toHaveCount(1)
        ->and($stub->notifyCalls[0]['channel'])->toBe('marko_events')
        ->and($stub->notifyCalls[0]['payload'])->toBe('{"type":"user.created"}');
});
