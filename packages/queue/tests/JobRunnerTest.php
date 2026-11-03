<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\Event\JobProcessedEvent;
use Marko\Queue\Event\JobProcessingEvent;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobRunner;
use Marko\Queue\JobRunnerInterface;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;

function createJobRunnerTestEnvelope(): JobEnvelope
{
    return new JobEnvelope(new EncryptionConfig(new FakeConfigRepository(['encryption.key' => 'job-runner-key'])));
}

class JobRunnerPlainJob extends Job
{
    public int $handled = 0;

    public ?int $attemptsWhenHandled = null;

    public ?int $releaseDelayWhenHandled = null;

    public function __construct(
        public readonly bool $fails = false,
        public readonly ?int $releaseWith = null,
    ) {}

    public function handle(): void
    {
        $this->handled++;
        $this->attemptsWhenHandled = $this->attempts;
        $this->releaseDelayWhenHandled = $this->releaseDelay;

        if ($this->releaseWith !== null) {
            $this->release($this->releaseWith);
        }

        if ($this->fails) {
            throw new RuntimeException('plain job failed');
        }
    }
}

class JobRunnerContainerAwareJob extends Job implements ContainerAwareJobInterface
{
    public ?ContainerInterface $container = null;

    public ?JobEnvelope $jobEnvelope = null;

    public bool $hadBothWhenHandled = false;

    public int $releaseCount = 0;

    public function __construct(
        public readonly bool $fails = false,
    ) {}

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function setJobEnvelope(JobEnvelope $jobEnvelope): void
    {
        $this->jobEnvelope = $jobEnvelope;
    }

    public function releaseContainer(): void
    {
        $this->container = null;
        $this->jobEnvelope = null;
        $this->releaseCount++;
    }

    public function handle(): void
    {
        $this->hadBothWhenHandled = $this->container !== null && $this->jobEnvelope !== null;

        if ($this->fails) {
            throw new RuntimeException('container-aware job failed');
        }
    }
}

describe('JobRunner', function (): void {
    it('implements JobRunnerInterface', function (): void {
        expect(new JobRunner(new Container(), createJobRunnerTestEnvelope(), new FakeEventDispatcher()))
            ->toBeInstanceOf(JobRunnerInterface::class);
    });

    it('increments attempts before handling the job', function (): void {
        $job = new JobRunnerPlainJob();

        new JobRunner(new Container(), createJobRunnerTestEnvelope(), new FakeEventDispatcher())->run($job, 'default');

        expect($job->handled)->toBe(1)
            ->and($job->attemptsWhenHandled)->toBe(1)
            ->and($job->attempts)->toBe(1)
            ->and($job->exceptions)->toBe(0);
    });

    it('counts an exception and rethrows it when handle() throws', function (): void {
        $job = new JobRunnerPlainJob(fails: true);
        $runner = new JobRunner(new Container(), createJobRunnerTestEnvelope(), new FakeEventDispatcher());

        expect(fn () => $runner->run($job, 'default'))->toThrow(RuntimeException::class, 'plain job failed')
            ->and($job->exceptions)->toBe(1)
            ->and($job->attempts)->toBe(1);
    });

    it('does not count a release requested from inside handle() as an exception', function (): void {
        $job = new JobRunnerPlainJob(releaseWith: 30);

        new JobRunner(new Container(), createJobRunnerTestEnvelope(), new FakeEventDispatcher())->run($job, 'default');

        expect($job->releaseDelay)->toBe(30)
            ->and($job->exceptions)->toBe(0);
    });

    it('clears a release request left over from an earlier attempt before handling the job', function (): void {
        $job = new JobRunnerPlainJob();
        $job->release(30);

        new JobRunner(new Container(), createJobRunnerTestEnvelope(), new FakeEventDispatcher())->run($job, 'default');

        expect($job->releaseDelayWhenHandled)->toBeNull()
            ->and($job->releaseDelay)->toBeNull();
    });

    it(
        'gives a container-aware job the container and envelope, then releases them when handle() returns',
        function (): void {
            $container = new Container();
            $job = new JobRunnerContainerAwareJob();

            new JobRunner($container, createJobRunnerTestEnvelope(), new FakeEventDispatcher())->run($job, 'default');

            expect($job->hadBothWhenHandled)->toBeTrue()
                ->and($job->releaseCount)->toBe(1)
                ->and($job->container)->toBeNull()
                ->and($job->jobEnvelope)->toBeNull();
        },
    );

    it('releases the container when a container-aware job throws, and counts the exception', function (): void {
        $job = new JobRunnerContainerAwareJob(fails: true);
        $runner = new JobRunner(new Container(), createJobRunnerTestEnvelope(), new FakeEventDispatcher());

        expect(fn () => $runner->run($job, 'default'))->toThrow(RuntimeException::class, 'container-aware job failed')
            ->and($job->releaseCount)->toBe(1)
            ->and($job->container)->toBeNull()
            ->and($job->exceptions)->toBe(1);
    });

    it('runs a callback with the container set up and returns its result', function (): void {
        $container = new Container();
        $job = new JobRunnerContainerAwareJob();
        $runner = new JobRunner($container, createJobRunnerTestEnvelope(), new FakeEventDispatcher());

        $result = $runner->withContainer($job, fn (): ?ContainerInterface => $job->container);

        expect($result)->toBe($container)
            ->and($job->releaseCount)->toBe(1)
            ->and($job->container)->toBeNull();
    });

    it('releases the container when the callback throws', function (): void {
        $job = new JobRunnerContainerAwareJob();
        $runner = new JobRunner(new Container(), createJobRunnerTestEnvelope(), new FakeEventDispatcher());

        expect(fn () => $runner->withContainer($job, fn () => throw new LogicException('callback failed')))
            ->toThrow(LogicException::class, 'callback failed')
            ->and($job->releaseCount)->toBe(1);
    });

    it('runs the callback as-is for a job that is not container-aware', function (): void {
        $job = new JobRunnerPlainJob();
        $runner = new JobRunner(new Container(), createJobRunnerTestEnvelope(), new FakeEventDispatcher());

        expect($runner->withContainer($job, fn (): string => 'ran'))->toBe('ran');
    });

    it('dispatches JobProcessingEvent before handle() and JobProcessedEvent after it returns', function (): void {
        $events = new class () implements EventDispatcherInterface
        {
            /** @var list<string> */
            public array $log = [];

            public function dispatch(Event $event): void
            {
                $this->log[] = $event::class . ':' . $event->jobId . ':attempt ' . $event->job->attempts;
            }
        };
        $job = new JobRunnerPlainJob();
        $job->setId('job-7');

        new JobRunner(new Container(), createJobRunnerTestEnvelope(), $events)->run($job, 'default');

        expect($events->log)->toBe([
            JobProcessingEvent::class . ':job-7:attempt 1',
            JobProcessedEvent::class . ':job-7:attempt 1',
        ])->and($job->handled)->toBe(1);
    });

    it('dispatches JobProcessingEvent before handle() runs', function (): void {
        $events = new FakeEventDispatcher();
        $job = new class ($events) extends Job
        {
            public int $processingEventsSeen = -1;

            public function __construct(
                private readonly FakeEventDispatcher $events,
            ) {}

            public function handle(): void
            {
                $this->processingEventsSeen = count($this->events->dispatched(JobProcessingEvent::class));
            }
        };

        new JobRunner(new Container(), createJobRunnerTestEnvelope(), $events)->run($job, 'default');

        expect($job->processingEventsSeen)->toBe(1)
            ->and($events->dispatched(JobProcessingEvent::class)[0]->job)->toBe($job)
            ->and($events->dispatched(JobProcessingEvent::class)[0]->jobClass)->toBe($job::class);
    });

    it('passes the queue it is given to JobProcessingEvent and JobProcessedEvent', function (): void {
        $events = new FakeEventDispatcher();

        new JobRunner(new Container(), createJobRunnerTestEnvelope(), $events)->run(new JobRunnerPlainJob(), 'engine');

        expect($events->dispatched(JobProcessingEvent::class)[0]->queue)->toBe('engine')
            ->and($events->dispatched(JobProcessedEvent::class)[0]->queue)->toBe('engine');
    });

    it('does not dispatch JobProcessedEvent when handle() throws', function (): void {
        $events = new FakeEventDispatcher();
        $runner = new JobRunner(new Container(), createJobRunnerTestEnvelope(), $events);

        expect(fn () => $runner->run(new JobRunnerPlainJob(fails: true), 'default'))->toThrow(RuntimeException::class);

        expect($events->dispatched(JobProcessingEvent::class))->toHaveCount(1)
            ->and($events->dispatched(JobProcessedEvent::class))->toBe([]);
    });

    it('dispatches both events while a container-aware job still holds its container', function (): void {
        $job = new JobRunnerContainerAwareJob();
        $events = new class ($job) implements EventDispatcherInterface
        {
            /** @var list<bool> */
            public array $hadContainer = [];

            public function __construct(
                private readonly JobRunnerContainerAwareJob $job,
            ) {}

            public function dispatch(Event $event): void
            {
                $this->hadContainer[] = $this->job->container !== null;
            }
        };

        new JobRunner(new Container(), createJobRunnerTestEnvelope(), $events)->run($job, 'default');

        expect($events->hadContainer)->toBe([true, true])
            ->and($job->container)->toBeNull();
    });
});
