<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Integration\Fixture\Job\AlwaysFailingJob;
use Marko\Integration\Fixture\Job\InvalidBackoffJob;
use Marko\Integration\Fixture\Job\PollingJob;
use Marko\Integration\Fixture\Job\PrivatePropertyJob;
use Marko\Integration\Fixture\Job\RecordingJob;
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
