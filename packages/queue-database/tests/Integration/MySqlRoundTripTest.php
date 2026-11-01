<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\MySql\Connection\MySqlConnection;
use Marko\Database\MySql\Introspection\MySqlIntrospector;
use Marko\Database\MySql\Query\MySqlQueryBuilderFactory;
use Marko\Database\MySql\Sql\MySqlGenerator;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\Database\DatabaseFailedJobRepository;
use Marko\Queue\Database\DatabaseQueue;
use Marko\Queue\Database\Tests\Fixtures\PrivateStateJob;
use Marko\Queue\Database\Tests\Fixtures\QueueTables;
use Marko\Queue\Database\Tests\Fixtures\TestFailedJobHandler;
use Marko\Queue\Database\Tests\Fixtures\TestRetryPolicy;
use Marko\Queue\FailedJob;
use Marko\Queue\JobEnvelope;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

/*
 * These tests run against a real MySQL server, configured through the same
 * MARKO_TEST_MYSQL_* variables as the database-mysql driver tests. Without
 * MARKO_TEST_MYSQL_HOST they skip; with MARKO_INTEGRATION_REQUIRED set they fail
 * instead, so the CI Integration job can never pass by skipping them.
 */

/**
 * Connect to the test server with fresh jobs and failed_jobs tables, built from the entities as db:migrate builds
 * them. With $create false, the tables are only dropped.
 *
 * @return array{connection: ?MySqlConnection, skipReason: ?string}
 * @throws RuntimeException When MARKO_INTEGRATION_REQUIRED is set and MySQL is not configured
 */
function mysqlQueueConnection(
    bool $create = true,
): array {
    $host = getenv('MARKO_TEST_MYSQL_HOST') ?: '';

    if ($host === '') {
        $reason = 'MySQL queue integration test: set MARKO_TEST_MYSQL_HOST (and _PORT, _DATABASE, _USERNAME, _PASSWORD) to run it';

        if (in_array(strtolower(getenv('MARKO_INTEGRATION_REQUIRED') ?: ''), ['1', 'true', 'yes'], true)) {
            throw new RuntimeException("MARKO_INTEGRATION_REQUIRED is set but MARKO_TEST_MYSQL_HOST is not. $reason");
        }

        return ['connection' => null, 'skipReason' => $reason];
    }

    $connection = new MySqlConnection(DatabaseConfig::fromArray([
        'driver' => 'mysql',
        'host' => $host,
        'port' => (int) (getenv('MARKO_TEST_MYSQL_PORT') ?: 3306),
        'database' => getenv('MARKO_TEST_MYSQL_DATABASE') ?: 'marko_test',
        'username' => getenv('MARKO_TEST_MYSQL_USERNAME') ?: 'root',
        'password' => getenv('MARKO_TEST_MYSQL_PASSWORD') ?: '',
    ]));

    $connection->connect();
    QueueTables::drop($connection);

    if ($create) {
        QueueTables::create($connection, new MySqlGenerator());
    }

    return ['connection' => $connection, 'skipReason' => null];
}

function mysqlQueue(
    MySqlConnection $connection,
    string $utcInstant,
    string $clockTimezone,
): DatabaseQueue {
    return new DatabaseQueue(
        connection: $connection,
        jobEnvelope: new JobEnvelope(
            new EncryptionConfig(new FakeConfigRepository(['encryption.key' => 'mysql-test-key'])),
        ),
        failedJobHandler: TestFailedJobHandler::create(
            new DatabaseFailedJobRepository($connection, DatabaseTimezoneConfig::fromName('UTC')),
            new JobEnvelope(
                new EncryptionConfig(new FakeConfigRepository(['encryption.key' => 'mysql-test-key'])),
            ),
            new FakeClock(new DateTimeImmutable($utcInstant)->setTimezone(new DateTimeZone($clockTimezone))),
        ),
        queryBuilderFactory: new MySqlQueryBuilderFactory($connection),
        clock: new FakeClock(new DateTimeImmutable($utcInstant)->setTimezone(new DateTimeZone($clockTimezone))),
        databaseTimezoneConfig: DatabaseTimezoneConfig::fromName('UTC'),
        retryPolicy: TestRetryPolicy::create(),
    );
}

describe('database queue tables on MySQL', function (): void {
    it('builds jobs and failed_jobs from the entities on MySQL', function (): void {
        ['connection' => $connection, 'skipReason' => $skipReason] = mysqlQueueConnection();

        if ($connection === null) {
            $this->markTestSkipped($skipReason);
        }

        $introspector = new MySqlIntrospector($connection, getenv('MARKO_TEST_MYSQL_DATABASE') ?: 'marko_test');

        try {
            $jobs = $introspector->getTable('jobs');
            $failedJobs = $introspector->getTable('failed_jobs');
            $diff = QueueTables::diff($introspector);
        } finally {
            $connection->disconnect();
        }

        expect(array_map(fn ($index): string => $index->name, $jobs?->indexes ?? []))->toContain('idx_queue_available')
            ->and($failedJobs)->not->toBeNull()
            ->and($diff->isEmpty())->toBeTrue();
    });

    it('diffs tables created from the documented DDL as empty on MySQL', function (): void {
        ['connection' => $connection, 'skipReason' => $skipReason] = mysqlQueueConnection(create: false);

        if ($connection === null) {
            $this->markTestSkipped($skipReason);
        }

        try {
            QueueTables::createFromDocumentedDdl($connection);
            $diff = QueueTables::diff(
                new MySqlIntrospector($connection, getenv('MARKO_TEST_MYSQL_DATABASE') ?: 'marko_test'),
            );
            $statements = new MySqlGenerator()->generateUp($diff);
        } finally {
            QueueTables::drop($connection);
            $connection->disconnect();
        }

        expect($statements)->toBe([]);
    });
})->group('integration-services');

describe('database queue release on MySQL', function (): void {
    it(
        'keeps attempts, exceptions and the retry deadline of a released job across the retry on MySQL',
        function (): void {
            ['connection' => $connection, 'skipReason' => $skipReason] = mysqlQueueConnection();

            if ($connection === null) {
                $this->markTestSkipped($skipReason);
            }

            try {
                $queue = mysqlQueue($connection, '2026-10-31T12:00:00Z', 'UTC');
                $id = $queue->push(new PrivateStateJob('secret', 'shared'))->id;

                $job = $queue->pop();
                $job->incrementAttempts();
                $job->incrementExceptions();
                $job->setRetryDeadline(new DateTimeImmutable('2026-11-01T06:00:00Z'));
                $released = $queue->release($job, 0);

                /** @var PrivateStateJob $retried */
                $retried = $queue->pop();
            } finally {
                $connection->disconnect();
            }

            expect($released)->toBeTrue()
                ->and($retried->id)->toBe($id)
                ->and($retried->attempts)->toBe(1)
                ->and($retried->exceptions)->toBe(1)
                ->and($retried->retryDeadline?->format(DATE_ATOM))->toBe('2026-11-01T06:00:00+00:00')
                ->and($retried->secret())->toBe('secret');
        },
    );
});

describe('database queue timestamps on MySQL with a non-UTC PHP default timezone', function (): void {
    beforeEach(function (): void {
        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
    });

    afterEach(function (): void {
        date_default_timezone_set($this->previousTimezone);
    });

    it('pops a delayed job on time with a non-UTC PHP default timezone on MySQL', function (): void {
        ['connection' => $connection, 'skipReason' => $skipReason] = mysqlQueueConnection();

        if ($connection === null) {
            $this->markTestSkipped($skipReason);
        }

        try {
            $id = mysqlQueue($connection, '2026-03-14T16:00:00Z', 'America/New_York')
                ->later(3600, new PrivateStateJob('secret', 'shared'))->id;

            $early = mysqlQueue($connection, '2026-03-14T16:59:59Z', 'UTC')->pop();
            $due = mysqlQueue($connection, '2026-03-14T17:00:00Z', 'UTC')->pop();
        } finally {
            $connection->disconnect();
        }

        expect($early)->toBeNull()
            ->and($due?->id)->toBe($id);
    });

    it('reads failed_at back as the stored instant on MySQL', function (): void {
        ['connection' => $connection, 'skipReason' => $skipReason] = mysqlQueueConnection();

        if ($connection === null) {
            $this->markTestSkipped($skipReason);
        }

        $repository = new DatabaseFailedJobRepository($connection, DatabaseTimezoneConfig::fromName('UTC'));
        $failedAt = new DateTimeImmutable('2026-11-01 01:50:00', new DateTimeZone('America/New_York'));

        try {
            $repository->store(new FailedJob(
                id: 'failed-instant',
                queue: 'default',
                payload: 'payload',
                exception: 'RuntimeException: boom',
                failedAt: $failedAt,
            ));
            $found = $repository->find('failed-instant');
        } finally {
            $connection->disconnect();
        }

        expect($found?->failedAt->getTimestamp())->toBe($failedAt->getTimestamp());
    });
})->group('integration-services');
