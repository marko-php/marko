<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\Database\DatabaseFailedJobRepository;
use Marko\Queue\Database\DatabaseQueue;
use Marko\Queue\Database\Tests\Fixtures\SqliteConnection;
use Marko\Queue\Database\Tests\Fixtures\TestFailedJobHandler;
use Marko\Queue\Database\Tests\Fixtures\TestJob;
use Marko\Queue\Database\Tests\Fixtures\TestRetryPolicy;
use Marko\Queue\JobEnvelope;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * A clock reading the given UTC instant, expressed in the given timezone.
 */
function zonedClock(
    string $utcInstant,
    string $timezone,
): FakeClock {
    return new FakeClock(new DateTimeImmutable($utcInstant)->setTimezone(new DateTimeZone($timezone)));
}

function zonedQueue(
    SqliteConnection $connection,
    FakeClock $clock,
    string $databaseTimezone = 'UTC',
    int $retryAfter = 90,
): DatabaseQueue {
    return new DatabaseQueue(
        connection: $connection,
        jobEnvelope: new JobEnvelope(new EncryptionConfig(new FakeConfigRepository([
            'encryption.key' => 'timezone-test-key',
        ]))),
        failedJobHandler: TestFailedJobHandler::create(
            new DatabaseFailedJobRepository(
                $connection,
                DatabaseTimezoneConfig::fromName($databaseTimezone),
            ),
            new JobEnvelope(new EncryptionConfig(new FakeConfigRepository([
                'encryption.key' => 'timezone-test-key',
            ]))),
            $clock,
        ),
        queryBuilderFactory: new PgSqlQueryBuilderFactory($connection),
        clock: $clock,
        databaseTimezoneConfig: DatabaseTimezoneConfig::fromName($databaseTimezone),
        retryPolicy: TestRetryPolicy::create(3, $clock),
        retryAfter: $retryAfter,
    );
}

describe('DatabaseQueue database timezone', function (): void {
    it('writes created_at and available_at in the database timezone whatever the clock timezone', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $clock = zonedClock('2026-03-14T15:00:00Z', 'America/New_York');

        zonedQueue($connection, $clock)->later(60, new TestJob());
        zonedQueue($connection, $clock, 'Europe/Berlin')->push(new TestJob());

        $rows = $connection->query('SELECT available_at, created_at FROM jobs ORDER BY available_at DESC');

        expect($rows[0]['created_at'])->toBe('2026-03-14 16:00:00')
            ->and($rows[0]['available_at'])->toBe('2026-03-14 16:00:00')
            ->and($rows[1]['created_at'])->toBe('2026-03-14 15:00:00')
            ->and($rows[1]['available_at'])->toBe('2026-03-14 15:01:00');
    });

    it('writes reserved_at in the database timezone whatever the clock timezone', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $queue = zonedQueue($connection, zonedClock('2026-03-14T15:00:00Z', 'Asia/Tokyo'));
        $queue->push(new TestJob());

        $queue->pop();

        expect($connection->query('SELECT reserved_at FROM jobs')[0]['reserved_at'])->toBe('2026-03-14 15:00:00');
    });

    it(
        'pops on time a job pushed by a queue whose clock is America/New_York from a queue whose clock is UTC',
        function (): void {
            $connection = SqliteConnection::withQueueTables();
            zonedQueue($connection, zonedClock('2026-03-14T16:00:00Z', 'America/New_York'))->later(
                3600,
                new TestJob(),
            );

            $early = zonedQueue($connection, zonedClock('2026-03-14T16:59:59Z', 'UTC'))->pop();
            $due = zonedQueue($connection, zonedClock('2026-03-14T17:00:00Z', 'UTC'))->pop();

            expect($early)->toBeNull()
                ->and($due)->toBeInstanceOf(TestJob::class);
        },
    );

    it('counts available jobs against a cutoff in the database timezone', function (): void {
        $connection = SqliteConnection::withQueueTables();
        zonedQueue($connection, zonedClock('2026-03-14T16:00:00Z', 'UTC'))->later(3600, new TestJob());

        $early = zonedQueue($connection, zonedClock('2026-03-14T16:59:59Z', 'America/New_York'))->size();
        $due = zonedQueue($connection, zonedClock('2026-03-14T17:00:00Z', 'America/New_York'))->size();

        expect($early)->toBe(0)
            ->and($due)->toBe(1);
    });

    it(
        'makes a reservation taken just before the fall-back transition reclaimable retry_after seconds later',
        function (): void {
            $connection = SqliteConnection::withQueueTables();
            $clock = zonedClock('2026-11-01T05:59:00Z', 'America/New_York');
            $queue = zonedQueue($connection, $clock);
            $id = $queue->push(new TestJob())->id;

            // Reserved at 01:59:00 EDT; the worker then crashes.
            $queue->pop();

            // 89 seconds later the wall clock reads 01:00:29 EST.
            $clock->setNow(
                new DateTimeImmutable('2026-11-01T06:00:29Z')->setTimezone(new DateTimeZone('America/New_York')),
            );
            $stillReserved = $queue->pop();

            // 91 seconds later it reads 01:00:31 EST, past retry_after.
            $clock->setNow(
                new DateTimeImmutable('2026-11-01T06:00:31Z')->setTimezone(new DateTimeZone('America/New_York')),
            );
            $reclaimed = $queue->pop();

            expect($stillReserved)->toBeNull()
                ->and($reclaimed?->id)->toBe($id);
        },
    );

    it('delays a job by elapsed seconds across the fall-back transition', function (): void {
        $connection = SqliteConnection::withQueueTables();

        // 01:59:00 EDT plus 120 seconds is 01:01:00 EST, not 02:01 EST.
        zonedQueue($connection, zonedClock('2026-11-01T05:59:00Z', 'America/New_York'))->later(120, new TestJob());

        expect($connection->query('SELECT available_at FROM jobs')[0]['available_at'])->toBe('2026-11-01 06:01:00');
    });

    it('keeps FIFO order across the repeated fall-back hour', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $clock = zonedClock('2026-11-01T05:55:00Z', 'America/New_York');
        $queue = zonedQueue($connection, $clock);

        // 01:55 EDT, then 01:05 EST (seventy minutes later).
        $first = $queue->push(new TestJob())->id;
        $clock->setNow(
            new DateTimeImmutable('2026-11-01T06:05:00Z')->setTimezone(new DateTimeZone('America/New_York')),
        );
        $second = $queue->push(new TestJob())->id;

        expect($queue->pop()?->id)->toBe($first)
            ->and($queue->pop()?->id)->toBe($second);
    });
});
