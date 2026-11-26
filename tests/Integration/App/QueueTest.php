<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Integration\Fixture\Job\AlwaysFailingJob;
use Marko\Integration\Fixture\Job\InsertAuthorJob;
use Marko\Integration\Fixture\Job\InvalidBackoffJob;
use Marko\Integration\Fixture\Job\PollingJob;
use Marko\Integration\Fixture\Job\PrivatePropertyJob;
use Marko\Integration\Fixture\Job\RecordingJob;
use Marko\Integration\Fixture\Job\TransactionLeakingJob;
use Marko\Integration\Fixture\Job\UniqueRecordingJob;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueInterface;

/*
 * The database queue driver through queue:work against real Postgres:
 * retries, failed jobs and payload encoding (#161), priority order and
 * backoff (#162), and an invalid backoff failing only its own job (#234).
 */

pest()->group('integration-services');

beforeEach(fn () => setUpIntegrationTest($this));

afterEach(fn () => tearDownIntegrationTest($this));

it('moves a job that always throws to failed_jobs after max_attempts', function (): void {
    $container = $this->app->container;
    $connection = $container->get(ConnectionInterface::class);
    $failedJobs = $container->get(FailedJobRepositoryInterface::class);
    $container->get(QueueInterface::class)->push(new AlwaysFailingJob());

    // max_attempts is 3 (Fixture/config/queue.php). After each of the first
    // two attempts the job is released with the configured backoff; make it
    // due again instead of waiting.
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $result = runIntegrationCommand($this->app, 'queue:work', ['--once']);

        expect($result['exitCode'])->toBe(0, $result['output'])
            ->and($connection->query('SELECT attempts FROM jobs'))->toBe([['attempts' => $attempt]])
            ->and($failedJobs->count())->toBe(0);

        $connection->execute("UPDATE jobs SET available_at = '2000-01-01 00:00:00'");
    }

    $result = runIntegrationCommand($this->app, 'queue:work', ['--once']);
    $failed = $failedJobs->all();

    expect($result['exitCode'])->toBe(0, $result['output']);

    expect($connection->query('SELECT id FROM jobs'))->toBe([])
        ->and($failed)->toHaveCount(1)
        ->and($failed[0]->exception)->toContain('AlwaysFailingJob failed on purpose');
})->issue(161);

it('round-trips a job payload containing NUL bytes on postgres', function (): void {
    $marker = $this->project . '/storage/private-property-job.txt';
    $job = new PrivatePropertyJob($marker);

    expect($job->serialize())->toContain("\0");

    $this->app->container->get(QueueInterface::class)->push($job);
    $result = runIntegrationCommand($this->app, 'queue:work', ['--once']);

    expect($result['exitCode'])->toBe(0, $result['output'])
        ->and(file_get_contents($marker))->toBe('private property job ran');
})->issue(161);

it('drains a higher-priority queue before a lower-priority one', function (): void {
    $queue = $this->app->container->get(QueueInterface::class);
    $low = $this->project . '/storage/low.txt';
    $high = $this->project . '/storage/high.txt';

    // Pushed first, so a worker ignoring priority would take it first.
    $queue->push(new RecordingJob($low, 'low ran'), 'low');
    $queue->push(new RecordingJob($high, 'high ran'), 'high');

    $result = runIntegrationCommand($this->app, 'queue:work', ['--once', '--queue=high,low']);

    expect($result['exitCode'])->toBe(0, $result['output'])
        ->and(file_get_contents($high))->toBe('high ran')
        ->and(file_exists($low))->toBeFalse()
        ->and($queue->size('high'))->toBe(0)
        ->and($queue->size('low'))->toBe(1);
})->issue(162);

it('waits the configured backoff before retrying a failed job', function (): void {
    $container = $this->app->container;
    $container->get(QueueInterface::class)->push(new AlwaysFailingJob());

    $before = time();
    $result = runIntegrationCommand($this->app, 'queue:work', ['--once']);
    $after = time();
    $rows = $container->get(ConnectionInterface::class)->query('SELECT attempts, available_at FROM jobs');
    $availableAt = (new DateTimeImmutable((string) $rows[0]['available_at']))->getTimestamp();

    // Fixture/config/queue.php sets backoff to 45 seconds; the built-in curve
    // would give 20 after the first attempt.
    expect($result['exitCode'])->toBe(0, $result['output'])
        ->and($rows)->toHaveCount(1)
        ->and((int) $rows[0]['attempts'])->toBe(1)
        ->and($availableAt)->toBeGreaterThanOrEqual($before + 45)
        ->and($availableAt)->toBeLessThanOrEqual($after + 45);
})->issue(162);

it('fails a job with an invalid backoff instead of stopping the worker', function (): void {
    $container = $this->app->container;
    $queue = $container->get(QueueInterface::class);
    $failedJobs = $container->get(FailedJobRepositoryInterface::class);
    $marker = $this->project . '/storage/after-invalid-backoff.txt';
    $queue->push(new InvalidBackoffJob());
    $queue->push(new RecordingJob($marker, 'next job ran'));

    // Attempt 1 of 3 fails, and [-5] cannot be used as a retry delay.
    $invalid = runIntegrationCommand($this->app, 'queue:work', ['--once']);
    $failed = $failedJobs->all();
    $next = runIntegrationCommand($this->app, 'queue:work', ['--once']);

    expect($invalid['exitCode'])->toBe(0, $invalid['output'])
        ->and($failed)->toHaveCount(1)
        ->and($failed[0]->exception)->toContain('InvalidBackoffJob failed on purpose')
        ->toContain('Job could not be retried')
        ->and($next['exitCode'])->toBe(0, $next['output'])
        ->and(file_get_contents($marker))->toBe('next job ran')
        ->and($queue->size())->toBe(0);
})->issue(234);

it(
    'keeps a self-releasing job with a retry deadline past max_attempts and fails it on its first exception',
    function (): void {
        $container = $this->app->container;
        $connection = $container->get(ConnectionInterface::class);
        $failedJobs = $container->get(FailedJobRepositoryInterface::class);
        $job = new PollingJob(throwOn: 6);
        $container->get(QueueInterface::class)->push($job);

        // The push plugin captured the deadline once, before the job was stored
        expect($job->retryDeadline)->not->toBeNull();

        // max_attempts is 3 (Fixture/config/queue.php); the deadline lifts that limit.
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $result = runIntegrationCommand($this->app, 'queue:work', ['--once']);

            expect($result['exitCode'])->toBe(0, $result['output'])
                ->and($connection->query('SELECT attempts FROM jobs'))->toBe([['attempts' => $attempt]])
                ->and($failedJobs->count())->toBe(0);
        }

        $result = runIntegrationCommand($this->app, 'queue:work', ['--once']);
        $failed = $failedJobs->all();

        expect($result['exitCode'])->toBe(0, $result['output'])
            ->and($connection->query('SELECT id FROM jobs'))->toBe([])
            ->and($failed)->toHaveCount(1)
            ->and($failed[0]->exception)->toContain('PollingJob failed on attempt 6')
            ->toContain('threw 1 of its 1 allowed exceptions');
    },
)->issue(536);

it('skips a duplicate unique job with a Redis lock and releases it once the worker runs the job', function (): void {
    $queue = $this->app->container->get(QueueInterface::class);
    $connection = $this->app->container->get(ConnectionInterface::class);
    $marker = $this->project . '/storage/unique-job.txt';
    // Redis outlives the fixture project, so every run takes its own lock
    $key = bin2hex(random_bytes(8));

    $first = $queue->push(new UniqueRecordingJob($key, $marker));
    $duplicate = $queue->push(new UniqueRecordingJob($key, $marker));
    $rows = $connection->query('SELECT id FROM jobs');

    $result = runIntegrationCommand($this->app, 'queue:work', ['--once']);
    $afterRun = $queue->push(new UniqueRecordingJob($key, $marker));

    expect($first->pushed)->toBeTrue()
        ->and($duplicate->pushed)->toBeFalse()
        ->and($duplicate->skipReason)->toContain('unique-job:' . UniqueRecordingJob::class . ":$key")
        ->and($rows)->toHaveCount(1)
        ->and($result['exitCode'])->toBe(0, $result['output'])
        ->and(file_get_contents($marker))->toBe("unique job $key ran")
        ->and($afterRun->pushed)->toBeTrue();
})->issue(534);

it('runs a queued job after the server terminates the worker\'s database session', function (): void {
    $container = $this->app->container;
    $connection = $container->get(ConnectionInterface::class);
    $failedJobs = $container->get(FailedJobRepositoryInterface::class);
    $killer = new PgSqlConnection($container->get(DatabaseConfig::class));

    try {
        $workerPid = (int) $connection->query('SELECT pg_backend_pid() AS pid')[0]['pid'];
        $terminated = $killer->query('SELECT pg_terminate_backend(:pid, 5000) AS terminated', ['pid' => $workerPid]);
    } finally {
        $killer->disconnect();
    }

    $container->get(QueueInterface::class)->push(new InsertAuthorJob('Written after the kill'));
    $result = runIntegrationCommand($this->app, 'queue:work', ['--once']);

    expect($terminated[0]['terminated'])->toBeTrue()
        ->and($result['exitCode'])->toBe(0, $result['output'])
        ->and($connection->query('SELECT name FROM authors'))->toBe([['name' => 'Written after the kill']])
        ->and($failedJobs->count())->toBe(0)
        ->and((int) $connection->query('SELECT pg_backend_pid() AS pid')[0]['pid'])->not->toBe($workerPid);
})->issue(717);

/**
 * Pushes a TransactionLeakingJob on the default queue and an InsertAuthorJob on
 * its own queue before the leaky job runs: after a returning job the container's
 * connection is still inside the leaked transaction, and a push made then would
 * be rolled back with it. `pid` is the container connection's session before the
 * leaky job ran.
 *
 * @return array{leaky: array{exitCode: int, output: string}, next: array{exitCode: int, output: string}, pid: int}
 */
function runLeakyJobThenNextJob(
    object $test,
    string $mode,
): array {
    $container = $test->app->container;
    $queue = $container->get(QueueInterface::class);
    $queue->push(new TransactionLeakingJob($mode));
    $queue->push(new InsertAuthorJob('Written by the next job'), 'next');

    $pid = (int) $container->get(ConnectionInterface::class)->query('SELECT pg_backend_pid() AS pid')[0]['pid'];
    $leaky = runIntegrationCommand($test->app, 'queue:work', ['--once']);
    $next = runIntegrationCommand($test->app, 'queue:work', ['--once', '--queue=next']);

    return ['leaky' => $leaky, 'next' => $next, 'pid' => $pid];
}

/**
 * Reads from a second, separately built connection, so only committed rows show.
 *
 * @return array{authors: array<int, string>, jobs: int, failedJobs: int}
 */
function committedQueueState(
    object $test,
): array {
    $reader = new PgSqlConnection($test->app->container->get(DatabaseConfig::class));

    try {
        return [
            'authors' => array_column($reader->query('SELECT name FROM authors ORDER BY id'), 'name'),
            'jobs' => count($reader->query('SELECT id FROM jobs')),
            'failedJobs' => count($reader->query('SELECT id FROM failed_jobs')),
        ];
    } finally {
        $reader->disconnect();
    }
}

it(
    'commits the next job\'s writes and the failed hook\'s after a job threw with a transaction open',
    function (): void {
        $run = runLeakyJobThenNextJob($this, TransactionLeakingJob::MODE_THROW);
        $state = committedQueueState($this);

        expect($run['leaky']['exitCode'])->toBe(0, $run['leaky']['output'])
            ->and($run['next']['exitCode'])->toBe(0, $run['next']['output'])
            ->and($state['authors'])->toBe([TransactionLeakingJob::FAILED_MARKER, 'Written by the next job'])
            ->and($state['failedJobs'])->toBe(1)
            ->and($state['jobs'])->toBe(0);
    },
)->issue(721);

it('runs the next job after a job lost its connection inside a transaction', function (): void {
    $run = runLeakyJobThenNextJob($this, TransactionLeakingJob::MODE_TERMINATE);
    $state = committedQueueState($this);
    $pidAfter = (int) $this->app->container->get(ConnectionInterface::class)
        ->query('SELECT pg_backend_pid() AS pid')[0]['pid'];

    expect($run['leaky']['exitCode'])->toBe(0, $run['leaky']['output'])
        ->and($run['next']['exitCode'])->toBe(0, $run['next']['output'])
        ->and($state['authors'])->toBe([TransactionLeakingJob::FAILED_MARKER, 'Written by the next job'])
        ->and($state['failedJobs'])->toBe(1)
        ->and($state['jobs'])->toBe(0)
        ->and($pidAfter)->not->toBe($run['pid']);
})->issue(721);

it('runs the next job on a clean connection after a job returned with a transaction open', function (): void {
    $run = runLeakyJobThenNextJob($this, TransactionLeakingJob::MODE_RETURN);
    $state = committedQueueState($this);

    // The leaky job's delete() was rolled back with its leaked transaction, so it
    // is still in jobs and runs again once its reservation expires.
    expect($run['leaky']['exitCode'])->toBe(0, $run['leaky']['output'])
        ->and($run['next']['exitCode'])->toBe(0, $run['next']['output'])
        ->and($state['authors'])->toBe(['Written by the next job'])
        ->and($state['failedJobs'])->toBe(0)
        ->and($state['jobs'])->toBe(1);
})->issue(721);
