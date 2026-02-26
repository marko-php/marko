<?php

declare(strict_types=1);

use Amp\Postgres\PostgresConfig;
use Amp\Postgres\PostgresConnection;
use Amp\Postgres\PostgresListener;
use Amp\Postgres\PostgresNotification;
use Amp\Postgres\PostgresResult;
use Amp\Postgres\PostgresStatement;
use Amp\Postgres\PostgresTransaction;
use Amp\Sql\SqlTransactionIsolation;
use Marko\PubSub\Exceptions\PubSubException;
use Marko\PubSub\PgSql\Driver\PgSqlSubscriber;
use Marko\PubSub\PgSql\PgSqlPubSubConnection;
use Marko\PubSub\PubSubConfig;
use Marko\PubSub\SubscriberInterface;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * Mock PostgresListener for testing — yields fixed notifications.
 */
class MockPostgresListener implements PostgresListener, IteratorAggregate
{
    /** @var PostgresNotification[] */
    private array $notifications;

    public bool $unlistenCalled = false;

    private string $channel;

    /** @param PostgresNotification[] $notifications */
    public function __construct(string $channel, array $notifications = [])
    {
        $this->channel = $channel;
        $this->notifications = $notifications;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function isListening(): bool
    {
        return !$this->unlistenCalled;
    }

    public function unlisten(): void
    {
        $this->unlistenCalled = true;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->notifications);
    }
}

/**
 * Stub PostgresConnection for subscriber testing — records listen calls and returns mock listeners.
 */
class SubscriberStubPostgresConnection implements PostgresConnection
{
    /** @var array<string, MockPostgresListener> */
    public array $listeners = [];

    /** @var PostgresNotification[] */
    private array $notificationsToReturn;

    /** @param PostgresNotification[] $notificationsToReturn */
    public function __construct(array $notificationsToReturn = [])
    {
        $this->notificationsToReturn = $notificationsToReturn;
    }

    public function listen(string $channel): PostgresListener
    {
        $listener = new MockPostgresListener($channel, $this->notificationsToReturn);
        $this->listeners[$channel] = $listener;

        return $listener;
    }

    public function notify(string $channel, string $payload = ''): PostgresResult
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

function createPgSqlSubscriberConfig(string $prefix = 'marko_'): PubSubConfig
{
    return new PubSubConfig(new FakeConfigRepository([
        'pubsub.driver' => 'pgsql',
        'pubsub.prefix' => $prefix,
    ]));
}

function createPgSqlSubscriberWithStub(
    ?SubscriberStubPostgresConnection $stub = null,
    string $prefix = 'marko_',
): PgSqlSubscriber {
    $stub ??= new SubscriberStubPostgresConnection();

    $connection = new class ($stub) extends PgSqlPubSubConnection
    {
        public function __construct(
            private readonly SubscriberStubPostgresConnection $stub,
        ) {
            parent::__construct();
        }

        protected function createConnection(): PostgresConnection
        {
            return $this->stub;
        }
    };

    $config = createPgSqlSubscriberConfig($prefix);

    return new PgSqlSubscriber($connection, $config);
}

it('creates PgSqlSubscriber implementing SubscriberInterface', function (): void {
    $subscriber = createPgSqlSubscriberWithStub();

    expect($subscriber)->toBeInstanceOf(SubscriberInterface::class);
});

it('subscribes to channels with prefix applied via LISTEN', function (): void {
    $stub = new SubscriberStubPostgresConnection();
    $subscriber = createPgSqlSubscriberWithStub($stub, 'marko_');

    $subscriber->subscribe('events', 'notifications');

    expect($stub->listeners)->toHaveCount(2)
        ->and(array_key_exists('marko_events', $stub->listeners))->toBeTrue()
        ->and(array_key_exists('marko_notifications', $stub->listeners))->toBeTrue();
});

it('throws PubSubException for psubscribe since Postgres does not support pattern subscriptions', function (): void {
    $subscriber = createPgSqlSubscriberWithStub();

    expect(fn () => $subscriber->psubscribe('events.*'))
        ->toThrow(PubSubException::class, "Pattern subscriptions are not supported by the 'pgsql' driver");
});
