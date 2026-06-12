<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\Database\DatabaseQueue;
use Marko\Queue\Database\Tests\Fixtures\TestJob;
use Marko\Queue\Exceptions\SerializationException;
use Marko\Queue\JobEnvelope;
use Marko\Queue\QueueInterface;
use Marko\Testing\Fake\FakeConfigRepository;

function createTestEnvelope(
    string $key = 'test-hmac-key-for-queue-database',
): JobEnvelope {
    return new JobEnvelope(new EncryptionConfig(new FakeConfigRepository(['encryption.key' => $key])));
}

test('DatabaseQueue implements QueueInterface', function () {
    $connection = $this->createMock(ConnectionInterface::class);
    $queue = new DatabaseQueue($connection, createTestEnvelope());

    expect($queue)->toBeInstanceOf(QueueInterface::class);
});

test('DatabaseQueue push stores job in database', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $connection->expects($this->once())
        ->method('execute')
        ->with(
            $this->stringContains('INSERT INTO jobs'),
            $this->callback(function (array $bindings) {
                return isset($bindings['id'])
                    && isset($bindings['queue'])
                    && isset($bindings['payload'])
                    && isset($bindings['attempts'])
                    && isset($bindings['available_at'])
                    && isset($bindings['created_at'])
                    && $bindings['queue'] === 'default'
                    && $bindings['attempts'] === 0;
            }),
        )
        ->willReturn(1);

    $job = new TestJob('test message');

    $queue = new DatabaseQueue($connection, createTestEnvelope());
    $queue->push($job);
});

test('DatabaseQueue push returns job ID', function () {
    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('execute')->willReturn(1);

    $job = new TestJob('test message');

    $queue = new DatabaseQueue($connection, createTestEnvelope());
    $id = $queue->push($job);

    expect($id)->toBeString()
        ->toMatch('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/')
        ->and($job->id)->toBe($id);
});

test('DatabaseQueue later stores job with future available_at', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $capturedBindings = [];
    $connection->expects($this->once())
        ->method('execute')
        ->with(
            $this->stringContains('INSERT INTO jobs'),
            $this->callback(function (array $bindings) use (&$capturedBindings) {
                $capturedBindings = $bindings;

                return true;
            }),
        )
        ->willReturn(1);

    $job = new TestJob('delayed job');

    $queue = new DatabaseQueue($connection, createTestEnvelope());
    $delay = 60; // 60 seconds
    $beforeTime = new DateTimeImmutable();
    $id = $queue->later($delay, $job);
    $afterTime = new DateTimeImmutable();

    expect($id)->toBeString();

    $availableAt = new DateTimeImmutable($capturedBindings['available_at']);
    $expectedMin = $beforeTime->modify('+59 seconds');
    $expectedMax = $afterTime->modify('+61 seconds');

    expect($availableAt >= $expectedMin)->toBeTrue('available_at should be at least 59 seconds in future')
        ->and($availableAt <= $expectedMax)->toBeTrue('available_at should be at most 61 seconds in future');
});

test('DatabaseQueue pop retrieves and reserves next job', function () {
    $envelope = createTestEnvelope();
    $connection = $this->createMock(ConnectionInterface::class);

    $job = new TestJob('pop test');
    $wrappedPayload = $envelope->wrap($job->serialize());

    // First query: SELECT to find next available job
    // Second execute: UPDATE to reserve the job
    $connection->expects($this->once())
        ->method('query')
        ->with(
            $this->stringContains('SELECT'),
            $this->callback(function (array $bindings) {
                return isset($bindings['queue']);
            }),
        )
        ->willReturn([
            [
                'id' => 'job-123',
                'queue' => 'default',
                'payload' => $wrappedPayload,
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => '2024-01-01 00:00:00',
                'created_at' => '2024-01-01 00:00:00',
            ],
        ]);

    $connection->expects($this->once())
        ->method('execute')
        ->with(
            $this->stringContains('UPDATE'),
            $this->callback(function (array $bindings) {
                return isset($bindings['reserved_at'])
                    && isset($bindings['attempts'])
                    && $bindings['attempts'] === 1
                    && $bindings['id'] === 'job-123';
            }),
        )
        ->willReturn(1);

    $queue = new DatabaseQueue($connection, $envelope);
    $poppedJob = $queue->pop();

    expect($poppedJob)->toBeInstanceOf(TestJob::class)
        ->and($poppedJob->id)->toBe('job-123')
        ->and($poppedJob->attempts)->toBe(1);
});

test('DatabaseQueue pop returns null when empty', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $connection->expects($this->once())
        ->method('query')
        ->willReturn([]);

    $connection->expects($this->never())
        ->method('execute');

    $queue = new DatabaseQueue($connection, createTestEnvelope());
    $result = $queue->pop();

    expect($result)->toBeNull();
});

test('DatabaseQueue size returns pending job count', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $connection->expects($this->once())
        ->method('query')
        ->with(
            $this->stringContains('COUNT'),
            $this->callback(function (array $bindings) {
                return isset($bindings['queue']) && $bindings['queue'] === 'default';
            }),
        )
        ->willReturn([['count' => 5]]);

    $queue = new DatabaseQueue($connection, createTestEnvelope());
    $size = $queue->size();

    expect($size)->toBe(5);
});

test('DatabaseQueue clear removes all jobs', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $connection->expects($this->once())
        ->method('execute')
        ->with(
            $this->stringContains('DELETE'),
            $this->callback(function (array $bindings) {
                return isset($bindings['queue']) && $bindings['queue'] === 'default';
            }),
        )
        ->willReturn(10);

    $queue = new DatabaseQueue($connection, createTestEnvelope());
    $cleared = $queue->clear();

    expect($cleared)->toBe(10);
});

test('DatabaseQueue delete removes specific job', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $connection->expects($this->once())
        ->method('execute')
        ->with(
            $this->stringContains('DELETE'),
            $this->callback(function (array $bindings) {
                return isset($bindings['id']) && $bindings['id'] === 'job-123';
            }),
        )
        ->willReturn(1);

    $queue = new DatabaseQueue($connection, createTestEnvelope());
    $deleted = $queue->delete('job-123');

    expect($deleted)->toBeTrue();
});

test('DatabaseQueue delete returns false when job not found', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $connection->expects($this->once())
        ->method('execute')
        ->willReturn(0);

    $queue = new DatabaseQueue($connection, createTestEnvelope());
    $deleted = $queue->delete('nonexistent-job');

    expect($deleted)->toBeFalse();
});

test('DatabaseQueue release updates job availability', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $capturedBindings = [];
    $connection->expects($this->once())
        ->method('execute')
        ->with(
            $this->stringContains('UPDATE'),
            $this->callback(function (array $bindings) use (&$capturedBindings) {
                $capturedBindings = $bindings;

                return array_key_exists('id', $bindings)
                    && array_key_exists('reserved_at', $bindings)
                    && array_key_exists('available_at', $bindings)
                    && $bindings['id'] === 'job-123'
                    && $bindings['reserved_at'] === null;
            }),
        )
        ->willReturn(1);

    $queue = new DatabaseQueue($connection, createTestEnvelope());
    $beforeTime = new DateTimeImmutable();
    $released = $queue->release('job-123', 30);
    $afterTime = new DateTimeImmutable();

    expect($released)->toBeTrue();

    $availableAt = new DateTimeImmutable($capturedBindings['available_at']);
    $expectedMin = $beforeTime->modify('+29 seconds');
    $expectedMax = $afterTime->modify('+31 seconds');

    expect($availableAt >= $expectedMin)->toBeTrue()
        ->and($availableAt <= $expectedMax)->toBeTrue();
});

test('DatabaseQueue release with zero delay makes job immediately available', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $capturedBindings = [];
    $connection->expects($this->once())
        ->method('execute')
        ->with(
            $this->stringContains('UPDATE'),
            $this->callback(function (array $bindings) use (&$capturedBindings) {
                $capturedBindings = $bindings;

                return true;
            }),
        )
        ->willReturn(1);

    $queue = new DatabaseQueue($connection, createTestEnvelope());
    $beforeTime = new DateTimeImmutable();
    $released = $queue->release('job-123');
    $afterTime = new DateTimeImmutable();

    expect($released)->toBeTrue();

    $availableAt = new DateTimeImmutable($capturedBindings['available_at']);

    // Allow 1 second tolerance for timing differences
    expect($availableAt->getTimestamp())->toBeGreaterThanOrEqual($beforeTime->getTimestamp() - 1)
        ->and($availableAt->getTimestamp())->toBeLessThanOrEqual($afterTime->getTimestamp() + 1);
});

test('DatabaseQueue release returns false when job not found', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $connection->expects($this->once())
        ->method('execute')
        ->willReturn(0);

    $queue = new DatabaseQueue($connection, createTestEnvelope());
    $released = $queue->release('nonexistent-job');

    expect($released)->toBeFalse();
});

test('DatabaseQueue uses transactions for pop', function () {
    $envelope = createTestEnvelope();
    $job = new TestJob('transaction test');
    $wrappedPayload = $envelope->wrap($job->serialize());

    $transactionCalls = [];

    // Create a mock that implements both interfaces
    $connection = new class ($wrappedPayload, $transactionCalls) implements ConnectionInterface, TransactionInterface
    {
        private bool $inTransaction = false;

        public function __construct(
            private readonly string $serializedJob,
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property tracks calls in external variable */
            private array &$transactionCalls,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            $this->transactionCalls[] = ['operation' => 'query', 'inTransaction' => $this->inTransaction];

            return [
                [
                    'id' => 'job-tx-123',
                    'queue' => 'default',
                    'payload' => $this->serializedJob,
                    'attempts' => 0,
                    'reserved_at' => null,
                    'available_at' => '2024-01-01 00:00:00',
                    'created_at' => '2024-01-01 00:00:00',
                ],
            ];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            $this->transactionCalls[] = ['operation' => 'execute', 'inTransaction' => $this->inTransaction];

            return 1;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 1;
        }

        public function beginTransaction(): void
        {
            $this->transactionCalls[] = ['operation' => 'beginTransaction'];
            $this->inTransaction = true;
        }

        public function commit(): void
        {
            $this->transactionCalls[] = ['operation' => 'commit'];
            $this->inTransaction = false;
        }

        public function rollback(): void
        {
            $this->transactionCalls[] = ['operation' => 'rollback'];
            $this->inTransaction = false;
        }

        public function inTransaction(): bool
        {
            return $this->inTransaction;
        }

        public function transaction(
            callable $callback,
        ): mixed {
            $this->beginTransaction();
            try {
                $result = $callback();
                $this->commit();

                return $result;
            } catch (Throwable $e) {
                $this->rollback();
                throw $e;
            }
        }
    };

    $queue = new DatabaseQueue($connection, $envelope);
    $poppedJob = $queue->pop();

    expect($poppedJob)->not->toBeNull();

    // Verify transaction was used: beginTransaction, then query+execute inside transaction, then commit
    $operations = array_column($transactionCalls, 'operation');
    expect($operations)->toContain('beginTransaction')
        ->and($operations)->toContain('commit');

    // Verify query and execute happened inside the transaction
    $queryCall = array_filter($transactionCalls, fn ($c) => ($c['operation'] ?? '') === 'query');
    $executeCall = array_filter($transactionCalls, fn ($c) => ($c['operation'] ?? '') === 'execute');

    expect(array_values($queryCall)[0]['inTransaction'])->toBeTrue('Query should happen inside transaction')
        ->and(array_values($executeCall)[0]['inTransaction'])->toBeTrue('Execute should happen inside transaction');
});

test('DatabaseQueue respects available_at for delayed jobs', function () {
    $envelope = createTestEnvelope();
    $connection = $this->createMock(ConnectionInterface::class);

    $job = new TestJob('delayed job test');
    $wrappedPayload = $envelope->wrap($job->serialize());

    // Create two jobs: one immediately available, one delayed (future available_at)
    $now = new DateTimeImmutable();
    $pastTime = $now->modify('-1 minute')->format('Y-m-d H:i:s');

    // Capture the query bindings to verify the available_at condition
    $capturedQuery = [];
    $connection->expects($this->once())
        ->method('query')
        ->with(
            $this->callback(function (string $sql) use (&$capturedQuery) {
                $capturedQuery['sql'] = $sql;

                // Must filter by available_at <= now to exclude delayed jobs
                return str_contains($sql, 'available_at') && str_contains($sql, '<=');
            }),
            $this->callback(function (array $bindings) use (&$capturedQuery) {
                $capturedQuery['bindings'] = $bindings;

                // Must have a 'now' binding to compare against available_at
                return isset($bindings['now']);
            }),
        )
        ->willReturn([
            [
                'id' => 'available-job',
                'queue' => 'default',
                'payload' => $wrappedPayload,
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $pastTime,
                'created_at' => $pastTime,
            ],
        ]);

    $connection->method('execute')->willReturn(1);

    $queue = new DatabaseQueue($connection, $envelope);
    $poppedJob = $queue->pop();

    expect($poppedJob)->not->toBeNull()
        ->and($poppedJob->id)->toBe('available-job');

    // Verify the SQL query filters by available_at
    expect($capturedQuery['sql'])->toContain('available_at')
        ->toContain('<=')
        ->and($capturedQuery['bindings'])->toHaveKey('now');
});

test('it verifies the envelope before unserializing in DatabaseQueue::pop()', function (): void {
    $envelope = createTestEnvelope();
    $connection = $this->createMock(ConnectionInterface::class);

    $job = new TestJob('envelope verify test');
    $wrappedPayload = $envelope->wrap($job->serialize());

    $connection->method('query')->willReturn([
        [
            'id' => 'job-envelope-test',
            'queue' => 'default',
            'payload' => $wrappedPayload,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => '2024-01-01 00:00:00',
            'created_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $connection->method('execute')->willReturn(1);

    $queue = new DatabaseQueue($connection, $envelope);

    /** @var TestJob $popped */
    $popped = $queue->pop();

    expect($popped)->toBeInstanceOf(TestJob::class)
        ->and($popped->message)->toBe('envelope verify test');
});

test('it rejects a tampered DatabaseQueue payload before unserializing', function (): void {
    $envelope = createTestEnvelope();
    $connection = $this->createMock(ConnectionInterface::class);

    // Build a tampered payload: valid HMAC prefix but different body
    $fakeHmac = str_repeat('a', 64);
    $tamperedPayload = $fakeHmac . '.O:8:"EvilJob":0:{}';

    $connection->method('query')->willReturn([
        [
            'id' => 'tampered-job',
            'queue' => 'default',
            'payload' => $tamperedPayload,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => '2024-01-01 00:00:00',
            'created_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $connection->method('execute')->willReturn(1);

    $queue = new DatabaseQueue($connection, $envelope);

    expect(fn () => $queue->pop())->toThrow(SerializationException::class);
});

test('it round-trips a legitimate job through DatabaseQueue push and pop', function (): void {
    $envelope = createTestEnvelope();

    $connection = new class () implements ConnectionInterface
    {
        /** @var array<int, array<string, mixed>> */
        private array $storedRows = [];

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            return $this->storedRows;
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            if (str_contains($sql, 'INSERT')) {
                $this->storedRows = [
                    [
                        'id' => $bindings['id'],
                        'queue' => $bindings['queue'],
                        'payload' => $bindings['payload'],
                        'attempts' => $bindings['attempts'],
                        'reserved_at' => null,
                        'available_at' => $bindings['available_at'],
                        'created_at' => $bindings['created_at'],
                    ],
                ];
            }

            return 1;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 1;
        }
    };

    $queue = new DatabaseQueue($connection, $envelope);

    $job = new TestJob('round-trip message');
    $pushedId = $queue->push($job);

    /** @var TestJob $poppedJob */
    $poppedJob = $queue->pop();

    expect($poppedJob)->toBeInstanceOf(TestJob::class)
        ->and($poppedJob->message)->toBe('round-trip message')
        ->and($poppedJob->id)->toBe($pushedId);
});
