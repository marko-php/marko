<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Container\Container;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Introspection\PgSqlIntrospector;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Marko\Database\PgSql\Sql\PgSqlGenerator;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\Command\RetryCommand;
use Marko\Queue\Database\DatabaseFailedJobRepository;
use Marko\Queue\Database\DatabaseQueue;
use Marko\Queue\Database\Tests\Fixtures\PrivateStateJob;
use Marko\Queue\Database\Tests\Fixtures\QueueTables;
use Marko\Queue\Database\Tests\Fixtures\TestFailedJobHandler;
use Marko\Queue\Database\Tests\Fixtures\TestRetryPolicy;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobHandler;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobRunner;
use Marko\Queue\QueueConfig;
use Marko\Queue\RetryPolicy;
use Marko\Queue\Worker;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;

/*
 * These tests run against a real PostgreSQL server. They are skipped (with the reason)
 * unless DB_HOST is set and reachable. Run locally with, for example:
 *
 *   docker run -d -p 5432:5432 -e POSTGRES_PASSWORD=secret -e POSTGRES_DB=marko_test postgres:17
 *   DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=marko_test DB_USERNAME=postgres DB_PASSWORD=secret \
 *     composer test -- packages/queue-database/tests/Integration
 */

/**
 * Connect to the test server. With $migrate (the default) the jobs and failed_jobs
 * tables are dropped and recreated from the entities, as db:migrate builds them;
 * pass false for a second connection to the same tables.
 *
 * @return array{connection: ?PgSqlConnection, skipReason: ?string}
 */
function pgsqlQueueConnection(
    bool $migrate = true,
): array {
    $host = getenv('DB_HOST');

    if ($host === false || $host === '') {
        return [
            'connection' => null,
            'skipReason' => 'PostgreSQL integration test: set DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD to run it',
        ];
    }

    $config = [
        'driver' => 'pgsql',
        'host' => $host,
        'port' => (int) (getenv('DB_PORT') ?: 5432),
        'database' => getenv('DB_DATABASE') ?: 'marko_test',
        'username' => getenv('DB_USERNAME') ?: 'postgres',
        'password' => getenv('DB_PASSWORD') ?: '',
    ];

    $basePath = sys_get_temp_dir() . '/marko-queue-pgsql-' . getmypid();

    if (!is_dir("$basePath/config")) {
        mkdir("$basePath/config", recursive: true);
    }

    file_put_contents("$basePath/config/database.php", '<?php return ' . var_export($config, true) . ';');

    $connection = new PgSqlConnection(new DatabaseConfig(new ProjectPaths($basePath)));

    try {
        $connection->connect();
    } catch (Throwable $e) {
        return [
            'connection' => null,
            'skipReason' => "PostgreSQL not reachable at {$config['host']}:{$config['port']}: {$e->getMessage()}",
        ];
    }

    if (!$migrate) {
        return ['connection' => $connection, 'skipReason' => null];
    }

    QueueTables::drop($connection);
    QueueTables::create($connection, new PgSqlGenerator());

    return ['connection' => $connection, 'skipReason' => null];
}

function pgsqlEnvelope(): JobEnvelope
{
    return new JobEnvelope(new EncryptionConfig(new FakeConfigRepository(['encryption.key' => 'pgsql-test-key'])));
}

function pgsqlQueue(
    PgSqlConnection $connection,
    FakeClock $clock = new FakeClock(),
    int $maxAttempts = 1,
): DatabaseQueue {
    return new DatabaseQueue(
        connection: $connection,
        jobEnvelope: pgsqlEnvelope(),
        failedJobHandler: TestFailedJobHandler::create(
            new DatabaseFailedJobRepository($connection, DatabaseTimezoneConfig::fromName('UTC')),
            pgsqlEnvelope(),
            $clock,
        ),
        queryBuilderFactory: new PgSqlQueryBuilderFactory($connection),
        clock: $clock,
        databaseTimezoneConfig: DatabaseTimezoneConfig::fromName('UTC'),
        retryPolicy: TestRetryPolicy::create($maxAttempts, $clock),
    );
}

describe('database queue on PostgreSQL', function (): void {
    it('builds jobs and failed_jobs from the entities on PostgreSQL', function (): void {
        ['connection' => $connection, 'skipReason' => $skipReason] = pgsqlQueueConnection();

        if ($connection === null) {
            $this->markTestSkipped($skipReason);
        }

        $introspector = new PgSqlIntrospector($connection);
        $jobs = $introspector->getTable('jobs');

        expect(array_map(fn ($index): string => $index->name, $jobs?->indexes ?? []))->toContain('idx_queue_available')
            ->and($introspector->getTable('failed_jobs'))->not->toBeNull()
            ->and(QueueTables::diff($introspector)->isEmpty())->toBeTrue();
    });

    it('diffs tables created from the documented DDL as empty on PostgreSQL', function (): void {
        ['connection' => $connection, 'skipReason' => $skipReason] = pgsqlQueueConnection();

        if ($connection === null) {
            $this->markTestSkipped($skipReason);
        }

        QueueTables::drop($connection);
        QueueTables::createFromDocumentedDdl($connection);

        expect(new PgSqlGenerator()->generateUp(QueueTables::diff(new PgSqlIntrospector($connection))))->toBe([]);
    });

    it(
        'round-trips a job with private and protected properties through push and pop on PostgreSQL',
        function (): void {
            ['connection' => $connection, 'skipReason' => $skipReason] = pgsqlQueueConnection();

            if ($connection === null) {
                $this->markTestSkipped($skipReason);
            }

            $queue = pgsqlQueue($connection);
            $queue->push(new PrivateStateJob("se\0cret", 'shared'));

            /** @var PrivateStateJob $popped */
            $popped = $queue->pop();

            expect($popped)->toBeInstanceOf(PrivateStateJob::class)
                ->and($popped->secret())->toBe("se\0cret")
                ->and($popped->shared())->toBe('shared')
                ->and(str_contains($connection->query('SELECT payload FROM jobs')[0]['payload'], "\0"))->toBeFalse();
        },
    );

    it(
        'keeps attempts, exceptions and the retry deadline of a released job across the retry on PostgreSQL',
        function (): void {
            ['connection' => $connection, 'skipReason' => $skipReason] = pgsqlQueueConnection();

            if ($connection === null) {
                $this->markTestSkipped($skipReason);
            }

            $queue = pgsqlQueue($connection, maxAttempts: 3);
            $id = $queue->push(new PrivateStateJob('secret', 'shared'))->id;

            $job = $queue->pop();
            $job->incrementAttempts();
            $job->incrementExceptions();
            $job->setRetryDeadline(new DateTimeImmutable('2026-11-01T06:00:00Z'));
            $released = $queue->release($job, 0);

            /** @var PrivateStateJob $retried */
            $retried = $queue->pop();

            expect($released)->toBeTrue()
                ->and($retried->id)->toBe($id)
                ->and($retried->attempts)->toBe(1)
                ->and($retried->exceptions)->toBe(1)
                ->and($retried->retryDeadline?->format(DATE_ATOM))->toBe('2026-11-01T06:00:00+00:00')
                ->and($retried->secret())->toBe('secret');
        },
    );

    it(
        'keeps reserving a job with a retry deadline past queue.max_attempts, keeping its exception count, on PostgreSQL',
        function (): void {
            ['connection' => $connection, 'skipReason' => $skipReason] = pgsqlQueueConnection();

            if ($connection === null) {
                $this->markTestSkipped($skipReason);
            }

            $clock = new FakeClock('2026-10-31T12:00:00Z');
            $queue = pgsqlQueue($connection, $clock, maxAttempts: 2);
            $job = new PrivateStateJob('secret', 'shared');
            $job->setRetryDeadline(new DateTimeImmutable('2026-10-31T18:00:00Z'));
            $id = $queue->push($job)->id;

            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $popped = $queue->pop();
                $popped->incrementAttempts();

                if ($attempt === 2) {
                    $popped->incrementExceptions();
                }

                $queue->release($popped);
                $clock->travel('+1 minute');
            }

            $popped = $queue->pop();

            expect($popped?->id)->toBe($id)
                ->and($popped->attempts)->toBe(5)
                ->and($popped->exceptions)->toBe(1);

            $popped->incrementAttempts();
            $queue->release($popped, 7 * 3600);
            $clock->travel('+7 hours');

            expect($queue->pop())->toBeNull()
                ->and(new DatabaseFailedJobRepository($connection, DatabaseTimezoneConfig::fromName('UTC'))
                    ->find($id)?->exception)->toContain('passed its retry deadline');
        },
    );

    it('round-trips a failed job through failed_jobs and queue:retry on PostgreSQL', function (): void {
        ['connection' => $connection, 'skipReason' => $skipReason] = pgsqlQueueConnection();

        if ($connection === null) {
            $this->markTestSkipped($skipReason);
        }

        $queue = pgsqlQueue($connection);
        $failedJobRepository = new DatabaseFailedJobRepository($connection, DatabaseTimezoneConfig::fromName('UTC'));
        $id = $queue->push(new PrivateStateJob('secret', 'shared', fail: true))->id;

        $worker = new Worker(
            $queue,
            new FailedJobHandler(
                $failedJobRepository,
                pgsqlEnvelope(),
                new JobRunner(new Container(), pgsqlEnvelope(), new FakeEventDispatcher()),
                new FakeEventDispatcher(),
                new FakeClock(),
            ),
            new QueueConfig(new FakeConfigRepository([
                'queue.queue' => 'default',
                'queue.max_attempts' => 1,
            ])),
            new JobRunner(new Container(), pgsqlEnvelope(), new FakeEventDispatcher()),
            new RetryPolicy(new QueueConfig(new FakeConfigRepository([
                'queue.queue' => 'default',
                'queue.max_attempts' => 1,
            ])), new FakeClock()),
        );
        $worker->work(once: true);

        expect($failedJobRepository->find($id))->not->toBeNull();

        $retry = new RetryCommand($failedJobRepository, $queue, pgsqlEnvelope());
        $exitCode = $retry->execute(new Input(['marko', 'queue:retry', $id]), new Output(fopen('php://memory', 'r+')));

        /** @var PrivateStateJob $retried */
        $retried = $queue->pop();

        expect($exitCode)->toBe(0)
            ->and($failedJobRepository->count())->toBe(0)
            ->and($retried)->toBeInstanceOf(PrivateStateJob::class)
            ->and($retried->secret())->toBe('secret')
            ->and($retried->shared())->toBe('shared')
            ->and($retried->attempts)->toBe(0);
    });

    it('never hands the same job to two concurrent reservations on PostgreSQL', function (): void {
        ['connection' => $workerA, 'skipReason' => $skipReason] = pgsqlQueueConnection();

        if ($workerA === null) {
            $this->markTestSkipped($skipReason);
        }

        ['connection' => $workerB] = pgsqlQueueConnection(migrate: false);
        $first = pgsqlQueue($workerA)->push(new PrivateStateJob('first', 'shared'))->id;
        $second = pgsqlQueue($workerA)->push(new PrivateStateJob('second', 'shared'))->id;

        // Worker B fails fast instead of hanging if the SELECT ever waits on A's lock again.
        $workerB->execute("SET lock_timeout = '2s'");

        // Worker A's pop() nests in an open transaction, so its row lock is held while B pops.
        $workerA->beginTransaction();

        try {
            $poppedByA = pgsqlQueue($workerA)->pop();
            $poppedByB = pgsqlQueue($workerB)->pop();
        } finally {
            $workerA->rollback();
            $workerB->disconnect();
        }

        expect($poppedByA?->id)->toBe($first)
            ->and($poppedByB?->id)->toBe($second);
    });

    it('skips a locked job instead of waiting for it on PostgreSQL', function (): void {
        ['connection' => $workerA, 'skipReason' => $skipReason] = pgsqlQueueConnection();

        if ($workerA === null) {
            $this->markTestSkipped($skipReason);
        }

        ['connection' => $workerB] = pgsqlQueueConnection(migrate: false);
        pgsqlQueue($workerA)->push(new PrivateStateJob('only', 'shared'));
        $workerB->execute("SET lock_timeout = '2s'");

        $workerA->beginTransaction();

        try {
            $poppedByA = pgsqlQueue($workerA)->pop();
            $startedAt = microtime(true);
            $poppedByB = pgsqlQueue($workerB)->pop();
            $waited = microtime(true) - $startedAt;
        } finally {
            $workerA->rollback();
            $workerB->disconnect();
        }

        expect($poppedByA)->not->toBeNull()
            ->and($poppedByB)->toBeNull()
            ->and($waited)->toBeLessThan(1.0);
    });
})->group('integration-services');

/**
 * A FakeClock reading the given UTC instant in the given timezone.
 */
function pgsqlZonedClock(
    string $utcInstant,
    string $timezone,
): FakeClock {
    return new FakeClock(new DateTimeImmutable($utcInstant)->setTimezone(new DateTimeZone($timezone)));
}

describe('database queue timestamps on PostgreSQL with a non-UTC PHP default timezone', function (): void {
    beforeEach(function (): void {
        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
    });

    afterEach(function (): void {
        date_default_timezone_set($this->previousTimezone);
    });

    it('stores queue timestamps in UTC with a non-UTC PHP default timezone on PostgreSQL', function (): void {
        ['connection' => $connection, 'skipReason' => $skipReason] = pgsqlQueueConnection();

        if ($connection === null) {
            $this->markTestSkipped($skipReason);
        }

        $queue = pgsqlQueue($connection, pgsqlZonedClock('2026-03-14T16:00:00Z', 'America/New_York'));
        $queue->push(new PrivateStateJob('secret', 'shared'));
        $queue->pop();

        $row = $connection->query(
            "SELECT to_char(created_at, 'YYYY-MM-DD HH24:MI:SS') AS created_at, "
            . "to_char(available_at, 'YYYY-MM-DD HH24:MI:SS') AS available_at, "
            . "to_char(reserved_at, 'YYYY-MM-DD HH24:MI:SS') AS reserved_at FROM jobs",
        )[0];

        expect($row['created_at'])->toBe('2026-03-14 16:00:00')
            ->and($row['available_at'])->toBe('2026-03-14 16:00:00')
            ->and($row['reserved_at'])->toBe('2026-03-14 16:00:00');
    });

    it('pops a delayed job on time with a non-UTC PHP default timezone on PostgreSQL', function (): void {
        ['connection' => $connection, 'skipReason' => $skipReason] = pgsqlQueueConnection();

        if ($connection === null) {
            $this->markTestSkipped($skipReason);
        }

        $id = pgsqlQueue($connection, pgsqlZonedClock('2026-03-14T16:00:00Z', 'America/New_York'))
            ->later(3600, new PrivateStateJob('secret', 'shared'))->id;

        $early = pgsqlQueue($connection, pgsqlZonedClock('2026-03-14T16:59:59Z', 'UTC'))->pop();
        $due = pgsqlQueue($connection, pgsqlZonedClock('2026-03-14T17:00:00Z', 'UTC'))->pop();

        expect($early)->toBeNull()
            ->and($due?->id)->toBe($id);
    });

    it('reads failed_at back as the stored instant on PostgreSQL', function (): void {
        ['connection' => $connection, 'skipReason' => $skipReason] = pgsqlQueueConnection();

        if ($connection === null) {
            $this->markTestSkipped($skipReason);
        }

        $repository = new DatabaseFailedJobRepository($connection, DatabaseTimezoneConfig::fromName('UTC'));
        $failedAt = new DateTimeImmutable('2026-11-01 01:50:00', new DateTimeZone('America/New_York'));
        $repository->store(new FailedJob(
            id: 'failed-instant',
            queue: 'default',
            payload: 'payload',
            exception: 'RuntimeException: boom',
            failedAt: $failedAt,
        ));

        $stored = $connection->query(
            "SELECT to_char(failed_at, 'YYYY-MM-DD HH24:MI:SS') AS failed_at FROM failed_jobs",
        )[0];

        expect($stored['failed_at'])->toBe('2026-11-01 05:50:00')
            ->and($repository->find('failed-instant')?->failedAt->getTimestamp())->toBe($failedAt->getTimestamp());
    });
})->group('integration-services');
