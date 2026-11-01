<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Log\LogLevel;
use Marko\Queue\Exceptions\FailedJobNotStoredException;
use Marko\Queue\FailableJobInterface;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobHandler;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobInterface;
use Marko\Queue\JobRunner;
use Marko\Queue\PushResult;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\RetryPolicy;
use Marko\Queue\Tests\Fixtures\FakeProcessControl;
use Marko\Queue\Worker;
use Marko\Queue\WorkerOptions;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeLogger;

/**
 * Failed-job repository whose store() always throws, like a database whose failed_jobs table is unreachable.
 */
class UnreachableFailedJobRepository implements FailedJobRepositoryInterface
{
    public function store(FailedJob $failedJob): void
    {
        throw new RuntimeException('failed_jobs is unreachable');
    }

    public function all(): array
    {
        return [];
    }

    public function find(string $id): ?FailedJob
    {
        return null;
    }

    public function delete(string $id): bool
    {
        return false;
    }

    public function clear(): int
    {
        return 0;
    }

    public function count(): int
    {
        return 0;
    }
}

/**
 * Queue that hands out its jobs in order (or throws a set pop error) and records pops, deletes and releases.
 */
class NotStoredRecordingQueue implements QueueInterface
{
    /** @var list<string> */
    public array $log = [];

    /**
     * @param list<JobInterface> $jobs
     */
    public function __construct(
        private array $jobs,
        public ?Throwable $popError = null,
    ) {}

    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        return PushResult::pushed('unused');
    }

    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        return PushResult::pushed('unused');
    }

    public function pop(
        ?string $queue = null,
        bool $block = false,
    ): ?JobInterface {
        if ($this->popError !== null) {
            $this->log[] = 'pop:error';

            throw $this->popError;
        }

        $job = array_shift($this->jobs);
        $this->log[] = 'pop:' . ($job?->id ?? 'empty');

        return $job;
    }

    public function size(
        ?string $queue = null,
    ): int {
        return count($this->jobs);
    }

    public function clear(
        ?string $queue = null,
    ): int {
        return 0;
    }

    public function delete(
        string $jobId,
    ): bool {
        $this->log[] = "delete:$jobId";

        return true;
    }

    public function release(
        JobInterface $job,
        int $delay = 0,
    ): bool {
        $this->log[] = "release:$job->id";

        return true;
    }
}

/**
 * Job on its last attempt that runs an optional callback, then throws, and records each failed() call.
 */
class NotStoredFailingJob extends Job implements FailableJobInterface
{
    public int $failedCalls = 0;

    public function __construct(
        private readonly ?Closure $during = null,
    ) {
        $this->maxAttempts = 1;
    }

    public function handle(): void
    {
        if ($this->during !== null) {
            ($this->during)();
        }

        throw new RuntimeException('job broke');
    }

    public function failed(Throwable $exception): void
    {
        $this->failedCalls++;
    }
}

class NotStoredSleepingWorker extends Worker
{
    public int $pauses = 0;

    protected function pause(
        int $seconds,
    ): void {
        $this->pauses++;
        $this->stop();
    }
}

function notStoredJob(
    string $id,
    ?Closure $during = null,
): NotStoredFailingJob {
    $job = new NotStoredFailingJob($during);
    $job->setId($id);

    return $job;
}

/**
 * @return array{worker: NotStoredSleepingWorker, logger: FakeLogger}
 */
function createNotStoredWorker(
    QueueInterface $queue,
    FakeProcessControl $processControl = new FakeProcessControl(),
): array {
    $logger = new FakeLogger();
    $envelope = new JobEnvelope(
        new EncryptionConfig(new FakeConfigRepository(['encryption.key' => 'worker-not-stored-key'])),
    );
    $config = new QueueConfig(new FakeConfigRepository([
        'queue.queue' => 'default',
        'queue.max_attempts' => 3,
        'queue.retry_after' => 90,
    ]));
    $worker = new NotStoredSleepingWorker(
        $queue,
        new FailedJobHandler(
            new UnreachableFailedJobRepository(),
            $envelope,
            new JobRunner(new Container(), $envelope, new FakeEventDispatcher()),
            new FakeEventDispatcher(),
            new FakeClock(),
            $logger,
        ),
        $config,
        new JobRunner(new Container(), $envelope, new FakeEventDispatcher()),
        new RetryPolicy($config, new FakeClock()),
        new FakeClock(),
        $logger,
        $processControl,
    );

    return ['worker' => $worker, 'logger' => $logger];
}

describe('Worker when a failed job cannot be stored', function (): void {
    it('stops with FailedJobNotStoredException instead of pulling more jobs', function (): void {
        $job = notStoredJob('broken');
        $queue = new NotStoredRecordingQueue([$job, notStoredJob('next')]);
        ['worker' => $worker] = createNotStoredWorker($queue);

        expect(fn () => $worker->work())->toThrow(
            FailedJobNotStoredException::class,
            'NotStoredFailingJob (id broken)',
        )
            ->and($queue->log)->toBe(['pop:broken'])
            ->and($job->failedCalls)->toBe(0);
    });

    it('leaves the job in its queue, neither deleted nor released, so it is failed again later', function (): void {
        $queue = new NotStoredRecordingQueue([notStoredJob('broken')]);
        ['worker' => $worker] = createNotStoredWorker($queue);

        try {
            $worker->work();
        } catch (FailedJobNotStoredException) {
            // Expected
        }

        expect($queue->log)->not->toContain('delete:broken')
            ->and($queue->log)->not->toContain('release:broken');
    });

    it('logs the failure at critical level with the job, the cause and the suggestion', function (): void {
        $queue = new NotStoredRecordingQueue([notStoredJob('broken')]);
        ['worker' => $worker, 'logger' => $logger] = createNotStoredWorker($queue);

        try {
            $worker->work();
        } catch (FailedJobNotStoredException) {
            // Expected
        }

        $critical = $logger->entriesForLevel(LogLevel::Critical);
        $message = $critical[0]['message'] ?? '';

        expect($critical)->toHaveCount(1)
            ->and($message)->toStartWith('[marko/queue] Worker stopping:')
            ->and($message)->toContain('NotStoredFailingJob (id broken)')
            ->and($message)->toContain('failed_jobs is unreachable')
            ->and($message)->toContain('job broke')
            ->and($message)->toContain('restart the worker');
    });

    it('rethrows FailedJobNotStoredException from pop() instead of logging it and polling again', function (): void {
        $notStored = FailedJobNotStoredException::failedJobNotStored(
            'App\Jobs\SendInvoice',
            'job-7',
            'default',
            new RuntimeException('attempts used up'),
            new RuntimeException('failed_jobs is unreachable'),
        );
        $queue = new NotStoredRecordingQueue([], popError: $notStored);
        ['worker' => $worker, 'logger' => $logger] = createNotStoredWorker($queue);

        expect(fn () => $worker->work())->toThrow($notStored)
            ->and($worker->pauses)->toBe(0)
            ->and($queue->log)->toBe(['pop:error'])
            ->and($logger->entriesForLevel(LogLevel::Error))->toBe([])
            ->and($logger->entriesForLevel(LogLevel::Critical))->toHaveCount(1);
    });

    it('still logs and polls again after any other pop failure', function (): void {
        $queue = new NotStoredRecordingQueue([], popError: new RuntimeException('Broker connection lost'));
        ['worker' => $worker, 'logger' => $logger] = createNotStoredWorker($queue);

        $worker->work();

        expect($worker->pauses)->toBe(1)
            ->and($logger->entriesForLevel(LogLevel::Error))->toHaveCount(1)
            ->and($logger->entriesForLevel(LogLevel::Critical))->toBe([]);
    });

    it('logs it at critical level and still exits with the timed-out status on a timeout', function (): void {
        $process = new FakeProcessControl();
        $queue = new NotStoredRecordingQueue([notStoredJob('slow', fn () => $process->fireTimer())]);
        ['worker' => $worker, 'logger' => $logger] = createNotStoredWorker($queue, $process);

        $worker->work(options: new WorkerOptions(timeout: 5));

        $critical = $logger->entriesForLevel(LogLevel::Critical);

        expect($process->terminatedWith)->toBe(Worker::EXIT_TIMED_OUT)
            ->and($critical)->toHaveCount(1)
            ->and($critical[0]['message'] ?? '')->toContain('NotStoredFailingJob (id slow)')
            ->and($queue->log)->toBe(['pop:slow']);
    });
});
