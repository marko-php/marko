<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Queue\Command\FailedCommand;
use Marko\Queue\Command\QueueClearCommand;
use Marko\Queue\Command\RetryCommand;
use Marko\Queue\Command\StatusCommand;
use Marko\Queue\FailedJob;
use Marko\Queue\Plugin\RetryDeadlinePlugin;
use Marko\Queue\Redis\Tests\Fixtures\AlwaysFailingJob;
use Marko\Queue\Redis\Tests\Fixtures\DeadlineJob;
use Marko\Queue\Redis\Tests\Fixtures\IntegrationRedis;
use Marko\Queue\Redis\Tests\Fixtures\QueueHarness;
use Marko\Queue\Redis\Tests\Fixtures\TestJob;
use Marko\Queue\WorkerInterface;

/*
 * Runs the marko/queue worker, commands and retry limits on RedisQueue and RedisFailedJobRepository against a
 * real Redis server (see IntegrationRedis for the environment variables). None of it needs driver code.
 */

pest()->group('integration-services');

beforeEach(function (): void {
    if (IntegrationRedis::unavailable()) {
        $this->markTestSkipped(IntegrationRedis::skipReason());
    }

    TestJob::$handled = [];
    AlwaysFailingJob::$handled = 0;
    DeadlineJob::$handled = 0;
    $this->harnesses = [];
    $this->harness = function (array $config = []): QueueHarness {
        return $this->harnesses[] = new QueueHarness($config);
    };
});

afterEach(function (): void {
    foreach ($this->harnesses ?? [] as $harness) {
        $harness->flush();
    }
});

/**
 * Run a command resolved from the harness container and return its exit code and output.
 *
 * @param list<string> $arguments
 * @return array{0: int, 1: string}
 */
function runRedisQueueCommand(
    QueueHarness $harness,
    string $command,
    string $name,
    array $arguments = [],
): array {
    $stream = fopen('php://memory', 'r+');
    $exitCode = $harness->container->get($command)->execute(
        new Input(['marko', $name, ...$arguments]),
        new Output($stream),
    );
    rewind($stream);

    return [$exitCode, (string) stream_get_contents($stream)];
}

function storeRedisFailedJob(
    QueueHarness $harness,
    string $id,
    string $failedAt,
    string $queue = 'default',
): FailedJob {
    $failedJob = new FailedJob(
        id: $id,
        queue: $queue,
        payload: "payload-$id",
        exception: "exception-$id",
        failedAt: new DateTimeImmutable($failedAt),
    );
    $harness->failedJobs()->store($failedJob);

    return $failedJob;
}

describe('RedisQueue with the worker', function (): void {
    it('runs a pushed job and deletes it', function (): void {
        $harness = ($this->harness)();
        $harness->queue()->push(new TestJob('worked'));

        $harness->container->get(WorkerInterface::class)->work(once: true);

        expect(TestJob::$handled)->toBe(['worked'])
            ->and($harness->client()->keys($harness->prefix . '*'))->toBe([]);
    });

    it('retries a failing job maxAttempts times, then stores it as failed', function (): void {
        $harness = ($this->harness)();
        $id = $harness->queue()->push(new AlwaysFailingJob())->id;
        $worker = $harness->container->get(WorkerInterface::class);

        foreach (range(1, 5) as $ignored) {
            $worker->work(once: true);
        }

        expect(AlwaysFailingJob::$handled)->toBe(3)
            ->and($harness->failedJobs()->find($id)?->exception)->toContain('This job always fails')
            ->and($harness->client()->exists($harness->prefix . "jobs:$id"))->toBe(0);
    });

    it('fails a job once its exceptions reach maxExceptions', function (): void {
        $harness = ($this->harness)();
        $id = $harness->queue()->push(new AlwaysFailingJob(maxAttempts: 10, maxExceptions: 2))->id;
        $worker = $harness->container->get(WorkerInterface::class);

        foreach (range(1, 4) as $ignored) {
            $worker->work(once: true);
        }

        expect(AlwaysFailingJob::$handled)->toBe(2)
            ->and($harness->failedJobs()->find($id))->not->toBeNull();
    });

    it('keeps releasing a job past maxAttempts until its retry deadline, then fails it', function (): void {
        $harness = ($this->harness)();
        $job = new DeadlineJob('2026-10-31 12:10:00');
        new RetryDeadlinePlugin()->beforePush($job);
        $id = $harness->queue()->push($job)->id;
        $worker = $harness->container->get(WorkerInterface::class);

        foreach (range(1, 5) as $ignored) {
            $worker->work(once: true);
            $harness->clock->travel('+60 seconds');
        }

        expect(DeadlineJob::$handled)->toBe(5)
            ->and($harness->failedJobs()->count())->toBe(0);

        $harness->clock->travel('+10 minutes');
        $worker->work(once: true);

        expect(DeadlineJob::$handled)->toBe(5)
            ->and($harness->failedJobs()->find($id)?->exception)->toContain('retry deadline');
    });
});

describe('RedisFailedJobRepository', function (): void {
    it('stores, finds and counts failed jobs', function (): void {
        $harness = ($this->harness)();
        $stored = storeRedisFailedJob($harness, 'job-1', '2026-10-31 12:00:00.123456');

        $found = $harness->failedJobs()->find('job-1');

        expect($found)->toEqual($stored)
            ->and($harness->failedJobs()->find('missing'))->toBeNull()
            ->and($harness->failedJobs()->count())->toBe(1);
    });

    it('lists failed jobs newest first', function (): void {
        $harness = ($this->harness)();
        storeRedisFailedJob($harness, 'old', '2026-10-31 10:00:00');
        storeRedisFailedJob($harness, 'new', '2026-10-31 12:00:00');
        storeRedisFailedJob($harness, 'middle', '2026-10-31 11:00:00');

        expect(array_map(fn (FailedJob $job): string => $job->id, $harness->failedJobs()->all()))
            ->toBe(['new', 'middle', 'old']);
    });

    it('deletes one failed job and clears the rest', function (): void {
        $harness = ($this->harness)();
        storeRedisFailedJob($harness, 'a', '2026-10-31 10:00:00');
        storeRedisFailedJob($harness, 'b', '2026-10-31 11:00:00');
        storeRedisFailedJob($harness, 'c', '2026-10-31 12:00:00');

        expect($harness->failedJobs()->delete('a'))->toBeTrue()
            ->and($harness->failedJobs()->delete('a'))->toBeFalse()
            ->and($harness->failedJobs()->clear())->toBe(2)
            ->and($harness->failedJobs()->count())->toBe(0)
            ->and($harness->failedJobs()->all())->toBe([])
            ->and($harness->client()->keys($harness->prefix . '*'))->toBe([]);
    });
});

describe('queue commands on Redis', function (): void {
    it('lists failed jobs with queue:failed and pushes one back with queue:retry', function (): void {
        $harness = ($this->harness)(['queue.max_attempts' => 1]);
        $id = $harness->queue()->push(new AlwaysFailingJob(), 'emails')->id;
        $harness->container->get(WorkerInterface::class)->work(queues: ['emails'], once: true);

        [, $listed] = runRedisQueueCommand($harness, FailedCommand::class, 'queue:failed');
        [$exitCode, $retried] = runRedisQueueCommand($harness, RetryCommand::class, 'queue:retry', [$id]);
        $job = $harness->queue()->pop('emails');

        expect($listed)->toContain($id)
            ->and($listed)->toContain('emails')
            ->and($exitCode)->toBe(0)
            ->and($retried)->toContain("Job $id pushed back to queue.")
            ->and($harness->failedJobs()->count())->toBe(0)
            ->and($job)->toBeInstanceOf(AlwaysFailingJob::class)
            ->and($job->attempts)->toBe(0);
    });

    it('retries every failed job with queue:retry --all', function (): void {
        $harness = ($this->harness)(['queue.max_attempts' => 1]);
        $harness->queue()->push(new AlwaysFailingJob());
        $harness->queue()->push(new AlwaysFailingJob());
        $worker = $harness->container->get(WorkerInterface::class);
        $worker->work(once: true);
        $worker->work(once: true);

        [$exitCode, $output] = runRedisQueueCommand($harness, RetryCommand::class, 'queue:retry', ['--all']);

        expect($exitCode)->toBe(0)
            ->and($output)->toContain('2 jobs pushed back to queue.')
            ->and($harness->queue()->size())->toBe(2)
            ->and($harness->failedJobs()->count())->toBe(0);
    });

    it('reports pending and failed counts with queue:status', function (): void {
        $harness = ($this->harness)();
        $harness->queue()->push(new TestJob());
        $harness->queue()->push(new TestJob());
        $harness->queue()->later(60, new TestJob());
        storeRedisFailedJob($harness, 'failed-1', '2026-10-31 12:00:00');

        [, $output] = runRedisQueueCommand($harness, StatusCommand::class, 'queue:status');

        expect($output)->toContain('Queue Driver: redis')
            ->and($output)->toContain('Pending Jobs: 2')
            ->and($output)->toContain('Failed Jobs: 1');
    });

    it('clears a queue with queue:clear', function (): void {
        $harness = ($this->harness)();
        $harness->queue()->push(new TestJob(), 'emails');
        $harness->queue()->later(60, new TestJob(), 'emails');

        [, $output] = runRedisQueueCommand($harness, QueueClearCommand::class, 'queue:clear', ['--queue=emails']);

        expect($output)->toContain("Cleared 2 jobs from 'emails' queue.")
            ->and($harness->client()->keys($harness->prefix . '*'))->toBe([]);
    });
});

describe('RedisQueue with a blocking worker', function (): void {
    it('blocks on the only queue a worker works', function (): void {
        $harness = ($this->harness)(['queue-redis.block_for' => 1]);

        $start = microtime(true);
        $harness->container->get(WorkerInterface::class)->work(queues: ['default'], once: true);

        expect(microtime(true) - $start)->toBeGreaterThanOrEqual(0.9);
    });

    it('does not block when the worker works several queues', function (): void {
        $harness = ($this->harness)(['queue-redis.block_for' => 2]);

        $start = microtime(true);
        $harness->container->get(WorkerInterface::class)->work(queues: ['high', 'default', 'low'], once: true);

        expect(microtime(true) - $start)->toBeLessThan(1.0);
    });
});
