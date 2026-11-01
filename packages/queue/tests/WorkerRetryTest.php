<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\Event\JobFailedEvent;
use Marko\Queue\Exceptions\JobAttemptsExhaustedException;
use Marko\Queue\Exceptions\JobRetryDeadlinePassedException;
use Marko\Queue\FailableJobInterface;
use Marko\Queue\FailedJobHandler;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobInterface;
use Marko\Queue\JobRunner;
use Marko\Queue\PushResult;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\RetryPolicy;
use Marko\Queue\Tests\Command\Helpers;
use Marko\Queue\Tests\Command\StubFailedJobRepository;
use Marko\Queue\Worker;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;

/**
 * A polling job: it releases itself while the remote engine is busy, and throws when a step in
 * $throwOn says so. Its state lives in a static log because the queue stores a serialized copy.
 */
class WorkerRetryPollingJob extends Job implements FailableJobInterface
{
    /** @var list<string> */
    public static array $log = [];

    /**
     * @param list<int> $throwOn Attempt numbers whose handle() throws
     */
    public function __construct(
        ?int $maxAttempts = null,
        ?int $maxExceptions = null,
        private readonly array $throwOn = [],
        private readonly ?int $releaseFor = 30,
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->maxExceptions = $maxExceptions;
        $this->backoff = 5;
    }

    public function handle(): void
    {
        if (in_array($this->attempts, $this->throwOn, true)) {
            self::$log[] = "throw:$this->attempts";

            throw new RuntimeException("Render engine failed on attempt $this->attempts");
        }

        if ($this->releaseFor !== null) {
            self::$log[] = "release:$this->attempts";
            $this->release($this->releaseFor);
        }
    }

    public function failed(
        Throwable $exception,
    ): void {
        self::$log[] = 'failed:' . $exception::class;
    }
}

/**
 * Holds one job and stores a serialized copy on release, as a real driver persists the job it is given.
 */
class WorkerRetryPersistingQueue implements QueueInterface
{
    private ?string $stored;

    private bool $reserved = false;

    /** @var list<int> */
    public array $releaseDelays = [];

    public bool $deleted = false;

    public function __construct(
        JobInterface $job,
    ) {
        $job->setId('job-1');
        $this->stored = serialize($job);
    }

    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        return PushResult::pushed('job-1');
    }

    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        return PushResult::pushed('job-1');
    }

    public function pop(
        ?string $queue = null,
    ): ?JobInterface {
        if ($this->stored === null || $this->reserved) {
            return null;
        }

        $this->reserved = true;

        return unserialize($this->stored);
    }

    public function size(
        ?string $queue = null,
    ): int {
        return $this->stored === null ? 0 : 1;
    }

    public function clear(
        ?string $queue = null,
    ): int {
        return 0;
    }

    public function delete(
        string $jobId,
    ): bool {
        $this->stored = null;
        $this->deleted = true;

        return true;
    }

    public function release(
        JobInterface $job,
        int $delay = 0,
    ): bool {
        $this->stored = serialize($job);
        $this->reserved = false;
        $this->releaseDelays[] = $delay;

        return true;
    }

    public function current(): JobInterface
    {
        return unserialize((string) $this->stored);
    }
}

/**
 * @return array{worker: Worker, failed: StubFailedJobRepository, events: FakeEventDispatcher}
 */
function createWorkerRetryWorker(
    QueueInterface $queue,
    FakeClock $clock,
): array {
    $failed = Helpers::createStubFailedJobRepository();
    $events = new FakeEventDispatcher();
    $envelope = new JobEnvelope(new EncryptionConfig(new FakeConfigRepository(['encryption.key' => 'retry-key'])));
    $config = new QueueConfig(new FakeConfigRepository(['queue.queue' => 'default', 'queue.max_attempts' => 3]));
    $runner = new JobRunner(new Container(), $envelope, new FakeEventDispatcher());

    $worker = new Worker(
        $queue,
        new FailedJobHandler($failed, $envelope, $runner, $events, $clock),
        $config,
        $runner,
        new RetryPolicy($config, $clock),
        clock: new FakeClock(),
    );

    return ['worker' => $worker, 'failed' => $failed, 'events' => $events];
}

/**
 * Run the worker once per attempt, moving the clock forward by the given seconds before each one.
 */
function runWorkerRetryAttempts(
    Worker $worker,
    FakeClock $clock,
    int $attempts,
    int $secondsBetween = 30,
): void {
    for ($i = 0; $i < $attempts; $i++) {
        $worker->work(once: true);
        $clock->travel("+$secondsBetween seconds");
    }
}

beforeEach(function (): void {
    WorkerRetryPollingJob::$log = [];
});

describe('Worker self-release', function (): void {
    it(
        'releases a job that asked for it after the delay it asked for, without counting an exception',
        function (): void {
            $clock = new FakeClock('2026-10-31 12:00:00');
            $queue = new WorkerRetryPersistingQueue(new WorkerRetryPollingJob(maxAttempts: 5));
            ['worker' => $worker] = createWorkerRetryWorker($queue, $clock);

            $worker->work(once: true);

            expect($queue->releaseDelays)->toBe([30])
                ->and($queue->deleted)->toBeFalse()
                ->and($queue->current()->exceptions)->toBe(0)
                ->and($queue->current()->attempts)->toBe(1);
        },
    );

    it('fails a self-releasing job without a deadline once it runs out of attempts', function (): void {
        $clock = new FakeClock('2026-10-31 12:00:00');
        $queue = new WorkerRetryPersistingQueue(new WorkerRetryPollingJob());
        ['worker' => $worker, 'failed' => $failed] = createWorkerRetryWorker($queue, $clock);

        runWorkerRetryAttempts($worker, $clock, 3);

        expect($queue->deleted)->toBeTrue()
            ->and($failed->count())->toBe(1)
            ->and(WorkerRetryPollingJob::$log)->toContain('failed:' . JobAttemptsExhaustedException::class);
    });

    it(
        'does not fail a job with a deadline that releases itself more than queue.max_attempts times',
        function (): void {
            $clock = new FakeClock('2026-10-31 12:00:00');
            $job = new WorkerRetryPollingJob();
            $job->setRetryDeadline(new DateTimeImmutable('2026-10-31 18:00:00'));
            $queue = new WorkerRetryPersistingQueue($job);
            ['worker' => $worker, 'failed' => $failed] = createWorkerRetryWorker($queue, $clock);

            runWorkerRetryAttempts($worker, $clock, 20);

            expect($failed->count())->toBe(0)
                ->and($queue->deleted)->toBeFalse()
                ->and($queue->current()->attempts)->toBe(20)
                ->and($queue->releaseDelays)->toHaveCount(20);
        },
    );
});

describe('Worker retry deadline', function (): void {
    it('fails a self-releasing job once its captured deadline has passed', function (): void {
        $clock = new FakeClock('2026-10-31 12:00:00');
        $job = new WorkerRetryPollingJob();
        $job->setRetryDeadline(new DateTimeImmutable('2026-10-31 12:05:00'));
        $queue = new WorkerRetryPersistingQueue($job);
        ['worker' => $worker, 'failed' => $failed, 'events' => $events] = createWorkerRetryWorker($queue, $clock);

        // Attempts run at 12:00:00, 12:01:00, ..., the sixth at 12:05:00, the deadline
        runWorkerRetryAttempts($worker, $clock, 6, secondsBetween: 60);

        expect($queue->deleted)->toBeTrue()
            ->and($failed->count())->toBe(1)
            ->and($queue->releaseDelays)->toHaveCount(5)
            ->and(WorkerRetryPollingJob::$log)->toContain('failed:' . JobRetryDeadlinePassedException::class)
            ->and($events->dispatched)->toHaveCount(1)
            ->and($events->dispatched[0])->toBeInstanceOf(JobFailedEvent::class);
    });

    it('fails a job that throws after its deadline, keeping its own exception', function (): void {
        $clock = new FakeClock('2026-10-31 12:00:00');
        $job = new WorkerRetryPollingJob(throwOn: [1]);
        $job->setRetryDeadline(new DateTimeImmutable('2026-10-31 11:00:00'));
        $queue = new WorkerRetryPersistingQueue($job);
        ['worker' => $worker, 'failed' => $failed] = createWorkerRetryWorker($queue, $clock);

        $worker->work(once: true);

        expect($failed->count())->toBe(1)
            ->and(WorkerRetryPollingJob::$log)->toBe(['throw:1', 'failed:' . RuntimeException::class])
            ->and($failed->all()[0]->exception)->toContain('Render engine failed on attempt 1')
            ->and($failed->all()[0]->exception)->toContain('passed its retry deadline');
    });
});

describe('Worker maxExceptions', function (): void {
    it('does not count releases toward maxExceptions', function (): void {
        $clock = new FakeClock('2026-10-31 12:00:00');
        $job = new WorkerRetryPollingJob(maxExceptions: 2, throwOn: [4]);
        $job->setRetryDeadline(new DateTimeImmutable('2026-10-31 18:00:00'));
        $queue = new WorkerRetryPersistingQueue($job);
        ['worker' => $worker, 'failed' => $failed] = createWorkerRetryWorker($queue, $clock);

        runWorkerRetryAttempts($worker, $clock, 6);

        expect($failed->count())->toBe(0)
            ->and($queue->current()->exceptions)->toBe(1)
            ->and($queue->current()->attempts)->toBe(6);
    });

    it('fails a job with maxExceptions 1 and a deadline on its first exception', function (): void {
        $clock = new FakeClock('2026-10-31 12:00:00');
        $job = new WorkerRetryPollingJob(maxExceptions: 1, throwOn: [8]);
        $job->setRetryDeadline(new DateTimeImmutable('2026-10-31 18:00:00'));
        $queue = new WorkerRetryPersistingQueue($job);
        ['worker' => $worker, 'failed' => $failed] = createWorkerRetryWorker($queue, $clock);

        runWorkerRetryAttempts($worker, $clock, 8);

        expect($failed->count())->toBe(1)
            ->and($queue->deleted)->toBeTrue()
            ->and(WorkerRetryPollingJob::$log[7])->toBe('throw:8')
            ->and(WorkerRetryPollingJob::$log[8])->toBe('failed:' . RuntimeException::class)
            ->and($failed->all()[0]->exception)->toContain('threw 1 of its 1 allowed exceptions');
    });

    it('keeps the exception count across releases so the limit is reached over several attempts', function (): void {
        $clock = new FakeClock('2026-10-31 12:00:00');
        $job = new WorkerRetryPollingJob(maxExceptions: 2, throwOn: [2, 5]);
        $job->setRetryDeadline(new DateTimeImmutable('2026-10-31 18:00:00'));
        $queue = new WorkerRetryPersistingQueue($job);
        ['worker' => $worker, 'failed' => $failed] = createWorkerRetryWorker($queue, $clock);

        runWorkerRetryAttempts($worker, $clock, 4);

        expect($failed->count())->toBe(0)
            ->and($queue->current()->exceptions)->toBe(1)
            ->and($queue->releaseDelays)->toBe([30, 5, 30, 30]);

        runWorkerRetryAttempts($worker, $clock, 1);

        expect($failed->count())->toBe(1);
    });
});
