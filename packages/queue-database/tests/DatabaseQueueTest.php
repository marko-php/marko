<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\LockException;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\Database\DatabaseQueue;
use Marko\Queue\Database\Tests\Fixtures\InMemoryFailedJobRepository;
use Marko\Queue\Database\Tests\Fixtures\SqliteConnection;
use Marko\Queue\Database\Tests\Fixtures\TestFailedJobHandler;
use Marko\Queue\Database\Tests\Fixtures\TestJob;
use Marko\Queue\Database\Tests\Fixtures\TestRetryPolicy;
use Marko\Queue\Exceptions\SerializationException;
use Marko\Queue\JobEnvelope;
use Marko\Queue\QueueInterface;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Clock\ClockInterface;

function createTestQueue(
    ConnectionInterface $connection,
    ?JobEnvelope $envelope = null,
    int $retryAfter = 90,
    ClockInterface $clock = new FakeClock('2026-10-05 12:00:00'),
    int $maxAttempts = 3,
    ?InMemoryFailedJobRepository $failedJobRepository = null,
): DatabaseQueue {
    return new DatabaseQueue(
        connection: $connection,
        queryBuilderFactory: new PgSqlQueryBuilderFactory($connection),
        jobEnvelope: $envelope ?? createTestEnvelope(),
        failedJobHandler: TestFailedJobHandler::create(
            $failedJobRepository ?? new InMemoryFailedJobRepository(),
            $envelope ?? createTestEnvelope(),
            $clock,
        ),
        clock: $clock,
        databaseTimezoneConfig: DatabaseTimezoneConfig::fromName('UTC'),
        retryPolicy: TestRetryPolicy::create($maxAttempts, $clock),
        retryAfter: $retryAfter,
    );
}

function createTestEnvelope(
    string $key = 'test-hmac-key-for-queue-database',
): JobEnvelope {
    return new JobEnvelope(new EncryptionConfig(new FakeConfigRepository(['encryption.key' => $key])));
}

/**
 * Make a connection mock behave like a driver connection inside transaction():
 * it reports an open transaction and runs the callback.
 */
function runsTransactions(
    Stub $connection,
): void {
    $connection->method('inTransaction')->willReturn(true);
    $connection->method('transaction')->willReturnCallback(fn (callable $callback): mixed => $callback());
}

test('DatabaseQueue implements QueueInterface', function () {
    $connection = $this->createStub(ConnectionInterface::class);
    $queue = createTestQueue($connection);

    expect($queue)->toBeInstanceOf(QueueInterface::class);
});

test('DatabaseQueue push stores job in database', function () {
    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('quoteIdentifier')->willReturnCallback(fn (string $name): string => "\"$name\"");

    $connection->expects($this->once())
        ->method('execute')
        ->with(
            $this->stringContains('INSERT INTO "jobs"'),
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

    $queue = createTestQueue($connection);
    $queue->push($job);
});

test('DatabaseQueue push returns job ID', function () {
    $connection = $this->createStub(ConnectionInterface::class);
    $connection->method('execute')->willReturn(1);

    $job = new TestJob('test message');

    $queue = createTestQueue($connection);
    $id = $queue->push($job)->id;

    expect($id)->toBeString()
        ->toMatch('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/')
        ->and($job->id)->toBe($id);
});

test('DatabaseQueue later stores job with future available_at', function () {
    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('quoteIdentifier')->willReturnCallback(fn (string $name): string => "\"$name\"");

    $capturedBindings = [];
    $connection->expects($this->once())
        ->method('execute')
        ->with(
            $this->stringContains('INSERT INTO "jobs"'),
            $this->callback(function (array $bindings) use (&$capturedBindings) {
                $capturedBindings = $bindings;

                return true;
            }),
        )
        ->willReturn(1);

    $job = new TestJob('delayed job');

    $queue = createTestQueue($connection, clock: new FakeClock('2026-10-05 12:00:00'));
    $id = $queue->later(60, $job)->id;

    expect($id)->toBeString()
        ->and($capturedBindings['available_at'])->toBe('2026-10-05 12:01:00')
        ->and($capturedBindings['created_at'])->toBe('2026-10-05 12:00:00');
});

test('DatabaseQueue pop retrieves and reserves next job', function () {
    $envelope = createTestEnvelope();
    $connection = $this->createMockForIntersectionOfInterfaces(
        [ConnectionInterface::class, TransactionInterface::class],
    );
    runsTransactions($connection);

    $job = new TestJob('pop test');
    $wrappedPayload = $envelope->wrap($job->serialize());

    // First query: SELECT to find next available job
    // Second execute: UPDATE to reserve the job
    $connection->expects($this->once())
        ->method('query')
        ->with(
            $this->stringContains('SELECT'),
            $this->callback(function (array $bindings) {
                return $bindings[0] === 'default';
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
                    && !isset($bindings['attempts'])
                    && $bindings['id'] === 'job-123';
            }),
        )
        ->willReturn(1);

    $queue = createTestQueue($connection, $envelope);
    $poppedJob = $queue->pop();

    expect($poppedJob)->toBeInstanceOf(TestJob::class)
        ->and($poppedJob->id)->toBe('job-123')
        ->and($poppedJob->attempts)->toBe(0);
});

test('DatabaseQueue pop returns null when empty', function () {
    $connection = $this->createMockForIntersectionOfInterfaces(
        [ConnectionInterface::class, TransactionInterface::class],
    );
    runsTransactions($connection);

    $connection->expects($this->once())
        ->method('query')
        ->willReturn([]);

    $connection->expects($this->never())
        ->method('execute');

    $queue = createTestQueue($connection);
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

    $queue = createTestQueue($connection);
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

    $queue = createTestQueue($connection);
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

    $queue = createTestQueue($connection);
    $deleted = $queue->delete('job-123');

    expect($deleted)->toBeTrue();
});

test('DatabaseQueue delete returns false when job not found', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $connection->expects($this->once())
        ->method('execute')
        ->willReturn(0);

    $queue = createTestQueue($connection);
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

    $job = new TestJob();
    $job->setId('job-123');

    $queue = createTestQueue($connection, clock: new FakeClock('2026-10-05 12:00:00'));
    $released = $queue->release($job, 30);

    expect($released)->toBeTrue()
        ->and($capturedBindings['available_at'])->toBe('2026-10-05 12:00:30');
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

    $job = new TestJob();
    $job->setId('job-123');

    $queue = createTestQueue($connection, clock: new FakeClock('2026-10-05 12:00:00'));
    $released = $queue->release($job);

    expect($released)->toBeTrue()
        ->and($capturedBindings['available_at'])->toBe('2026-10-05 12:00:00');
});

test('DatabaseQueue release returns false when job not found', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $connection->expects($this->once())
        ->method('execute')
        ->willReturn(0);

    $job = new TestJob();
    $job->setId('nonexistent-job');

    $queue = createTestQueue($connection);
    $released = $queue->release($job);

    expect($released)->toBeFalse();
});

test('DatabaseQueue release returns false for a job that was never queued', function () {
    $connection = $this->createMock(ConnectionInterface::class);

    $connection->expects($this->never())
        ->method('execute');

    $queue = createTestQueue($connection);

    expect($queue->release(new TestJob()))->toBeFalse();
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

        public function driverName(): string
        {
            return 'sqlite';
        }

        public function supportsReturning(): bool
        {
            return false;
        }

        public function quoteIdentifier(
            string $identifier,
        ): string {
            return '"' . str_replace('"', '""', $identifier) . '"';
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
            int $attempts = 1,
            int|Closure|null $backoff = null,
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

        public function transactionLevel(): int
        {
            return 0;
        }

        public function afterCommit(callable $callback): void {}

        public function afterRollback(callable $callback): void {}
    };

    $queue = createTestQueue($connection, $envelope);
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
    $connection = $this->createMockForIntersectionOfInterfaces(
        [ConnectionInterface::class, TransactionInterface::class],
    );
    runsTransactions($connection);

    $job = new TestJob('delayed job test');
    $wrappedPayload = $envelope->wrap($job->serialize());

    $pastTime = '2026-10-05 11:59:00';

    // Capture the query bindings to verify the available_at condition
    $capturedQuery = [];
    $connection->expects($this->once())
        ->method('query')
        ->with(
            $this->callback(function (string $sql) use (&$capturedQuery) {
                $capturedQuery['sql'] = $sql;

                // Must filter by available_at <= now to exclude delayed jobs
                return str_contains($sql, '"available_at" <= ?');
            }),
            $this->callback(function (array $bindings) use (&$capturedQuery) {
                $capturedQuery['bindings'] = $bindings;

                // Must bind the clock's current time to compare against available_at
                return in_array('2026-10-05 12:00:00', $bindings, true);
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

    $queue = createTestQueue($connection, $envelope);
    $poppedJob = $queue->pop();

    expect($poppedJob)->not->toBeNull()
        ->and($poppedJob->id)->toBe('available-job');

    // Verify the SQL query filters by available_at
    expect($capturedQuery['sql'])->toContain('"available_at" <= ?')
        ->and($capturedQuery['bindings'])->toHaveCount(3);
});

test('it verifies the envelope before unserializing in DatabaseQueue::pop()', function (): void {
    $envelope = createTestEnvelope();
    $connection = $this->createStubForIntersectionOfInterfaces(
        [ConnectionInterface::class, TransactionInterface::class],
    );
    runsTransactions($connection);

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

    $queue = createTestQueue($connection, $envelope);

    /** @var TestJob $popped */
    $popped = $queue->pop();

    expect($popped)->toBeInstanceOf(TestJob::class)
        ->and($popped->message)->toBe('envelope verify test');
});

test('it rejects a tampered DatabaseQueue payload before unserializing', function (): void {
    $envelope = createTestEnvelope();
    $connection = $this->createStubForIntersectionOfInterfaces(
        [ConnectionInterface::class, TransactionInterface::class],
    );
    runsTransactions($connection);

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

    $queue = createTestQueue($connection, $envelope);

    expect(fn () => $queue->pop())->toThrow(SerializationException::class);
});

it(
    'counts exactly one attempt per execution (popping then processing a job once yields attempts == 1, not 2)',
    function (): void {
        $envelope = createTestEnvelope();
        $connection = $this->createStubForIntersectionOfInterfaces(
            [ConnectionInterface::class, TransactionInterface::class],
        );
        runsTransactions($connection);

        $job = new TestJob('attempt count test');
        $wrappedPayload = $envelope->wrap($job->serialize());

        $connection->method('query')->willReturn([
            [
                'id' => 'job-attempt',
                'queue' => 'default',
                'payload' => $wrappedPayload,
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => '2024-01-01 00:00:00',
                'created_at' => '2024-01-01 00:00:00',
            ],
        ]);
        $connection->method('execute')->willReturn(1);

        $queue = createTestQueue($connection, $envelope);

        /** @var TestJob $poppedJob */
        $poppedJob = $queue->pop();

        // After pop: attempts must be 0 (DB no longer increments)
        expect($poppedJob)->toBeInstanceOf(TestJob::class)
                ->and($poppedJob->attempts)->toBe(0);

        // Worker calls incrementAttempts() once
        $poppedJob->incrementAttempts();

        // After one Worker execution: exactly 1 attempt
        expect($poppedJob->attempts)->toBe(1);
    },
);

it('does not reclaim a job whose reservation is within the retry_after window', function (): void {
    $connection = SqliteConnection::withQueueTables();
    $clock = new FakeClock('2026-10-05 12:00:00');
    $queue = createTestQueue($connection, retryAfter: 90, clock: $clock);
    $queue->push(new TestJob('recent reservation'));
    $queue->pop();
    $clock->travel('+89 seconds');

    expect($queue->pop())->toBeNull();
});

it(
    'reclaims a job whose reservation is older than queue.retry_after and makes it available to pop() again',
    function (): void {
        $connection = SqliteConnection::withQueueTables();
        $clock = new FakeClock('2026-10-05 12:00:00');
        $queue = createTestQueue($connection, retryAfter: 90, clock: $clock);
        $id = $queue->push(new TestJob('reclaim test'))->id;
        $queue->pop();
        $clock->travel('+91 seconds');

        expect($queue->pop()?->id)->toBe($id);
    },
);

it('selects the next job with FOR UPDATE SKIP LOCKED', function (): void {
    $connection = SqliteConnection::withQueueTables();
    $queue = createTestQueue($connection);
    $queue->push(new TestJob('locked select'));

    $queue->pop();

    expect($connection->lockClauses)->toBe(['FOR UPDATE SKIP LOCKED']);
});

it('fails loudly when popping on a connection without transactions', function (): void {
    $connection = $this->createStub(ConnectionInterface::class);
    $queue = createTestQueue($connection);

    expect(fn () => $queue->pop())->toThrow(LockException::class);
});

it('issues the reserve UPDATE with a reserved_at IS NULL guard', function (): void {
    $envelope = createTestEnvelope();
    $connection = $this->createMockForIntersectionOfInterfaces(
        [ConnectionInterface::class, TransactionInterface::class],
    );
    runsTransactions($connection);

    $job = new TestJob('guard test');
    $wrappedPayload = $envelope->wrap($job->serialize());

    $connection->method('query')->willReturn([
        [
            'id' => 'job-guard',
            'queue' => 'default',
            'payload' => $wrappedPayload,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => '2024-01-01 00:00:00',
            'created_at' => '2024-01-01 00:00:00',
        ],
    ]);

    $capturedSql = '';
    $connection->expects($this->once())
        ->method('execute')
        ->with(
            $this->callback(function (string $sql) use (&$capturedSql): bool {
                $capturedSql = $sql;

                return true;
            }),
            $this->anything(),
        )
        ->willReturn(1);

    $queue = createTestQueue($connection, $envelope);
    $queue->pop();

    expect($capturedSql)->toContain('reserved_at IS NULL');
});

it(
    'reserves a job atomically so a second concurrent pop() of the same queue does not return the already-reserved job (affected-rows guard returns null on race loss)',
    function (): void {
        $envelope = createTestEnvelope();
        $connection = $this->createStubForIntersectionOfInterfaces(
            [ConnectionInterface::class, TransactionInterface::class],
        );
        runsTransactions($connection);

        $job = new TestJob('atomic test');
        $wrappedPayload = $envelope->wrap($job->serialize());

        $connection->method('query')->willReturn([
            [
                'id' => 'job-atomic',
                'queue' => 'default',
                'payload' => $wrappedPayload,
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => '2024-01-01 00:00:00',
                'created_at' => '2024-01-01 00:00:00',
            ],
        ]);

        // Simulate race loss: UPDATE affects 0 rows (another worker reserved it first)
        $connection->method('execute')->willReturn(0);

        $queue = createTestQueue($connection, $envelope);
        $result = $queue->pop();

        expect($result)->toBeNull();
    },
);

test('it round-trips a legitimate job through DatabaseQueue push and pop', function (): void {
    $queue = createTestQueue(SqliteConnection::withQueueTables());

    $job = new TestJob('round-trip message');
    $pushedId = $queue->push($job)->id;

    /** @var TestJob $poppedJob */
    $poppedJob = $queue->pop();

    expect($poppedJob)->toBeInstanceOf(TestJob::class)
        ->and($poppedJob->message)->toBe('round-trip message')
        ->and($poppedJob->id)->toBe($pushedId);
});

describe('DatabaseQueue clock', function (): void {
    it('stores available_at and created_at from the injected clock', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $queue = createTestQueue($connection, clock: new FakeClock('2026-10-05 12:00:00'));

        $id = $queue->later(120, new TestJob('timed'))->id;
        $row = $connection->query('SELECT available_at, created_at FROM jobs WHERE id = :id', ['id' => $id])[0];

        expect($row['available_at'])->toBe('2026-10-05 12:02:00')
            ->and($row['created_at'])->toBe('2026-10-05 12:00:00');
    });

    it('does not pop a delayed job until the clock reaches its available time', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $clock = new FakeClock('2026-10-05 12:00:00');
        $queue = createTestQueue($connection, clock: $clock);
        $id = $queue->later(60, new TestJob('delayed'))->id;

        $clock->travel('+59 seconds');
        $beforeAvailable = $queue->pop();
        $clock->travel('+1 second');
        $onceAvailable = $queue->pop();

        expect($beforeAvailable)->toBeNull()
            ->and($onceAvailable?->id)->toBe($id);
    });

    it('reclaims a reserved job once the clock reaches retry_after', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $clock = new FakeClock('2026-10-05 12:00:00');
        $queue = createTestQueue($connection, retryAfter: 30, clock: $clock);
        $id = $queue->push(new TestJob('stuck'))->id;
        $queue->pop();

        $clock->travel('+29 seconds');
        $withinWindow = $queue->pop();
        $clock->travel('+1 second');
        $afterWindow = $queue->pop();

        expect($withinWindow)->toBeNull()
            ->and($afterWindow?->id)->toBe($id);
    });

    it('counts only jobs available at the clock time in size', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $clock = new FakeClock('2026-10-05 12:00:00');
        $queue = createTestQueue($connection, clock: $clock);
        $queue->push(new TestJob('now'));
        $queue->later(300, new TestJob('later'));

        $sizeNow = $queue->size();
        $clock->travel('+5 minutes');

        expect($sizeNow)->toBe(1)
            ->and($queue->size())->toBe(2);
    });

    it('schedules a released job relative to the injected clock', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $clock = new FakeClock('2026-10-05 12:00:00');
        $queue = createTestQueue($connection, clock: $clock);
        $id = $queue->push(new TestJob('retry me'))->id;
        $job = $queue->pop();
        $clock->travel('+10 seconds');

        $queue->release($job, 45);
        $row = $connection->query('SELECT available_at, reserved_at FROM jobs WHERE id = :id', ['id' => $id])[0];

        expect($row['available_at'])->toBe('2026-10-05 12:00:55')
            ->and($row['reserved_at'])->toBeNull();
    });

    it('stamps failedAt from the injected clock when a reclaimed job exhausts its attempts', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $clock = new FakeClock('2026-10-05 12:00:00');
        $failed = new InMemoryFailedJobRepository();
        $queue = createTestQueue(
            $connection,
            retryAfter: 30,
            clock: $clock,
            maxAttempts: 1,
            failedJobRepository: $failed,
        );
        $id = $queue->push(new TestJob('crashes its worker'))->id;
        $queue->pop();
        $clock->travel('+31 seconds');

        expect($queue->pop())->toBeNull()
            ->and($failed->find($id)?->failedAt)->toEqual(new DateTimeImmutable('2026-10-05 12:00:31'));
    });
});
