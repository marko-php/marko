<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Mail\Message;
use Marko\Notification\Channel\DatabaseChannel;
use Marko\Notification\Channel\MailChannel;
use Marko\Notification\Contracts\BatchChannelInterface;
use Marko\Notification\Contracts\ChannelInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Notification\Exceptions\ChannelException;

// Hand-written stub for ConnectionInterface that records execute() calls
class BatchTestConnection implements ConnectionInterface
{
    /** @var array<int, array{sql: string, bindings: array<int, mixed>}> */
    public array $executeCalls = [];

    public function connect(): void {}

    public function disconnect(): void {}

    public function isConnected(): bool
    {
        return true;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        return [];
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->executeCalls[] = ['sql' => $sql, 'bindings' => $bindings];

        return count($bindings) / 7;
    }

    public function prepare(string $sql): StatementInterface
    {
        throw new RuntimeException('Not implemented in test stub');
    }

    public function lastInsertId(): int
    {
        return 0;
    }

    public function driverName(): string
    {
        return 'mysql';
    }
}

function makeBatchNotifiable(string $type = 'App\\Entity\\User', int|string $id = 1): NotifiableInterface
{
    return new readonly class ($type, $id) implements NotifiableInterface
    {
        public function __construct(
            private string $type,
            private int|string $id,
        ) {}

        public function routeNotificationFor(string $channel): mixed
        {
            return null;
        }

        public function getNotifiableType(): string
        {
            return $this->type;
        }

        public function getNotifiableId(): int|string
        {
            return $this->id;
        }
    };
}

function makeNotification(array $data = ['message' => 'Hello']): NotificationInterface
{
    return new readonly class ($data) implements NotificationInterface
    {
        public function __construct(
            private array $data,
        ) {}

        /** @return array<string> */
        public function channels(NotifiableInterface $notifiable): array
        {
            return ['database'];
        }

        public function toMail(NotifiableInterface $notifiable): Message
        {
            return new Message();
        }

        /** @return array<string, mixed> */
        public function toDatabase(NotifiableInterface $notifiable): array
        {
            return $this->data;
        }
    };
}

test('it persists a notification for every recipient on the database channel', function (): void {
    $connection = new BatchTestConnection();
    $channel = new DatabaseChannel($connection);
    $notification = makeNotification();

    $notifiables = [
        makeBatchNotifiable(id: 1),
        makeBatchNotifiable(id: 2),
        makeBatchNotifiable(id: 3),
    ];

    $channel->sendMany($notifiables, $notification);

    // All 3 recipients should be persisted
    $totalBindings = array_merge(...array_column($connection->executeCalls, 'bindings'));
    $totalRows = count($totalBindings) / 7;

    expect($totalRows)->toBe(3);
});

test('it issues a single multi-row insert when all recipients fit one chunk', function (): void {
    $connection = new BatchTestConnection();
    $channel = new DatabaseChannel($connection);
    $notification = makeNotification();

    $notifiables = [
        makeBatchNotifiable(id: 1),
        makeBatchNotifiable(id: 2),
        makeBatchNotifiable(id: 3),
    ];

    $channel->sendMany($notifiables, $notification);

    expect($connection->executeCalls)->toHaveCount(1);
});

test('it issues one insert per chunk when recipients exceed the chunk size', function (): void {
    $connection = new BatchTestConnection();
    $channel = new DatabaseChannel($connection);
    $notification = makeNotification();

    // Create enough notifiables to exceed one chunk
    // Chunk size is based on placeholder limit; use reflection to get it
    $reflection = new ReflectionClass(DatabaseChannel::class);
    $chunkSizeConst = $reflection->getConstant('ROWS_PER_CHUNK');

    $notifiables = [];
    for ($i = 1; $i <= $chunkSizeConst + 1; $i++) {
        $notifiables[] = makeBatchNotifiable(id: $i);
    }

    $channel->sendMany($notifiables, $notification);

    expect($connection->executeCalls)->toHaveCount(2);
});

test('it writes the same column data per row as the single-recipient send', function (): void {
    $connection = new BatchTestConnection();
    $channel = new DatabaseChannel($connection);
    $notification = makeNotification(['key' => 'value', 'order_id' => 42]);

    $notifiable = makeBatchNotifiable(type: 'App\\Entity\\User', id: 99);

    $channel->sendMany([$notifiable, makeBatchNotifiable(id: 2)], $notification);

    $bindings = $connection->executeCalls[0]['bindings'];
    // First row bindings: id, type, notifiable_type, notifiable_id, data, read_at, created_at
    $firstRowBindings = array_slice($bindings, 0, 7);

    expect($firstRowBindings[1])->toBeString() // type = notification class name
        ->and($firstRowBindings[2])->toBe('App\\Entity\\User') // notifiable_type
        ->and($firstRowBindings[3])->toBe('99') // notifiable_id as string
        ->and(json_decode($firstRowBindings[4], true))->toBe(['key' => 'value', 'order_id' => 42]) // data JSON
        ->and($firstRowBindings[5])->toBeNull() // read_at
        ->and($firstRowBindings[6])->toBeString(); // created_at
});

test('it generates a distinct id for each persisted notification row', function (): void {
    $connection = new BatchTestConnection();
    $channel = new DatabaseChannel($connection);
    $notification = makeNotification();

    $notifiables = [
        makeBatchNotifiable(id: 1),
        makeBatchNotifiable(id: 2),
        makeBatchNotifiable(id: 3),
    ];

    $channel->sendMany($notifiables, $notification);

    $bindings = $connection->executeCalls[0]['bindings'];

    // Extract UUIDs (index 0, 7, 14 for 3 rows of 7 columns)
    $uuids = [];
    for ($i = 0; $i < 3; $i++) {
        $uuids[] = $bindings[$i * 7];
    }

    expect(array_unique($uuids))->toHaveCount(3);
});

test('it wraps a batch insert failure in a channel exception', function (): void {
    $connection = new class () extends BatchTestConnection
    {
        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            throw new RuntimeException('DB connection lost');
        }
    };

    $channel = new DatabaseChannel($connection);
    $notification = makeNotification();

    $notifiables = [makeBatchNotifiable(id: 1), makeBatchNotifiable(id: 2)];

    expect(fn () => $channel->sendMany($notifiables, $notification))
        ->toThrow(ChannelException::class, "Failed to deliver notification via 'database' channel.");
});

test('it leaves MailChannel and other ChannelInterface implementations unchanged', function (): void {
    $reflection = new ReflectionClass(MailChannel::class);

    expect($reflection->implementsInterface(BatchChannelInterface::class))->toBeFalse()
        ->and($reflection->implementsInterface(ChannelInterface::class))->toBeTrue();
});
