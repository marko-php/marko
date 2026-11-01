<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\Event\JobFailedEvent;
use Marko\Queue\FailableJobInterface;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobHandler;
use Marko\Queue\FailedJobHandlerInterface;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobRunner;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;

function createFailedJobHandlerEnvelope(): JobEnvelope
{
    return new JobEnvelope(new EncryptionConfig(new FakeConfigRepository(['encryption.key' => 'failed-handler-key'])));
}

/**
 * Failed-job repository that keeps stored jobs in memory and appends 'store:<id>' to a shared log.
 */
class LoggingFailedJobRepository implements FailedJobRepositoryInterface
{
    /** @var array<string, FailedJob> */
    public array $stored = [];

    /**
     * @param ArrayObject<int, string> $log
     */
    public function __construct(
        private readonly ArrayObject $log = new ArrayObject(),
    ) {}

    public function store(FailedJob $failedJob): void
    {
        $this->log[] = 'store:' . $failedJob->id;
        $this->stored[$failedJob->id] = $failedJob;
    }

    public function all(): array
    {
        return array_values($this->stored);
    }

    public function find(string $id): ?FailedJob
    {
        return $this->stored[$id] ?? null;
    }

    public function delete(string $id): bool
    {
        unset($this->stored[$id]);

        return true;
    }

    public function clear(): int
    {
        $count = count($this->stored);
        $this->stored = [];

        return $count;
    }

    public function count(): int
    {
        return count($this->stored);
    }
}

/**
 * Event dispatcher that appends the class of each event to a shared log.
 */
class LoggingEventDispatcher implements EventDispatcherInterface
{
    /** @var list<Event> */
    public array $events = [];

    /**
     * @param ArrayObject<int, string> $log
     */
    public function __construct(
        private readonly ArrayObject $log = new ArrayObject(),
    ) {}

    public function dispatch(Event $event): void
    {
        $this->log[] = 'event:' . $event::class;
        $this->events[] = $event;
    }
}

class HandlerFailableJob extends Job implements FailableJobInterface
{
    /** @var list<Throwable> */
    public array $failedWith = [];

    /**
     * @param ArrayObject<int, string>|null $log
     */
    public function __construct(
        private readonly ?ArrayObject $log = null,
        private readonly bool $failedThrows = false,
    ) {}

    public function handle(): void {}

    public function failed(Throwable $exception): void
    {
        $this->log?->append('failed:' . $exception->getMessage());
        $this->failedWith[] = $exception;

        if ($this->failedThrows) {
            throw new LogicException('cleanup broke');
        }
    }
}

class HandlerContainerAwareFailableJob extends Job implements ContainerAwareJobInterface, FailableJobInterface
{
    private ?ContainerInterface $container = null;

    public ?object $resolvedInFailed = null;

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function setJobEnvelope(JobEnvelope $jobEnvelope): void {}

    public function releaseContainer(): void
    {
        $this->container = null;
    }

    public function handle(): void {}

    public function failed(Throwable $exception): void
    {
        $this->resolvedInFailed = $this->container?->get(HandlerCleanupService::class);
    }
}

class HandlerCleanupService {}

/**
 * @return array{handler: FailedJobHandler, repository: LoggingFailedJobRepository, events: LoggingEventDispatcher}
 */
function createLoggingFailedJobHandler(
    ArrayObject $log = new ArrayObject(),
    ContainerInterface $container = new Container(),
): array {
    $repository = new LoggingFailedJobRepository($log);
    $events = new LoggingEventDispatcher($log);
    $envelope = createFailedJobHandlerEnvelope();
    $handler = new FailedJobHandler(
        $repository,
        $envelope,
        new JobRunner($container, $envelope, new FakeEventDispatcher()),
        $events,
        new FakeClock('2026-10-31 09:30:00'),
    );

    return ['handler' => $handler, 'repository' => $repository, 'events' => $events];
}

/**
 * Point PHP's error log at a temp file for the callback and return what was logged.
 */
function captureFailedJobHandlerErrorLog(
    Closure $callback,
): string {
    $logFile = tempnam(sys_get_temp_dir(), 'failed-job-handler-log');
    $previous = ini_set('error_log', $logFile);

    try {
        $callback();
    } finally {
        ini_set('error_log', $previous === false ? '' : $previous);
    }

    $logged = (string) file_get_contents($logFile);
    unlink($logFile);

    return $logged;
}

describe('FailedJobHandler', function (): void {
    it('implements FailedJobHandlerInterface', function (): void {
        expect(createLoggingFailedJobHandler()['handler'])->toBeInstanceOf(FailedJobHandlerInterface::class);
    });

    it('stores the job, calls failed(), then dispatches JobFailedEvent, in that order', function (): void {
        $log = new ArrayObject();
        ['handler' => $handler] = createLoggingFailedJobHandler($log);
        $job = new HandlerFailableJob($log);
        $job->setId('job-1');

        $handler->handle($job, 'emails', new RuntimeException('boom'));

        expect($log->getArrayCopy())->toBe([
            'store:job-1',
            'failed:boom',
            'event:' . JobFailedEvent::class,
        ]);
    });

    it('stores the failed job with its queue, signed payload, exception and the clock time', function (): void {
        ['handler' => $handler, 'repository' => $repository] = createLoggingFailedJobHandler();
        $job = new HandlerFailableJob();
        $job->setId('job-1');

        $handler->handle($job, 'emails', new RuntimeException('boom'), "\n\nextra note");

        $stored = $repository->find('job-1');

        expect($stored)->not->toBeNull()
            ->and($stored->queue)->toBe('emails')
            ->and($stored->exception)->toStartWith("boom\n#0 ")
            ->and($stored->exception)->toEndWith("\n\nextra note")
            ->and($stored->failedAt->format('Y-m-d H:i:s'))->toBe('2026-10-31 09:30:00')
            ->and(unserialize(createFailedJobHandlerEnvelope()->verifyAndUnwrap($stored->payload)))
            ->toBeInstanceOf(HandlerFailableJob::class);
    });

    it('dispatches JobFailedEvent with the job, its class and id, the queue and the exception', function (): void {
        ['handler' => $handler, 'events' => $events] = createLoggingFailedJobHandler();
        $job = new HandlerFailableJob();
        $job->setId('job-1');
        $exception = new RuntimeException('boom');

        $handler->handle($job, 'emails', $exception);

        expect($events->events)->toHaveCount(1);

        $event = $events->events[0];

        expect($event)->toBeInstanceOf(JobFailedEvent::class)
            ->and($event->job)->toBe($job)
            ->and($event->jobClass)->toBe(HandlerFailableJob::class)
            ->and($event->jobId)->toBe('job-1')
            ->and($event->queue)->toBe('emails')
            ->and($event->exception)->toBe($exception);
    });

    it('stores and dispatches the event for a job that does not implement FailableJobInterface', function (): void {
        ['handler' => $handler, 'repository' => $repository, 'events' => $events] = createLoggingFailedJobHandler();
        $job = new class () extends Job
        {
            public function handle(): void {}
        };
        $job->setId('plain');

        $handler->handle($job, 'default', new RuntimeException('boom'));

        expect($repository->find('plain'))->not->toBeNull()
            ->and($events->events)->toHaveCount(1);
    });

    it(
        'logs a throwing failed() with the job class and id, still dispatches the event, and returns',
        function (): void {
            ['handler' => $handler, 'events' => $events, 'repository' => $repository] = createLoggingFailedJobHandler();
            $job = new HandlerFailableJob(failedThrows: true);
            $job->setId('job-9');

            $logged = captureFailedJobHandlerErrorLog(
                fn () => $handler->handle($job, 'default', new RuntimeException('boom')),
            );

            expect($logged)->toContain('HandlerFailableJob')
                ->and($logged)->toContain('job-9')
                ->and($logged)->toContain('LogicException: cleanup broke')
                ->and($events->events)->toHaveCount(1)
                ->and($repository->find('job-9'))->not->toBeNull();
        },
    );

    it('gives a container-aware job its container while failed() runs, then releases it', function (): void {
        $service = new HandlerCleanupService();
        $container = new Container();
        $container->instance(HandlerCleanupService::class, $service);
        ['handler' => $handler] = createLoggingFailedJobHandler(container: $container);
        $job = new HandlerContainerAwareFailableJob();
        $job->setId('aware');

        $handler->handle($job, 'default', new RuntimeException('boom'));

        expect($job->resolvedInFailed)->toBe($service);
    });

    it(
        'stores a job whose payload cannot be serialized with a placeholder and still calls failed()',
        function (): void {
            ['handler' => $handler, 'repository' => $repository] = createLoggingFailedJobHandler();
            $job = new class () extends Job implements FailableJobInterface
            {
                public Closure $callback;

                public bool $failedCalled = false;

                public function __construct()
                {
                    $this->callback = fn (): null => null;
                }

                public function handle(): void {}

                public function failed(Throwable $exception): void
                {
                    $this->failedCalled = true;
                }
            };
            $job->setId('closure-job');

            $handler->handle($job, 'default', new RuntimeException('boom'));

            $stored = $repository->find('closure-job');
            $payload = unserialize(createFailedJobHandlerEnvelope()->verifyAndUnwrap($stored->payload));

            expect($payload)->toBeArray()
                ->and($payload['class'])->toBe($job::class)
                ->and($stored->exception)->toContain('Job payload could not be serialized')
                ->and($job->failedCalled)->toBeTrue();
        },
    );
});
