<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\Database\DatabaseFailedJobRepository;
use Marko\Queue\Database\DatabaseQueue;
use Marko\Queue\Database\Tests\Fixtures\AlwaysFailingJob;
use Marko\Queue\Database\Tests\Fixtures\FailableCrashJob;
use Marko\Queue\Database\Tests\Fixtures\FailedHookRecorder;
use Marko\Queue\Database\Tests\Fixtures\SqliteConnection;
use Marko\Queue\Database\Tests\Fixtures\TestFailedJobHandler;
use Marko\Queue\Database\Tests\Fixtures\TestJob;
use Marko\Queue\Database\Tests\Fixtures\TestRetryPolicy;
use Marko\Queue\Event\JobFailedEvent;
use Marko\Queue\Exceptions\FailedJobNotStoredException;
use Marko\Queue\Exceptions\JobAttemptsExhaustedException;
use Marko\Queue\Exceptions\JobRetryDeadlinePassedException;
use Marko\Queue\JobEnvelope;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;

function attemptsEnvelope(): JobEnvelope
{
    return new JobEnvelope(new EncryptionConfig(new FakeConfigRepository([
        'encryption.key' => 'attempts-test-key',
    ])));
}

function attemptsQueue(
    SqliteConnection $connection,
    int $maxAttempts = 3,
    FakeClock $clock = new FakeClock(),
): DatabaseQueue {
    return new DatabaseQueue(
        connection: $connection,
        jobEnvelope: attemptsEnvelope(),
        failedJobHandler: TestFailedJobHandler::create(
            new DatabaseFailedJobRepository($connection, DatabaseTimezoneConfig::fromName('UTC')),
            attemptsEnvelope(),
            $clock,
        ),
        queryBuilderFactory: new PgSqlQueryBuilderFactory($connection),
        clock: $clock,
        databaseTimezoneConfig: DatabaseTimezoneConfig::fromName('UTC'),
        retryPolicy: TestRetryPolicy::create($maxAttempts, $clock),
    );
}

/**
 * Simulate a worker that died mid-run: push the reservation past retry_after.
 */
function expireReservation(
    SqliteConnection $connection,
    string $jobId,
): void {
    $connection->execute(
        'UPDATE jobs SET reserved_at = :reserved_at WHERE id = :id',
        ['reserved_at' => new DateTimeImmutable('-1 day')->format('Y-m-d H:i:s'), 'id' => $jobId],
    );
}

function jobsRow(
    SqliteConnection $connection,
    string $jobId,
): ?array {
    return $connection->query('SELECT * FROM jobs WHERE id = :id', ['id' => $jobId])[0] ?? null;
}

describe('DatabaseQueue attempt persistence', function (): void {
    it('increments the attempts column when reserving a job', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $queue = attemptsQueue($connection);
        $id = $queue->push(new TestJob())->id;

        $queue->pop();

        expect((int) jobsRow($connection, $id)['attempts'])->toBe(1);
    });

    it('rewrites the payload with the incremented attempt count on release', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $queue = attemptsQueue($connection);
        $id = $queue->push(new TestJob())->id;

        $job = $queue->pop();
        $job->incrementAttempts();
        $queue->release($job, 0);

        $row = jobsRow($connection, $id);
        $stored = unserialize(attemptsEnvelope()->verifyAndUnwrap($row['payload']));

        expect($stored->attempts)->toBe(1)
            ->and((int) $row['attempts'])->toBe(1)
            ->and($row['reserved_at'])->toBeNull()
            ->and($queue->pop()->attempts)->toBe(1);
    });

    it(
        'stores the released job state (exceptions, retry deadline, attempts) instead of the reserved payload',
        function (): void {
            $connection = SqliteConnection::withQueueTables();
            $queue = attemptsQueue($connection, clock: new FakeClock('2026-10-31T12:00:00Z'));
            $id = $queue->push(new TestJob())->id;

            $job = $queue->pop();
            $job->incrementAttempts();
            $job->incrementExceptions();
            $job->setRetryDeadline(new DateTimeImmutable('2026-11-01T06:00:00Z'));
            $queue->release($job, 0);

            $retried = $queue->pop();

            expect($retried->id)->toBe($id)
                ->and($retried->attempts)->toBe(1)
                ->and($retried->exceptions)->toBe(1)
                ->and($retried->retryDeadline?->format(DATE_ATOM))->toBe('2026-11-01T06:00:00+00:00');
        },
    );

    it('syncs the popped job attempts with reservations that were never released', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $queue = attemptsQueue($connection);
        $id = $queue->push(new TestJob())->id;

        $queue->pop();
        expireReservation($connection, $id);
        $reclaimed = $queue->pop();

        expect($reclaimed)->not->toBeNull()
            ->and($reclaimed->id)->toBe($id)
            ->and($reclaimed->attempts)->toBe(1);
    });

    it('moves a job exhausted by crashed reservations to failed_jobs instead of returning it', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $queue = attemptsQueue($connection, maxAttempts: 2);
        $failedJobRepository = new DatabaseFailedJobRepository($connection, DatabaseTimezoneConfig::fromName('UTC'));
        $id = $queue->push(new TestJob('crashy'), 'emails')->id;

        $queue->pop('emails');
        expireReservation($connection, $id);
        $queue->pop('emails');
        expireReservation($connection, $id);

        expect($queue->pop('emails'))->toBeNull()
            ->and(jobsRow($connection, $id))->toBeNull();

        $failedJob = $failedJobRepository->find($id);

        expect($failedJob)->not->toBeNull()
            ->and($failedJob->queue)->toBe('emails')
            ->and($failedJob->exception)->toContain('exceeded max attempts after worker crash or timeout')
            ->and(unserialize(attemptsEnvelope()->verifyAndUnwrap($failedJob->payload))->message)->toBe('crashy');
    });

    it('calls failed() and dispatches JobFailedEvent for a job exhausted by crashed reservations', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $recorder = new FailedHookRecorder($connection);
        $container = new Container();
        $container->instance(FailedHookRecorder::class, $recorder);
        $events = new FakeEventDispatcher();
        $queue = new DatabaseQueue(
            connection: $connection,
            jobEnvelope: attemptsEnvelope(),
            failedJobHandler: TestFailedJobHandler::create(
                new DatabaseFailedJobRepository($connection, DatabaseTimezoneConfig::fromName('UTC')),
                attemptsEnvelope(),
                new FakeClock(),
                $events,
                $container,
            ),
            queryBuilderFactory: new PgSqlQueryBuilderFactory($connection),
            clock: new FakeClock(),
            databaseTimezoneConfig: DatabaseTimezoneConfig::fromName('UTC'),
            retryPolicy: TestRetryPolicy::create(1),
        );
        $id = $queue->push(new FailableCrashJob('crashy'), 'emails')->id;

        $queue->pop('emails');
        expireReservation($connection, $id);

        expect($queue->pop('emails'))->toBeNull()
            ->and($recorder->calls)->toHaveCount(1)
            ->and($recorder->calls[0]['message'])->toBe('crashy')
            ->and($recorder->calls[0]['exception'])->toBeInstanceOf(JobAttemptsExhaustedException::class)
            ->and($recorder->calls[0]['exception']->getMessage())
            ->toBe("Job $id exceeded max attempts after worker crash or timeout: "
                . '1 of 1 attempts were reserved without being completed or released.')
            ->and($recorder->calls[0]['inTransaction'])->toBeFalse()
            ->and($events->dispatched(JobFailedEvent::class))->toHaveCount(1)
            ->and($events->dispatched(JobFailedEvent::class)[0]->queue)->toBe('emails')
            ->and($events->dispatched(JobFailedEvent::class)[0]->jobId)->toBe($id);
    });

    it('returns the next available job after failing a crash-exhausted one', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $queue = attemptsQueue($connection, maxAttempts: 1);
        $crashedId = $queue->push(new TestJob('crashy'))->id;

        $queue->pop();
        expireReservation($connection, $crashedId);
        $healthyId = $queue->push(new TestJob('healthy'))->id;

        expect($queue->pop()?->id)->toBe($healthyId);
    });

    it('prefers the job maxAttempts over the queue default when failing crashed jobs', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $queue = attemptsQueue($connection, maxAttempts: 1);
        $id = $queue->push(new AlwaysFailingJob(maxAttempts: 5))->id;

        $queue->pop();
        expireReservation($connection, $id);

        expect($queue->pop()?->attempts)->toBe(1);
    });
});

describe('DatabaseQueue retry deadline at pop time', function (): void {
    it(
        'does not fail a job with a deadline that released itself more than queue.max_attempts times',
        function (): void {
            $connection = SqliteConnection::withQueueTables();
            $clock = new FakeClock('2026-10-31 12:00:00');
            $queue = attemptsQueue($connection, maxAttempts: 3, clock: $clock);
            $job = new TestJob('polling');
            $job->setRetryDeadline(new DateTimeImmutable('2026-10-31 18:00:00'));
            $id = $queue->push($job)->id;

            for ($attempt = 1; $attempt <= 10; $attempt++) {
                $popped = $queue->pop();

                expect($popped?->id)->toBe($id);

                // The worker counts the attempt, the job releases itself, and the queue stores it
                $popped->incrementAttempts();
                $popped->release(0);
                $queue->release($popped);
                $clock->travel('+1 minute');
            }

            $popped = $queue->pop();

            expect($popped?->id)->toBe($id)
                ->and($popped->attempts)->toBe(10)
                ->and(jobsRow($connection, $id)['attempts'])->toBe(11);
        },
    );

    it('fails a popped job whose retry deadline has passed through the failed-job handler', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $clock = new FakeClock('2026-10-31 12:00:00');
        $queue = attemptsQueue($connection, clock: $clock);
        $failedJobRepository = new DatabaseFailedJobRepository($connection, DatabaseTimezoneConfig::fromName('UTC'));
        $job = new TestJob('polling');
        $job->setRetryDeadline(new DateTimeImmutable('2026-10-31 12:30:00'));
        $id = $queue->push($job)->id;

        $popped = $queue->pop();
        $popped->incrementAttempts();
        $queue->release($popped, 3600);
        $clock->travel('+1 hour');

        expect($queue->pop())->toBeNull()
            ->and(jobsRow($connection, $id))->toBeNull()
            ->and($failedJobRepository->find($id)?->exception)->toContain(JobRetryDeadlinePassedException::class)
            ->and($failedJobRepository->find($id)?->exception)->toContain('passed its retry deadline');
    });

    it('keeps the exception count of a job released after an exception', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $queue = attemptsQueue($connection);
        $job = new TestJob('polling');
        $job->setRetryDeadline(new DateTimeImmutable('+1 day'));
        $queue->push($job);

        $popped = $queue->pop();
        $popped->incrementAttempts();
        $popped->incrementExceptions();
        $queue->release($popped);

        expect($queue->pop()?->exceptions)->toBe(1);
    });
});

/**
 * A queue (max 1 attempt) whose failed-job handler stores into the connection's failed_jobs table and runs
 * failed() with a container that holds the recorder.
 *
 * @return array{queue: DatabaseQueue, recorder: FailedHookRecorder, events: FakeEventDispatcher}
 */
function notStoredAttemptsQueue(
    SqliteConnection $connection,
): array {
    $recorder = new FailedHookRecorder($connection);
    $container = new Container();
    $container->instance(FailedHookRecorder::class, $recorder);
    $events = new FakeEventDispatcher();
    $queue = new DatabaseQueue(
        connection: $connection,
        jobEnvelope: attemptsEnvelope(),
        failedJobHandler: TestFailedJobHandler::create(
            new DatabaseFailedJobRepository($connection, DatabaseTimezoneConfig::fromName('UTC')),
            attemptsEnvelope(),
            new FakeClock(),
            $events,
            $container,
        ),
        queryBuilderFactory: new PgSqlQueryBuilderFactory($connection),
        clock: new FakeClock(),
        databaseTimezoneConfig: DatabaseTimezoneConfig::fromName('UTC'),
        retryPolicy: TestRetryPolicy::create(1),
    );

    return ['queue' => $queue, 'recorder' => $recorder, 'events' => $events];
}

describe('DatabaseQueue when a crash-exhausted job cannot be stored', function (): void {
    it(
        'throws FailedJobNotStoredException from pop() and leaves the job reserved, without calling failed()',
        function (): void {
            $connection = SqliteConnection::withQueueTables();
            ['queue' => $queue, 'recorder' => $recorder, 'events' => $events] = notStoredAttemptsQueue($connection);
            $id = $queue->push(new FailableCrashJob('crashy'), 'emails')->id;
            $queue->pop('emails');
            expireReservation($connection, $id);
            $connection->execute('ALTER TABLE failed_jobs RENAME TO failed_jobs_gone');
            $thrown = null;

            try {
                $queue->pop('emails');
            } catch (FailedJobNotStoredException $e) {
                $thrown = $e;
            }

            $row = jobsRow($connection, $id);

            expect($thrown)->not->toBeNull()
                ->and($thrown->getMessage())->toContain(FailableCrashJob::class . " (id $id)")
                ->and($thrown->getContext())->toContain('exceeded max attempts after worker crash or timeout')
                ->and($thrown->getPrevious())->not->toBeNull()
                ->and($row)->not->toBeNull()
                ->and($row['reserved_at'])->not->toBeNull()
                ->and($recorder->calls)->toBe([])
                ->and($events->dispatched(JobFailedEvent::class))->toBe([]);
        },
    );

    it(
        'fails the job again, calling failed() once, after the store works and the reservation expires',
        function (): void {
            $connection = SqliteConnection::withQueueTables();
            ['queue' => $queue, 'recorder' => $recorder] = notStoredAttemptsQueue($connection);
            $failedJobs = new DatabaseFailedJobRepository($connection, DatabaseTimezoneConfig::fromName('UTC'));
            $id = $queue->push(new FailableCrashJob('crashy'), 'emails')->id;
            $queue->pop('emails');
            expireReservation($connection, $id);
            $connection->execute('ALTER TABLE failed_jobs RENAME TO failed_jobs_gone');

            expect(fn () => $queue->pop('emails'))->toThrow(FailedJobNotStoredException::class);

            $connection->execute('ALTER TABLE failed_jobs_gone RENAME TO failed_jobs');
            expireReservation($connection, $id);

            expect($queue->pop('emails'))->toBeNull()
                ->and(jobsRow($connection, $id))->toBeNull()
                ->and($failedJobs->find($id))->not->toBeNull()
                ->and($recorder->calls)->toHaveCount(1);
        },
    );
});
