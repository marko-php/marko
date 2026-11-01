<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\Exceptions\JobTimedOutException;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\FailableJobInterface;
use Marko\Queue\FailedJobHandler;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobInterface;
use Marko\Queue\JobRunner;
use Marko\Queue\PushResult;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\Tests\Command\Helpers;
use Marko\Queue\Tests\Command\StubFailedJobRepository;
use Marko\Queue\Tests\Fixtures\FakeProcessControl;
use Marko\Queue\Worker;
use Marko\Queue\WorkerOptions;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;

/**
 * In-memory queue that records pops, deletes and releases in order.
 */
class LimitsRecordingQueue implements QueueInterface
{
    /** @var list<string> */
    public array $log = [];

    public ?Throwable $popError = null;

    /**
     * @param list<JobInterface> $jobs
     */
    public function __construct(
        private array $jobs,
    ) {}

    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        $this->jobs[] = $job;

        return PushResult::pushed($job->id ?? 'pushed');
    }

    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        return PushResult::pushed('later');
    }

    public function pop(
        ?string $queue = null,
    ): ?JobInterface {
        if ($this->popError !== null) {
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
        $this->log[] = "release:$job->id:$delay";

        return true;
    }
}

/**
 * Job that runs a callback in handle(), so a test can fire a signal or the timer mid-job.
 */
class LimitsCallbackJob extends Job implements FailableJobInterface
{
    public protected(set) ?int $maxAttempts = 1;

    /** @var list<Throwable> */
    public array $failedWith = [];

    public protected(set) array|int|null $backoff = 15;

    public function __construct(
        private readonly ?Closure $during = null,
        ?int $maxAttempts = 1,
    ) {
        $this->maxAttempts = $maxAttempts;
    }

    public function handle(): void
    {
        if ($this->during !== null) {
            ($this->during)();
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->failedWith[] = $exception;
    }
}

/**
 * Job that fails inside a function called with a secret argument.
 */
class LimitsSecretArgumentJob extends Job
{
    public protected(set) ?int $maxAttempts = 1;

    public function handle(): void
    {
        $this->connect('super-secret-password');
    }

    private function connect(
        string $password,
    ): void {
        throw new RuntimeException('Connection refused');
    }
}

/**
 * Worker that stops instead of sleeping once every queue is empty.
 */
class LimitsTestWorker extends Worker
{
    protected function pause(
        int $seconds,
    ): void {
        $this->stop();
    }
}

function limitsJob(
    string $id,
    ?Closure $during = null,
    ?int $maxAttempts = 1,
): LimitsCallbackJob {
    $job = new LimitsCallbackJob($during, $maxAttempts);
    $job->setId($id);

    return $job;
}

/**
 * @return array{worker: LimitsTestWorker, failed: StubFailedJobRepository}
 */
function createLimitsWorker(
    QueueInterface $queue,
    FakeProcessControl $processControl,
): array {
    $failed = Helpers::createStubFailedJobRepository();

    $envelope = new JobEnvelope(
        new EncryptionConfig(new FakeConfigRepository(['encryption.key' => 'worker-limits-key'])),
    );
    $worker = new LimitsTestWorker(
        $queue,
        new FailedJobHandler(
            $failed,
            $envelope,
            new JobRunner(new Container(), $envelope, new FakeEventDispatcher()),
            new FakeEventDispatcher(),
            new FakeClock(),
        ),
        new QueueConfig(new FakeConfigRepository([
            'queue.queue' => 'default',
            'queue.max_attempts' => 3,
        ])),
        new JobRunner(new Container(), $envelope, new FakeEventDispatcher()),
        processControl: $processControl,
    );

    return ['worker' => $worker, 'failed' => $failed];
}

/**
 * Point PHP's error log at a temp file for the callback and return what was logged.
 */
function captureLimitsErrorLog(
    callable $callback,
): string {
    $file = tempnam(sys_get_temp_dir(), 'marko-worker-limits-log');
    $previous = ini_set('error_log', $file);

    try {
        $callback();
    } finally {
        ini_set('error_log', (string) $previous);
    }

    $logged = (string) file_get_contents($file);
    unlink($file);

    return $logged;
}

describe('Worker job timeout', function (): void {
    it('starts a timer for each job and cancels it once the job finishes', function (): void {
        $process = new FakeProcessControl();
        $queue = new LimitsRecordingQueue([limitsJob('job-1')]);
        ['worker' => $worker] = createLimitsWorker($queue, $process);

        $worker->work(options: new WorkerOptions(timeout: 30));

        expect($process->calls)->toBe(['listen', 'startTimer:30', 'cancelTimer', 'stopListening'])
            ->and($queue->log)->toContain('delete:job-1');
    });

    it('starts no timer when the timeout is 0', function (): void {
        $process = new FakeProcessControl();
        ['worker' => $worker] = createLimitsWorker(new LimitsRecordingQueue([limitsJob('job-1')]), $process);

        $worker->work(options: new WorkerOptions(timeout: 0));

        expect($process->calls)->toBe(['listen', 'stopListening']);
    });

    it('fails a job that exceeds the timeout and ends the worker process', function (): void {
        $process = new FakeProcessControl();
        $queue = new LimitsRecordingQueue([
            limitsJob('slow', fn () => $process->fireTimer()),
            limitsJob('next'),
        ]);
        ['worker' => $worker, 'failed' => $failed] = createLimitsWorker($queue, $process);

        $logged = captureLimitsErrorLog(fn () => $worker->work(options: new WorkerOptions(timeout: 5)));

        expect($failed->find('slow'))->not->toBeNull()
            ->and($failed->find('slow')->exception)->toContain(
                "Job 'LimitsCallbackJob' exceeded the 5-second timeout.",
            )
            ->and($process->terminatedWith)->toBe(Worker::EXIT_TIMED_OUT)
            ->and($queue->log)->toBe(['pop:slow', 'delete:slow'])
            ->and($logged)->toContain('exceeded the 5-second timeout')
            ->and($logged)->toContain('exiting with status 1');
    });

    it('releases a timed-out job with its backoff when it has attempts left', function (): void {
        $process = new FakeProcessControl();
        $queue = new LimitsRecordingQueue([limitsJob('slow', fn () => $process->fireTimer(), maxAttempts: 3)]);
        ['worker' => $worker, 'failed' => $failed] = createLimitsWorker($queue, $process);

        captureLimitsErrorLog(fn () => $worker->work(options: new WorkerOptions(timeout: 5)));

        expect($queue->log)->toBe(['pop:slow', 'release:slow:15'])
            ->and($failed->count())->toBe(0)
            ->and($process->terminatedWith)->toBe(1);
    });

    it('calls failed() with the timeout exception when the job times out on its last attempt', function (): void {
        $process = new FakeProcessControl();
        $job = limitsJob('slow', fn () => $process->fireTimer());
        $queue = new LimitsRecordingQueue([$job]);
        ['worker' => $worker] = createLimitsWorker($queue, $process);

        captureLimitsErrorLog(fn () => $worker->work(options: new WorkerOptions(timeout: 5)));

        expect($job->failedWith)->toHaveCount(1)
            ->and($job->failedWith[0])->toBeInstanceOf(JobTimedOutException::class)
            ->and($job->failedWith[0]->getMessage())->toContain('exceeded the 5-second timeout')
            ->and($process->terminatedWith)->toBe(Worker::EXIT_TIMED_OUT);
    });

    it('does not call failed() on a timed-out attempt that is released for a retry', function (): void {
        $process = new FakeProcessControl();
        $job = limitsJob('slow', fn () => $process->fireTimer(), maxAttempts: 3);
        ['worker' => $worker] = createLimitsWorker(new LimitsRecordingQueue([$job]), $process);

        captureLimitsErrorLog(fn () => $worker->work(options: new WorkerOptions(timeout: 5)));

        expect($job->failedWith)->toBe([]);
    });
});

describe('Worker graceful shutdown', function (): void {
    it('finishes the current job on SIGTERM, then exits without popping another', function (): void {
        $process = new FakeProcessControl();
        $queue = new LimitsRecordingQueue([
            limitsJob('current', fn () => $process->sendTerminationSignal()),
            limitsJob('next'),
        ]);
        ['worker' => $worker] = createLimitsWorker($queue, $process);

        $worker->work();

        expect($queue->log)->toBe(['pop:current', 'delete:current'])
            ->and($process->terminatedWith)->toBeNull()
            ->and($process->calls)->toBe(['listen', 'stopListening']);
    });

    it('restores signal handling even when a --once pop failure is thrown', function (): void {
        $process = new FakeProcessControl();
        $queue = new LimitsRecordingQueue([]);
        $queue->popError = new RuntimeException('Broker connection lost');
        ['worker' => $worker] = createLimitsWorker($queue, $process);

        expect(fn () => $worker->work(once: true))->toThrow(RuntimeException::class, 'Broker connection lost')
            ->and($process->calls)->toBe(['listen', 'stopListening']);
    });
});

describe('Worker memory and job limits', function (): void {
    it('exits after --max-jobs jobs', function (): void {
        $queue = new LimitsRecordingQueue([limitsJob('a'), limitsJob('b'), limitsJob('c')]);
        ['worker' => $worker] = createLimitsWorker($queue, new FakeProcessControl());

        $worker->work(options: new WorkerOptions(maxJobs: 2));

        expect($queue->log)->toBe(['pop:a', 'delete:a', 'pop:b', 'delete:b'])
            ->and($queue->size())->toBe(1);
    });

    it('exits after a job once memory reaches the --memory limit', function (): void {
        $queue = new LimitsRecordingQueue([limitsJob('a'), limitsJob('b')]);
        ['worker' => $worker] = createLimitsWorker(
            $queue,
            new FakeProcessControl(memoryBytes: 128 * 1024 * 1024),
        );

        $worker->work(options: new WorkerOptions(memory: 128));

        expect($queue->log)->toBe(['pop:a', 'delete:a']);
    });

    it('keeps working while memory stays below the --memory limit', function (): void {
        $queue = new LimitsRecordingQueue([limitsJob('a'), limitsJob('b')]);
        ['worker' => $worker] = createLimitsWorker(
            $queue,
            new FakeProcessControl(memoryBytes: 64 * 1024 * 1024),
        );

        $worker->work(options: new WorkerOptions(memory: 128));

        expect($queue->log)->toBe(['pop:a', 'delete:a', 'pop:b', 'delete:b', 'pop:empty']);
    });
});

describe('Worker failed job trace', function (): void {
    it('stores the trace without call arguments, which can hold secrets', function (): void {
        $previous = ini_set('zend.exception_ignore_args', '0');

        try {
            $job = new LimitsSecretArgumentJob();
            $job->setId('secret');
            $queue = new LimitsRecordingQueue([$job]);
            ['worker' => $worker, 'failed' => $failed] = createLimitsWorker($queue, new FakeProcessControl());

            $worker->work(once: true);
        } finally {
            ini_set('zend.exception_ignore_args', (string) $previous);
        }

        $exception = $failed->find('secret')->exception;

        expect($exception)->toStartWith("Connection refused\n#0 ")
            ->and($exception)->toContain('LimitsSecretArgumentJob->connect()')
            ->and($exception)->toContain('{main}')
            ->and($exception)->not->toContain('super-secret');
    });
});

describe('WorkerOptions', function (): void {
    it('defaults every limit to 0, meaning off', function (): void {
        $options = new WorkerOptions();

        expect($options->timeout)->toBe(0)
            ->and($options->memory)->toBe(0)
            ->and($options->maxJobs)->toBe(0);
    });

    it('rejects a negative limit', function (string $name, array $args): void {
        expect(fn () => new WorkerOptions(...$args))
            ->toThrow(QueueException::class, "Invalid --$name value for the queue worker.");
    })->with([
        'timeout' => ['timeout', ['timeout' => -1]],
        'memory' => ['memory', ['memory' => -1]],
        'max-jobs' => ['max-jobs', ['maxJobs' => -1]],
    ]);
});
