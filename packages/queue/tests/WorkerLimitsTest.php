<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Log\LogLevel;
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
use Marko\Queue\RetryPolicy;
use Marko\Queue\StopCondition\MaxJobsStopCondition;
use Marko\Queue\StopCondition\MaxTimeStopCondition;
use Marko\Queue\StopCondition\MemoryStopCondition;
use Marko\Queue\StopCondition\RestartSignalStopCondition;
use Marko\Queue\Tests\Command\Helpers;
use Marko\Queue\Tests\Command\StubFailedJobRepository;
use Marko\Queue\Tests\Fixtures\FakeProcessControl;
use Marko\Queue\Tests\Fixtures\InMemoryRestartSignal;
use Marko\Queue\Worker;
use Marko\Queue\WorkerOptions;
use Marko\Queue\WorkerRunState;
use Marko\Queue\WorkerStopConditionInterface;
use Marko\Queue\WorkerStopReason;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeLogger;

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
        bool $block = false,
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
        ?int $timeout = null,
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->timeout = $timeout;
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
 * Worker that stops instead of sleeping once every queue is empty, or runs $onPause instead when set.
 */
class LimitsTestWorker extends Worker
{
    public ?Closure $onPause = null;

    public int $pauses = 0;

    protected function pause(
        int $seconds,
    ): void {
        $this->pauses++;

        if ($this->onPause === null) {
            $this->stop();

            return;
        }

        ($this->onPause)($this);
    }
}

function limitsJob(
    string $id,
    ?Closure $during = null,
    ?int $maxAttempts = 1,
    ?int $timeout = null,
): LimitsCallbackJob {
    $job = new LimitsCallbackJob($during, $maxAttempts, $timeout);
    $job->setId($id);

    return $job;
}

/**
 * @param array<string, mixed> $config Overrides for the queue.* config the worker reads
 * @return array{worker: LimitsTestWorker, failed: StubFailedJobRepository, logger: FakeLogger}
 */
function createLimitsWorker(
    QueueInterface $queue,
    FakeProcessControl $processControl,
    FakeClock $clock = new FakeClock(),
    array $config = [],
): array {
    $failed = Helpers::createStubFailedJobRepository();
    $logger = new FakeLogger();

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
            $logger,
        ),
        new QueueConfig(new FakeConfigRepository([
            'queue.queue' => 'default',
            'queue.max_attempts' => 3,
            'queue.retry_after' => 90,
            ...$config,
        ])),
        new JobRunner(new Container(), $envelope, new FakeEventDispatcher()),
        new RetryPolicy(new QueueConfig(new FakeConfigRepository([
            'queue.queue' => 'default',
            'queue.max_attempts' => 3,
        ])), new FakeClock()),
        logger: $logger,
        processControl: $processControl,
        clock: $clock,
    );

    return ['worker' => $worker, 'failed' => $failed, 'logger' => $logger];
}

/**
 * Every message the worker logged through the fake logger, one per line.
 */
function limitsLogged(
    FakeLogger $logger,
): string {
    return implode("\n", array_column($logger->entries, 'message'));
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
        ['worker' => $worker, 'failed' => $failed, 'logger' => $logger] = createLimitsWorker($queue, $process);

        $worker->work(options: new WorkerOptions(timeout: 5));
        $logged = limitsLogged($logger);

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

        $worker->work(options: new WorkerOptions(timeout: 5));

        expect($queue->log)->toBe(['pop:slow', 'release:slow:15'])
            ->and($failed->count())->toBe(0)
            ->and($process->terminatedWith)->toBe(1);
    });

    it('counts a timed-out attempt as an exception toward maxExceptions', function (): void {
        $process = new FakeProcessControl();
        $job = limitsJob('slow', fn () => $process->fireTimer(), maxAttempts: 3);
        $queue = new LimitsRecordingQueue([$job]);
        ['worker' => $worker] = createLimitsWorker($queue, $process);

        $worker->work(options: new WorkerOptions(timeout: 5));

        expect($job->exceptions)->toBe(1);
    });

    it('calls failed() with the timeout exception when the job times out on its last attempt', function (): void {
        $process = new FakeProcessControl();
        $job = limitsJob('slow', fn () => $process->fireTimer());
        $queue = new LimitsRecordingQueue([$job]);
        ['worker' => $worker] = createLimitsWorker($queue, $process);

        $worker->work(options: new WorkerOptions(timeout: 5));

        expect($job->failedWith)->toHaveCount(1)
            ->and($job->failedWith[0])->toBeInstanceOf(JobTimedOutException::class)
            ->and($job->failedWith[0]->getMessage())->toContain('exceeded the 5-second timeout')
            ->and($process->terminatedWith)->toBe(Worker::EXIT_TIMED_OUT);
    });

    it('does not call failed() on a timed-out attempt that is released for a retry', function (): void {
        $process = new FakeProcessControl();
        $job = limitsJob('slow', fn () => $process->fireTimer(), maxAttempts: 3);
        ['worker' => $worker] = createLimitsWorker(new LimitsRecordingQueue([$job]), $process);

        $worker->work(options: new WorkerOptions(timeout: 5));

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

        $reason = $worker->work(options: new WorkerOptions(maxJobs: 2));

        expect($queue->log)->toBe(['pop:a', 'delete:a', 'pop:b', 'delete:b'])
            ->and($queue->size())->toBe(1)
            ->and($reason)->toBe(WorkerStopReason::MaxJobs);
    });

    it('exits after a job once memory reaches the --memory limit', function (): void {
        $queue = new LimitsRecordingQueue([limitsJob('a'), limitsJob('b')]);
        ['worker' => $worker] = createLimitsWorker(
            $queue,
            new FakeProcessControl(memoryBytes: 128 * 1024 * 1024),
        );

        $reason = $worker->work(options: new WorkerOptions(memory: 128));

        expect($queue->log)->toBe(['pop:a', 'delete:a'])
            ->and($reason)->toBe(WorkerStopReason::Memory);
    });

    it('stops from an idle loop once memory reaches the --memory limit', function (): void {
        $process = new FakeProcessControl(memoryBytes: 64 * 1024 * 1024);
        $queue = new LimitsRecordingQueue([]);
        ['worker' => $worker] = createLimitsWorker($queue, $process);
        $worker->onPause = function () use ($process): void {
            $process->memoryBytes = 128 * 1024 * 1024;
        };

        $reason = $worker->work(options: new WorkerOptions(memory: 128));

        expect($reason)->toBe(WorkerStopReason::Memory)
            ->and($worker->pauses)->toBe(1);
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
            ->and($options->maxJobs)->toBe(0)
            ->and($options->maxTime)->toBe(0)
            ->and($options->stopConditions)->toBe([])
            ->and($options->buildStopConditions(new FakeClock()))->toBe([]);
    });

    it('rejects a negative limit', function (string $name, array $args): void {
        expect(fn () => new WorkerOptions(...$args))
            ->toThrow(QueueException::class, "Invalid --$name value for the queue worker.");
    })->with([
        'timeout' => ['timeout', ['timeout' => -1]],
        'memory' => ['memory', ['memory' => -1]],
        'max-jobs' => ['max-jobs', ['maxJobs' => -1]],
        'max-time' => ['max-time', ['maxTime' => -1]],
    ]);

    it('builds a stop condition for each limit that is on, followed by the extra conditions', function (): void {
        $extra = new class () implements WorkerStopConditionInterface
        {
            public function check(
                WorkerRunState $state,
            ): ?WorkerStopReason {
                return null;
            }
        };

        $conditions = new WorkerOptions(memory: 128, maxJobs: 10, maxTime: 3600, stopConditions: [$extra])
            ->buildStopConditions(new FakeClock());

        expect($conditions)->toHaveCount(4)
            ->and($conditions[0])->toBeInstanceOf(MaxJobsStopCondition::class)
            ->and($conditions[1])->toBeInstanceOf(MemoryStopCondition::class)
            ->and($conditions[2])->toBeInstanceOf(MaxTimeStopCondition::class)
            ->and($conditions[3])->toBe($extra);
    });

    it('rejects an extra stop condition that does not implement WorkerStopConditionInterface', function (): void {
        // @phpstan-ignore argument.type
        expect(fn () => new WorkerOptions(stopConditions: [new stdClass()]))
            ->toThrow(QueueException::class, 'Invalid stop condition for the queue worker.');
    });
});

describe('Worker per-job timeout', function (): void {
    it('uses the job timeout instead of the worker timeout', function (int $jobTimeout): void {
        $process = new FakeProcessControl();
        $queue = new LimitsRecordingQueue([limitsJob('job-1', timeout: $jobTimeout)]);
        ['worker' => $worker] = createLimitsWorker($queue, $process);

        $worker->work(options: new WorkerOptions(timeout: 30));

        expect($process->calls)->toContain("startTimer:$jobTimeout")
            ->and($process->calls)->not->toContain('startTimer:30');
    })->with([
        'shorter' => [5],
        'longer' => [60],
    ]);

    it('uses the worker timeout for a job that sets none', function (): void {
        $process = new FakeProcessControl();
        $queue = new LimitsRecordingQueue([limitsJob('job-1')]);
        ['worker' => $worker] = createLimitsWorker($queue, $process);

        $worker->work(options: new WorkerOptions(timeout: 30));

        expect($process->calls)->toContain('startTimer:30');
    });

    it('turns the timeout off for a job that sets 0', function (): void {
        $process = new FakeProcessControl();
        $queue = new LimitsRecordingQueue([limitsJob('job-1', timeout: 0)]);
        ['worker' => $worker] = createLimitsWorker($queue, $process);

        $worker->work(options: new WorkerOptions(timeout: 30));

        expect($process->calls)->toBe(['listen', 'stopListening']);
    });

    it('reports the job timeout when a job exceeds it', function (): void {
        $process = new FakeProcessControl();
        $queue = new LimitsRecordingQueue([limitsJob('slow', fn () => $process->fireTimer(), timeout: 10)]);
        ['worker' => $worker, 'failed' => $failed] = createLimitsWorker($queue, $process);

        $worker->work(options: new WorkerOptions(timeout: 30));

        expect($failed->find('slow')->exception)->toContain("Job 'LimitsCallbackJob' exceeded the 10-second timeout.")
            ->and($process->terminatedWith)->toBe(Worker::EXIT_TIMED_OUT);
    });

    it('fails a job whose timeout is not below queue.retry_after without running it', function (int $timeout): void {
        $ran = false;
        $process = new FakeProcessControl();
        $job = limitsJob('long', function () use (&$ran): void {
            $ran = true;
        }, maxAttempts: 3, timeout: $timeout);
        $queue = new LimitsRecordingQueue([$job, limitsJob('next')]);
        ['worker' => $worker, 'failed' => $failed] = createLimitsWorker($queue, $process);

        $worker->work();

        $stored = $failed->find('long')?->exception;

        expect($ran)->toBeFalse()
            ->and($job->attempts)->toBe(0)
            ->and($process->calls)->not->toContain("startTimer:$timeout")
            ->and($queue->log)->toBe(['pop:long', 'delete:long', 'pop:next', 'delete:next', 'pop:empty'])
            ->and($stored)->toContain("Job 'LimitsCallbackJob' has a {$timeout}s timeout")
            ->and($stored)->toContain('queue.retry_after (90s)')
            ->and($job->failedWith)->toHaveCount(1)
            ->and($job->failedWith[0])->toBeInstanceOf(QueueException::class)
            ->and($job->failedWith[0]->getSuggestion())->toContain('Raise queue.retry_after');
    })->with([
        'equal' => [90],
        'above' => [120],
    ]);

    it('checks a job\'s timeout against the retry_after of the queue it was popped from', function (): void {
        $ran = false;
        $process = new FakeProcessControl();
        $job = limitsJob('render', function () use (&$ran): void {
            $ran = true;
        }, timeout: 3600);
        $queue = new LimitsRecordingQueue([$job]);
        ['worker' => $worker, 'failed' => $failed] = createLimitsWorker($queue, $process, config: [
            'queue.queues' => ['engine' => ['retry_after' => 3900]],
        ]);

        $worker->work(queues: ['engine']);

        expect($ran)->toBeTrue()
            ->and($failed->find('render'))->toBeNull()
            ->and($process->calls)->toContain('startTimer:3600');
    });

    it('fails a job whose timeout is not below its queue\'s own retry_after', function (): void {
        $ran = false;
        $process = new FakeProcessControl();
        $job = limitsJob('mail', function () use (&$ran): void {
            $ran = true;
        }, maxAttempts: 3, timeout: 60);
        $queue = new LimitsRecordingQueue([$job]);
        ['worker' => $worker, 'failed' => $failed] = createLimitsWorker($queue, $process, config: [
            'queue.retry_after' => 3900,
            'queue.queues' => ['mail' => ['retry_after' => 30]],
        ]);

        $worker->work(queues: ['mail']);

        $stored = $failed->find('mail')?->exception;

        expect($ran)->toBeFalse()
            ->and($stored)->toContain("Job 'LimitsCallbackJob' has a 60s timeout")
            ->and($stored)->toContain("queue.queues.mail.retry_after (30s) on queue 'mail'")
            ->and($job->failedWith[0]->getSuggestion())->toContain('Raise queue.queues.mail.retry_after');
    });

    it('warns when a job sets a timeout but ext-pcntl is not loaded', function (): void {
        $queue = new LimitsRecordingQueue([limitsJob('job-1', timeout: 10)]);
        ['worker' => $worker, 'logger' => $logger] = createLimitsWorker(
            $queue,
            new FakeProcessControl(supported: false),
        );

        $worker->work();
        $logged = limitsLogged($logger);

        expect($logged)->toContain(
            "[marko/queue] Job 'LimitsCallbackJob' sets a 10-second timeout, but ext-pcntl is not loaded",
        )
            ->and($logger->entriesForLevel(LogLevel::Warning))->toHaveCount(1)
            ->and($queue->log)->toContain('delete:job-1');
    });

    it('does not warn about ext-pcntl for a job without a timeout', function (): void {
        $queue = new LimitsRecordingQueue([limitsJob('job-1')]);
        ['worker' => $worker, 'logger' => $logger] = createLimitsWorker(
            $queue,
            new FakeProcessControl(supported: false),
        );

        $worker->work();
        $logged = limitsLogged($logger);

        expect($logged)->not->toContain('ext-pcntl');
    });
});

describe('Worker --max-time', function (): void {
    it('exits after the current job once --max-time has passed', function (): void {
        $clock = new FakeClock('2026-01-01 00:00:00');
        $queue = new LimitsRecordingQueue([
            limitsJob('a'),
            limitsJob('b', fn () => $clock->travel('+3600 seconds')),
            limitsJob('c'),
        ]);
        ['worker' => $worker, 'logger' => $logger] = createLimitsWorker($queue, new FakeProcessControl(), $clock);

        $reason = $worker->work(options: new WorkerOptions(maxTime: 3600));
        $logged = limitsLogged($logger);

        expect($reason)->toBe(WorkerStopReason::MaxTime)
            ->and($queue->log)->toBe(['pop:a', 'delete:a', 'pop:b', 'delete:b'])
            ->and($logged)->toContain('[marko/queue] Worker stopping: ' . WorkerStopReason::MaxTime->message());
    });

    it('exits from an idle loop once --max-time has passed', function (): void {
        $clock = new FakeClock('2026-01-01 00:00:00');
        $queue = new LimitsRecordingQueue([]);
        ['worker' => $worker] = createLimitsWorker($queue, new FakeProcessControl(), $clock);
        $worker->onPause = fn () => $clock->travel('+30 seconds');

        $reason = $worker->work(options: new WorkerOptions(maxTime: 60));

        expect($reason)->toBe(WorkerStopReason::MaxTime)
            ->and($worker->pauses)->toBe(2)
            ->and($queue->log)->toBe(['pop:empty', 'pop:empty']);
    });
});

describe('Worker restart signal', function (): void {
    it('exits after the current job once a restart is requested, logging the restart', function (): void {
        $signal = new InMemoryRestartSignal();
        $queue = new LimitsRecordingQueue([
            limitsJob('a'),
            limitsJob('b', fn () => $signal->request()),
            limitsJob('c'),
        ]);
        ['worker' => $worker, 'logger' => $logger] = createLimitsWorker($queue, new FakeProcessControl());
        $options = new WorkerOptions(stopConditions: [new RestartSignalStopCondition($signal)]);

        $reason = $worker->work(options: $options);
        $logged = limitsLogged($logger);

        expect($reason)->toBe(WorkerStopReason::RestartRequested)
            ->and($reason->exitCode())->toBe(0)
            ->and($queue->log)->toBe(['pop:a', 'delete:a', 'pop:b', 'delete:b'])
            ->and($logged)->toContain(
                '[marko/queue] Worker stopping: ' . WorkerStopReason::RestartRequested->message(),
            );
    });

    it('ignores a restart requested before the worker started', function (): void {
        $signal = new InMemoryRestartSignal();
        $signal->request();
        $queue = new LimitsRecordingQueue([limitsJob('a'), limitsJob('b')]);
        ['worker' => $worker] = createLimitsWorker($queue, new FakeProcessControl());
        $options = new WorkerOptions(stopConditions: [new RestartSignalStopCondition($signal)]);

        $reason = $worker->work(options: $options);

        // The test worker stops itself at the first idle pause, so both jobs ran
        expect($reason)->toBe(WorkerStopReason::Signal)
            ->and($queue->log)->toBe(['pop:a', 'delete:a', 'pop:b', 'delete:b', 'pop:empty']);
    });

    it('picks up a restart requested while the worker is idle', function (): void {
        $signal = new InMemoryRestartSignal();
        $queue = new LimitsRecordingQueue([]);
        ['worker' => $worker] = createLimitsWorker($queue, new FakeProcessControl());
        $worker->onPause = function () use ($worker, $signal): void {
            if ($worker->pauses === 2) {
                $signal->request();
            }
        };
        $options = new WorkerOptions(stopConditions: [new RestartSignalStopCondition($signal)]);

        $reason = $worker->work(options: $options);

        expect($reason)->toBe(WorkerStopReason::RestartRequested)
            ->and($worker->pauses)->toBe(2)
            ->and($queue->log)->toBe(['pop:empty', 'pop:empty']);
    });
});

describe('Worker stop reasons', function (): void {
    it('logs the stop line at info level through the injected logger', function (): void {
        ['worker' => $worker, 'logger' => $logger] = createLimitsWorker(
            new LimitsRecordingQueue([]),
            new FakeProcessControl(),
        );

        $worker->work(once: true);

        $logger->assertLogged('[marko/queue] Worker stopping: ' . WorkerStopReason::Once->message(), LogLevel::Info);
        expect($logger->entries)->toHaveCount(1);
    });

    it('logs a timed-out job and its stop line through the injected logger', function (): void {
        $process = new FakeProcessControl();
        $queue = new LimitsRecordingQueue([limitsJob('slow', fn () => $process->fireTimer())]);
        ['worker' => $worker, 'logger' => $logger] = createLimitsWorker($queue, $process);

        $worker->work(options: new WorkerOptions(timeout: 5));

        $errors = array_column($logger->entriesForLevel(LogLevel::Error), 'message');

        expect($errors)->toHaveCount(1)
            ->and($errors[0])->toStartWith("[marko/queue] Job 'LimitsCallbackJob' exceeded the 5-second timeout.");
        $logger->assertLogged(
            '[marko/queue] Worker stopping: ' . WorkerStopReason::TimedOut->message(),
            LogLevel::Info,
        );
    });

    it('returns Once after one job with --once', function (): void {
        $queue = new LimitsRecordingQueue([limitsJob('a'), limitsJob('b')]);
        ['worker' => $worker, 'logger' => $logger] = createLimitsWorker($queue, new FakeProcessControl());

        $reason = $worker->work(once: true);
        $logged = limitsLogged($logger);

        expect($reason)->toBe(WorkerStopReason::Once)
            ->and($logged)->toContain('[marko/queue] Worker stopping: ' . WorkerStopReason::Once->message());
    });

    it('returns Once when --once finds every queue empty', function (): void {
        ['worker' => $worker] = createLimitsWorker(new LimitsRecordingQueue([]), new FakeProcessControl());

        $reason = $worker->work(once: true);

        expect($reason)->toBe(WorkerStopReason::Once);
    });

    it('returns Signal after SIGTERM, once the current job is done', function (): void {
        $process = new FakeProcessControl();
        $queue = new LimitsRecordingQueue([limitsJob('a', fn () => $process->sendTerminationSignal())]);
        ['worker' => $worker, 'logger' => $logger] = createLimitsWorker($queue, $process);

        $reason = $worker->work();
        $logged = limitsLogged($logger);

        expect($reason)->toBe(WorkerStopReason::Signal)
            ->and($logged)->toContain('[marko/queue] Worker stopping: ' . WorkerStopReason::Signal->message());
    });

    it('returns TimedOut when a job exceeds its timeout, logging the stop once', function (): void {
        $process = new FakeProcessControl();
        $queue = new LimitsRecordingQueue([limitsJob('slow', fn () => $process->fireTimer())]);
        ['worker' => $worker, 'logger' => $logger] = createLimitsWorker($queue, $process);

        $reason = $worker->work(options: new WorkerOptions(timeout: 5));
        $logged = limitsLogged($logger);

        expect($reason)->toBe(WorkerStopReason::TimedOut)
            ->and(substr_count($logged, '[marko/queue] Worker stopping: '))->toBe(1)
            ->and($logged)->toContain('[marko/queue] Worker stopping: ' . WorkerStopReason::TimedOut->message());
    });

    it('logs exactly one stop line per run', function (): void {
        $queue = new LimitsRecordingQueue([limitsJob('a'), limitsJob('b')]);
        ['worker' => $worker, 'logger' => $logger] = createLimitsWorker($queue, new FakeProcessControl());

        $worker->work(options: new WorkerOptions(maxJobs: 1));
        $logged = limitsLogged($logger);

        expect(substr_count($logged, '[marko/queue] Worker stopping: '))->toBe(1)
            ->and($logged)->toContain('[marko/queue] Worker stopping: ' . WorkerStopReason::MaxJobs->message());
    });

    it('stops with the reason returned by an extra stop condition', function (): void {
        $condition = new class () implements WorkerStopConditionInterface
        {
            /** @var list<WorkerRunState> */
            public array $states = [];

            public function check(
                WorkerRunState $state,
            ): ?WorkerStopReason {
                $this->states[] = $state;

                return $state->jobsProcessed >= 2 ? WorkerStopReason::RestartRequested : null;
            }
        };
        $clock = new FakeClock('2026-01-01 00:00:00');
        $queue = new LimitsRecordingQueue([limitsJob('a'), limitsJob('b'), limitsJob('c')]);
        ['worker' => $worker] = createLimitsWorker($queue, new FakeProcessControl(memoryBytes: 1024), $clock);

        $reason = $worker->work(options: new WorkerOptions(stopConditions: [$condition]));

        expect($reason)->toBe(WorkerStopReason::RestartRequested)
            ->and($condition->states)->toHaveCount(2)
            ->and($condition->states[1]->jobsProcessed)->toBe(2)
            ->and($condition->states[1]->memoryBytes)->toBe(1024)
            ->and($condition->states[1]->startedAt->format('Y-m-d H:i:s'))->toBe('2026-01-01 00:00:00');
    });
});
