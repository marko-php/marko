<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Queue\Command\WorkCommand;
use Marko\Queue\ProcessControlInterface;
use Marko\Queue\QueueConfig;
use Marko\Queue\RestartSignalInterface;
use Marko\Queue\StopCondition\RestartSignalStopCondition;
use Marko\Queue\Tests\Command\Helpers;
use Marko\Queue\Tests\Fixtures\FakeProcessControl;
use Marko\Queue\Tests\Fixtures\InMemoryRestartSignal;
use Marko\Queue\WorkerInterface;
use Marko\Queue\WorkerOptions;
use Marko\Queue\WorkerRunState;
use Marko\Queue\WorkerStopReason;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * Worker stub that records the arguments queue:work passes to it.
 */
class CapturingWorker implements WorkerInterface
{
    public bool $called = false;

    /** @var list<string>|null */
    public ?array $queues = null;

    public ?bool $once = null;

    public ?int $sleep = null;

    public ?WorkerOptions $options = null;

    public function __construct(
        private readonly WorkerStopReason $reason = WorkerStopReason::Signal,
    ) {}

    public function work(
        ?array $queues = null,
        bool $once = false,
        int $sleep = 3,
        WorkerOptions $options = new WorkerOptions(),
    ): WorkerStopReason {
        $this->called = true;
        $this->queues = $queues;
        $this->once = $once;
        $this->sleep = $sleep;
        $this->options = $options;

        return $this->reason;
    }

    public function stop(): void {}
}

function createWorkCommand(
    WorkerInterface $worker,
    QueueConfig $config,
    ProcessControlInterface $processControl = new FakeProcessControl(),
    RestartSignalInterface $restartSignal = new InMemoryRestartSignal(),
): WorkCommand {
    return new WorkCommand($worker, $config, $processControl, $restartSignal);
}

/**
 * Helper to execute WorkCommand and return output.
 *
 * @param array<string> $args
 *
 * @return array{output: string, exitCode: int}
 */
function executeWorkCommand(
    WorkCommand $command,
    array $args = ['marko', 'queue:work'],
): array {
    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    $flags = new ReflectionClass(WorkCommand::class)->getAttributes(Command::class)[0]->newInstance()->flags;
    $input = new Input($args, $flags);

    $exitCode = $command->execute($input, $output);
    $result = Helpers::getOutputContent($stream);

    return ['output' => $result, 'exitCode' => $exitCode];
}

/**
 * Queue config for queue:work, with no backoff unless one is given.
 *
 * @param array<string, mixed> $values
 */
function createWorkCommandConfig(
    array $values = [],
): QueueConfig {
    return new QueueConfig(new FakeConfigRepository(array_merge([
        'queue.queue' => 'default',
        'queue.retry_after' => 90,
        'queue.timeout' => 60,
    ], $values)));
}

it('registers as queue:work command via #[Command] attribute', function (): void {
    $reflection = new ReflectionClass(WorkCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('queue:work');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(WorkCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('processes jobs continuously', function (): void {
    $jobsProcessed = 0;
    $maxJobs = 3;

    // Create a worker that processes a few jobs then stops
    $worker = new class ($jobsProcessed, $maxJobs) implements WorkerInterface
    {
        public function __construct(
            private int &$processed,
            private readonly int $max,
        ) {}

        public function work(
            ?array $queues = null,
            bool $once = false,
            int $sleep = 3,
            WorkerOptions $options = new WorkerOptions(),
        ): WorkerStopReason {
            // Simulate processing multiple jobs
            while ($this->processed < $this->max) {
                $this->processed++;
            }

            return WorkerStopReason::MaxJobs;
        }

        public function stop(): void {}
    };

    $command = createWorkCommand($worker, createWorkCommandConfig());
    ['exitCode' => $exitCode] = executeWorkCommand($command);

    expect($jobsProcessed)->toBe(3)
        ->and($exitCode)->toBe(0);
});

it('supports once flag', function (): void {
    $worker = new CapturingWorker();

    executeWorkCommand(createWorkCommand($worker, createWorkCommandConfig()), ['marko', 'queue:work', '--once']);

    expect($worker->once)->toBeTrue();
});

it('passes a single queue as a one-item list', function (): void {
    $worker = new CapturingWorker();

    executeWorkCommand(
        createWorkCommand($worker, createWorkCommandConfig()),
        ['marko', 'queue:work', '--queue=emails'],
    );

    expect($worker->queues)->toBe(['emails']);
});

it('works the emails queue for queue:work --queue emails', function (): void {
    $worker = new CapturingWorker();

    executeWorkCommand(
        createWorkCommand($worker, createWorkCommandConfig()),
        ['marko', 'queue:work', '--once', '--queue', 'emails', '--sleep', '5'],
    );

    expect($worker->queues)->toBe(['emails'])
        ->and($worker->once)->toBeTrue()
        ->and($worker->sleep)->toBe(5);
});

it('parses --queue=a,b --sleep=1 into a queue list and sleep', function (): void {
    $worker = new CapturingWorker();

    executeWorkCommand(
        createWorkCommand($worker, createWorkCommandConfig()),
        ['marko', 'queue:work', '--queue=a,b', '--sleep=1'],
    );

    expect($worker->queues)->toBe(['a', 'b'])
        ->and($worker->sleep)->toBe(1);
});

it('keeps the priority order of --queue=high,default,low', function (): void {
    $worker = new CapturingWorker();

    executeWorkCommand(
        createWorkCommand($worker, createWorkCommandConfig()),
        ['marko', 'queue:work', '--queue=high,default,low'],
    );

    expect($worker->queues)->toBe(['high', 'default', 'low']);
});

it('trims whitespace around queue names', function (): void {
    $worker = new CapturingWorker();

    executeWorkCommand(
        createWorkCommand($worker, createWorkCommandConfig()),
        ['marko', 'queue:work', '--queue', ' high , low '],
    );

    expect($worker->queues)->toBe(['high', 'low']);
});

it('drops empty segments such as --queue=high,,low', function (): void {
    $worker = new CapturingWorker();

    executeWorkCommand(
        createWorkCommand($worker, createWorkCommandConfig()),
        ['marko', 'queue:work', '--queue=high,,low,'],
    );

    expect($worker->queues)->toBe(['high', 'low']);
});

it('passes null when no queue option is given', function (): void {
    $worker = new CapturingWorker();

    executeWorkCommand(createWorkCommand($worker, createWorkCommandConfig()));

    expect($worker->called)->toBeTrue()
        ->and($worker->queues)->toBeNull();
});

it('fails loudly when --queue contains no queue names', function (): void {
    $worker = new CapturingWorker();

    ['output' => $output, 'exitCode' => $exitCode] = executeWorkCommand(
        createWorkCommand($worker, createWorkCommandConfig()),
        ['marko', 'queue:work', '--queue=,'],
    );

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('--queue needs at least one queue name')
        ->and($worker->called)->toBeFalse();
});

it('declares once as a flag on queue:work', function (): void {
    $attribute = new ReflectionClass(WorkCommand::class)->getAttributes(Command::class)[0]->newInstance();

    expect($attribute->flags)->toBe(['once']);
});

it('supports sleep option', function (): void {
    $worker = new CapturingWorker();

    executeWorkCommand(createWorkCommand($worker, createWorkCommandConfig()), ['marko', 'queue:work', '--sleep=5']);

    expect($worker->sleep)->toBe(5);
});

it('displays processing status', function (): void {
    ['output' => $output] = executeWorkCommand(createWorkCommand(new CapturingWorker(), createWorkCommandConfig()));

    expect($output)->toContain('Processing jobs from queue');
});

it('refuses to start with an invalid queue.backoff config', function (): void {
    ['output' => $output, 'exitCode' => $exitCode] = executeWorkCommand(
        createWorkCommand(new CapturingWorker(), createWorkCommandConfig(['queue.backoff' => [10, -5]])),
    );

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Invalid queue backoff in config queue.backoff.')
        ->and($output)->toContain('every backoff list entry must be a non-negative int; got -5')
        ->and($output)->toContain('Set backoff to a non-negative int')
        ->and($output)->not->toContain('Processing jobs from queue');
});

it('does not start the worker when queue.backoff is invalid', function (): void {
    $worker = new CapturingWorker();

    executeWorkCommand(createWorkCommand($worker, createWorkCommandConfig(['queue.backoff' => -1])));

    expect($worker->called)->toBeFalse();
});

it('starts the worker when queue.backoff is valid', function (): void {
    $worker = new CapturingWorker();

    ['exitCode' => $exitCode] = executeWorkCommand(
        createWorkCommand($worker, createWorkCommandConfig(['queue.backoff' => [5, 30]])),
    );

    expect($exitCode)->toBe(0)
        ->and($worker->called)->toBeTrue();
});

describe('queue:work exit code', function (): void {
    it('returns the exit code of the reason the worker stopped', function (WorkerStopReason $reason): void {
        ['exitCode' => $exitCode] = executeWorkCommand(
            createWorkCommand(new CapturingWorker($reason), createWorkCommandConfig()),
        );

        expect($exitCode)->toBe($reason->exitCode());
    })->with(array_map(fn (WorkerStopReason $reason): array => [$reason], WorkerStopReason::cases()));

    it('returns 1 when the worker stopped because a job timed out', function (): void {
        ['exitCode' => $exitCode] = executeWorkCommand(
            createWorkCommand(new CapturingWorker(WorkerStopReason::TimedOut), createWorkCommandConfig()),
        );

        expect($exitCode)->toBe(1);
    });
});

describe('queue:work restart signal', function (): void {
    it('passes a restart signal stop condition to the worker', function (): void {
        $worker = new CapturingWorker();

        executeWorkCommand(createWorkCommand($worker, createWorkCommandConfig()));

        expect($worker->options?->stopConditions)->toHaveCount(1)
            ->and($worker->options?->stopConditions[0])->toBeInstanceOf(RestartSignalStopCondition::class);
    });

    it('stops the worker only for a restart requested after queue:work started', function (): void {
        $worker = new CapturingWorker();
        $signal = new InMemoryRestartSignal('requested-at-last-deploy');
        $state = new WorkerRunState(new DateTimeImmutable(), 0, 0);

        executeWorkCommand(createWorkCommand($worker, createWorkCommandConfig(), restartSignal: $signal));
        $condition = $worker->options?->stopConditions[0];

        expect($condition?->check($state))->toBeNull();

        $signal->request();

        expect($condition?->check($state))->toBe(WorkerStopReason::RestartRequested);
    });

    it('exits with status 0 when the worker stopped for a restart', function (): void {
        ['exitCode' => $exitCode] = executeWorkCommand(
            createWorkCommand(new CapturingWorker(WorkerStopReason::RestartRequested), createWorkCommandConfig()),
        );

        expect($exitCode)->toBe(0);
    });
});

describe('queue:work limits', function (): void {
    it('uses queue.timeout as the default job timeout, with memory and max-jobs off', function (): void {
        $worker = new CapturingWorker();

        executeWorkCommand(createWorkCommand($worker, createWorkCommandConfig(['queue.timeout' => 45])));

        expect($worker->options?->timeout)->toBe(45)
            ->and($worker->options?->memory)->toBe(0)
            ->and($worker->options?->maxJobs)->toBe(0);
    });

    it('passes --timeout, --memory and --max-jobs to the worker', function (): void {
        $worker = new CapturingWorker();

        executeWorkCommand(
            createWorkCommand($worker, createWorkCommandConfig()),
            ['marko', 'queue:work', '--timeout=30', '--memory=256', '--max-jobs', '500'],
        );

        expect($worker->options?->timeout)->toBe(30)
            ->and($worker->options?->memory)->toBe(256)
            ->and($worker->options?->maxJobs)->toBe(500);
    });

    it('accepts --timeout=0 to turn the timeout off', function (): void {
        $worker = new CapturingWorker();

        executeWorkCommand(
            createWorkCommand($worker, createWorkCommandConfig()),
            ['marko', 'queue:work', '--timeout=0'],
        );

        expect($worker->options?->timeout)->toBe(0);
    });

    it('refuses a limit that is not a non-negative whole number', function (string $arg, string $message): void {
        $worker = new CapturingWorker();

        ['output' => $output, 'exitCode' => $exitCode] = executeWorkCommand(
            createWorkCommand($worker, createWorkCommandConfig()),
            ['marko', 'queue:work', $arg],
        );

        expect($exitCode)->toBe(1)
            ->and($output)->toContain($message)
            ->and($worker->called)->toBeFalse();
    })->with([
        'text timeout' => ['--timeout=soon', "--timeout must be a whole number of zero or more; got 'soon'."],
        'negative memory' => ['--memory=-5', "--memory must be a whole number of zero or more; got '-5'."],
        'fractional max-jobs' => ['--max-jobs=1.5', "--max-jobs must be a whole number of zero or more; got '1.5'."],
        'negative max-time' => ['--max-time=-1', "--max-time must be a whole number of zero or more; got '-1'."],
        'text max-time' => ['--max-time=hour', "--max-time must be a whole number of zero or more; got 'hour'."],
    ]);

    it('passes --max-time to the worker, off by default', function (): void {
        $default = new CapturingWorker();
        $limited = new CapturingWorker();

        executeWorkCommand(createWorkCommand($default, createWorkCommandConfig()));
        executeWorkCommand(
            createWorkCommand($limited, createWorkCommandConfig()),
            ['marko', 'queue:work', '--max-time=3600'],
        );

        expect($default->options?->maxTime)->toBe(0)
            ->and($limited->options?->maxTime)->toBe(3600);
    });

    it('refuses a timeout that is not below queue.retry_after, so a job cannot run twice', function (): void {
        $worker = new CapturingWorker();

        ['output' => $output, 'exitCode' => $exitCode] = executeWorkCommand(
            createWorkCommand($worker, createWorkCommandConfig(['queue.retry_after' => 90])),
            ['marko', 'queue:work', '--timeout=90'],
        );

        expect($exitCode)->toBe(1)
            ->and($output)->toContain(
                "The queue worker timeout (90s) must be shorter than queue.retry_after (90s) on queue 'default'.",
            )
            ->and($output)->toContain('so it would run twice at once')
            ->and($worker->called)->toBeFalse();
    });

    it('checks the timeout against the default queue\'s own retry_after when --queue is not given', function (): void {
        $worker = new CapturingWorker();

        ['exitCode' => $exitCode] = executeWorkCommand(
            createWorkCommand($worker, createWorkCommandConfig([
                'queue.queue' => 'engine',
                'queue.queues' => ['engine' => ['retry_after' => 3900]],
            ])),
            ['marko', 'queue:work', '--timeout=3600'],
        );

        expect($exitCode)->toBe(0)
            ->and($worker->options?->timeout)->toBe(3600);
    });

    it('accepts a timeout below the retry_after of every queue in --queue', function (): void {
        $worker = new CapturingWorker();

        ['exitCode' => $exitCode] = executeWorkCommand(
            createWorkCommand($worker, createWorkCommandConfig([
                'queue.retry_after' => 90,
                'queue.queues' => ['engine' => ['retry_after' => 3900], 'render' => ['retry_after' => 4000]],
            ])),
            ['marko', 'queue:work', '--queue=engine,render', '--timeout=3600'],
        );

        expect($exitCode)->toBe(0)
            ->and($worker->queues)->toBe(['engine', 'render']);
    });

    it('refuses a timeout that is not below the retry_after of any queue in --queue', function (): void {
        $worker = new CapturingWorker();

        ['output' => $output, 'exitCode' => $exitCode] = executeWorkCommand(
            createWorkCommand($worker, createWorkCommandConfig([
                'queue.retry_after' => 90,
                'queue.queues' => ['engine' => ['retry_after' => 3900]],
            ])),
            ['marko', 'queue:work', '--queue=engine,default', '--timeout=3600'],
        );

        expect($exitCode)->toBe(1)
            ->and($output)->toContain(
                "The queue worker timeout (3600s) must be shorter than queue.retry_after (90s) on queue 'default'.",
            )
            ->and($output)->toContain('queue.queues.default.retry_after')
            ->and($worker->called)->toBeFalse();
    });

    it('names the per-queue retry_after key when a queue\'s own retry_after is too short', function (): void {
        $worker = new CapturingWorker();

        ['output' => $output, 'exitCode' => $exitCode] = executeWorkCommand(
            createWorkCommand($worker, createWorkCommandConfig([
                'queue.retry_after' => 3900,
                'queue.queues' => ['mail' => ['retry_after' => 60]],
            ])),
            ['marko', 'queue:work', '--queue=mail', '--timeout=600'],
        );

        expect($exitCode)->toBe(1)
            ->and($output)->toContain(
                'The queue worker timeout (600s) must be shorter than queue.queues.mail.retry_after (60s)'
                . " on queue 'mail'.",
            )
            ->and($worker->called)->toBeFalse();
    });

    it('reports an invalid per-queue retry_after instead of starting', function (): void {
        $worker = new CapturingWorker();

        ['output' => $output, 'exitCode' => $exitCode] = executeWorkCommand(
            createWorkCommand($worker, createWorkCommandConfig([
                'queue.queues' => ['engine' => ['retry_after' => 'long']],
            ])),
            ['marko', 'queue:work', '--queue=engine'],
        );

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('Invalid queue.queues.engine.retry_after')
            ->and($worker->called)->toBeFalse();
    });

    it('warns that timeouts and graceful shutdown need ext-pcntl when it is missing', function (): void {
        $worker = new CapturingWorker();

        ['output' => $output, 'exitCode' => $exitCode] = executeWorkCommand(
            createWorkCommand($worker, createWorkCommandConfig(), new FakeProcessControl(supported: false)),
        );

        expect($exitCode)->toBe(0)
            ->and($output)->toContain('Warning: ext-pcntl is not loaded, so job timeouts are not enforced')
            ->and($worker->called)->toBeTrue();
    });

    it('does not warn when ext-pcntl is available', function (): void {
        ['output' => $output] = executeWorkCommand(
            createWorkCommand(new CapturingWorker(), createWorkCommandConfig(), new FakeProcessControl()),
        );

        expect($output)->not->toContain('Warning');
    });
});
