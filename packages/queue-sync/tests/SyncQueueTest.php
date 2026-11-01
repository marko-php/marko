<?php

declare(strict_types=1);

namespace Marko\Queue\Sync\Tests;

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\Event\JobFailedEvent;
use Marko\Queue\Event\JobProcessedEvent;
use Marko\Queue\Event\JobProcessingEvent;
use Marko\Queue\Exceptions\JobFailedException;
use Marko\Queue\FailableJobInterface;
use Marko\Queue\FailedJobHandler;
use Marko\Queue\FailedJobHandlerInterface;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobInterface;
use Marko\Queue\JobRunner;
use Marko\Queue\JobRunnerInterface;
use Marko\Queue\PushResult;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\Sync\NullFailedJobRepository;
use Marko\Queue\Sync\SyncQueue;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeLogger;
use RuntimeException;
use Throwable;

function createSyncQueueJobEnvelope(): JobEnvelope
{
    return new JobEnvelope(
        new EncryptionConfig(new FakeConfigRepository(['encryption.key' => 'sync-queue-test-key'])),
    );
}

function createSyncQueue(
    ?ContainerInterface $container = null,
    ?JobEnvelope $jobEnvelope = null,
    FakeEventDispatcher $events = new FakeEventDispatcher(),
    ?FailedJobHandlerInterface $failedJobHandler = null,
): SyncQueue {
    $jobEnvelope ??= createSyncQueueJobEnvelope();
    $runner = new JobRunner($container ?? new Container(), $jobEnvelope, $events);

    return new SyncQueue(
        $runner,
        $failedJobHandler ?? new FailedJobHandler(
            new NullFailedJobRepository(),
            $jobEnvelope,
            $runner,
            $events,
            new FakeClock(),
            logger: new FakeLogger(),
        ),
        new QueueConfig(new FakeConfigRepository(['queue.queue' => 'default'])),
    );
}

class SyncFailableJob extends Job implements FailableJobInterface
{
    /** @var list<Throwable> */
    public array $failedWith = [];

    public function handle(): void
    {
        throw new RuntimeException('sync job failed');
    }

    public function failed(Throwable $exception): void
    {
        $this->failedWith[] = $exception;
    }
}

/**
 * Failed-job handler double that records every call.
 */
class RecordingSyncFailedJobHandler implements FailedJobHandlerInterface
{
    /** @var list<array{job: JobInterface, queue: string, exception: Throwable}> */
    public array $handled = [];

    public function handle(
        JobInterface $job,
        string $queue,
        Throwable $exception,
        string $note = '',
    ): void {
        $this->handled[] = ['job' => $job, 'queue' => $queue, 'exception' => $exception];
    }
}

class ContainerAwareCaptureJob extends Job implements ContainerAwareJobInterface
{
    public ?ContainerInterface $receivedContainer = null;

    public ?JobEnvelope $receivedJobEnvelope = null;

    public bool $hadBothWhenHandled = false;

    public function setContainer(ContainerInterface $container): void
    {
        $this->receivedContainer = $container;
    }

    public function setJobEnvelope(JobEnvelope $jobEnvelope): void
    {
        $this->receivedJobEnvelope = $jobEnvelope;
    }

    public int $releaseCount = 0;

    public bool $throwOnHandle = false;

    public function releaseContainer(): void
    {
        $this->releaseCount++;
    }

    public function handle(): void
    {
        $this->hadBothWhenHandled = $this->receivedContainer !== null && $this->receivedJobEnvelope !== null
            && $this->releaseCount === 0;

        if ($this->throwOnHandle) {
            throw new RuntimeException('Capture job failed');
        }
    }
}

it('gives container-aware jobs the container and job envelope before handling them', function (): void {
    $container = new Container();
    $jobEnvelope = createSyncQueueJobEnvelope();
    $job = new ContainerAwareCaptureJob();

    createSyncQueue($container, $jobEnvelope)->push($job);

    expect($job->receivedContainer)->toBe($container)
        ->and($job->receivedJobEnvelope)->toBe($jobEnvelope)
        ->and($job->hadBothWhenHandled)->toBeTrue();
});

it('releases the container from a container-aware job after SyncQueue runs it', function (): void {
    $job = new ContainerAwareCaptureJob();

    createSyncQueue()->push($job);

    expect($job->releaseCount)->toBe(1);
});

it('releases the container from a container-aware job when it fails under SyncQueue', function (): void {
    $job = new ContainerAwareCaptureJob();
    $job->throwOnHandle = true;

    expect(fn () => createSyncQueue()->push($job))->toThrow(JobFailedException::class)
        ->and($job->releaseCount)->toBe(1);
});

it('implements QueueInterface', function (): void {
    $queue = createSyncQueue();

    expect($queue)->toBeInstanceOf(QueueInterface::class);
});

it('push executes job immediately', function (): void {
    $queue = createSyncQueue();
    $executed = false;

    $job = new class ($executed) extends Job
    {
        public function __construct(
            private bool &$executed,
        ) {}

        public function handle(): void
        {
            $this->executed = true;
        }
    };

    $queue->push($job);

    expect($executed)->toBeTrue();
});

it('push returns a pushed PushResult with the job ID', function (): void {
    $queue = createSyncQueue();

    $job = new class () extends Job
    {
        public function handle(): void {}
    };

    $result = $queue->push($job);

    expect($result)->toBeInstanceOf(PushResult::class)
        ->and($result->pushed)->toBeTrue()
        ->and($result->id)->toBeString()->not->toBeEmpty()
        ->and($job->id)->toBe($result->id);
});

it('runs the job through the JobRunner it is given', function (): void {
    $runner = new class () implements JobRunnerInterface
    {
        /** @var list<JobInterface> */
        public array $ran = [];

        public function run(JobInterface $job): void
        {
            $this->ran[] = $job;
        }

        public function withContainer(
            JobInterface $job,
            callable $callback,
        ): mixed {
            return $callback();
        }
    };
    $job = new class () extends Job
    {
        public function handle(): void
        {
            throw new RuntimeException('handle() must not be called directly by SyncQueue');
        }
    };

    new SyncQueue(
        $runner,
        new RecordingSyncFailedJobHandler(),
        new QueueConfig(new FakeConfigRepository(['queue.queue' => 'default'])),
    )->push($job);

    expect($runner->ran)->toBe([$job]);
});

it('counts the exception on a job that fails under SyncQueue', function (): void {
    $job = new class () extends Job
    {
        public function handle(): void
        {
            throw new RuntimeException('fails');
        }
    };

    expect(fn () => createSyncQueue()->push($job))->toThrow(JobFailedException::class)
        ->and($job->exceptions)->toBe(1)
        ->and($job->attempts)->toBe(1);
});

it('calls failed() on a job that fails under SyncQueue and still throws JobFailedException', function (): void {
    $job = new SyncFailableJob();

    expect(fn () => createSyncQueue()->push($job))->toThrow(JobFailedException::class, 'sync job failed')
        ->and($job->failedWith)->toHaveCount(1)
        ->and($job->failedWith[0])->toBeInstanceOf(RuntimeException::class)
        ->and($job->failedWith[0]->getMessage())->toBe('sync job failed');
});

it('dispatches JobFailedEvent with the exception when a job fails under SyncQueue', function (): void {
    $events = new FakeEventDispatcher();
    $job = new SyncFailableJob();

    expect(fn () => createSyncQueue(events: $events)->push($job, 'emails'))->toThrow(JobFailedException::class);

    $failed = $events->dispatched(JobFailedEvent::class);

    expect($failed)->toHaveCount(1)
        ->and($failed[0]->job)->toBe($job)
        ->and($failed[0]->queue)->toBe('emails')
        ->and($failed[0]->exception->getMessage())->toBe('sync job failed');
});

it('hands a failing job to the failed-job handler on the default queue when none is given', function (): void {
    $handler = new RecordingSyncFailedJobHandler();
    $job = new SyncFailableJob();

    expect(fn () => createSyncQueue(failedJobHandler: $handler)->push($job))->toThrow(JobFailedException::class);

    expect($handler->handled)->toHaveCount(1)
        ->and($handler->handled[0]['job'])->toBe($job)
        ->and($handler->handled[0]['queue'])->toBe('default')
        ->and($handler->handled[0]['exception']->getMessage())->toBe('sync job failed');
});

it('does not hand a job that succeeds to the failed-job handler', function (): void {
    $handler = new RecordingSyncFailedJobHandler();
    $job = new class () extends Job
    {
        public function handle(): void {}
    };

    createSyncQueue(failedJobHandler: $handler)->push($job);

    expect($handler->handled)->toBe([]);
});

it('dispatches JobProcessingEvent and JobProcessedEvent around a job run under SyncQueue', function (): void {
    $events = new FakeEventDispatcher();
    $job = new class () extends Job
    {
        public function handle(): void {}
    };

    createSyncQueue(events: $events)->push($job);

    expect($events->dispatched(JobProcessingEvent::class))->toHaveCount(1)
        ->and($events->dispatched(JobProcessedEvent::class))->toHaveCount(1)
        ->and($events->dispatched(JobProcessedEvent::class)[0]->job)->toBe($job);
});

it('later executes job immediately', function (): void {
    $queue = createSyncQueue();
    $executed = false;

    $job = new class ($executed) extends Job
    {
        public function __construct(
            private bool &$executed,
        ) {}

        public function handle(): void
        {
            $this->executed = true;
        }
    };

    $result = $queue->later(60, $job);

    expect($executed)->toBeTrue()
        ->and($result->pushed)->toBeTrue()
        ->and($result->id)->toBeString()->not->toBeEmpty()
        ->and($job->id)->toBe($result->id);
});

it('pop returns null', function (): void {
    $queue = createSyncQueue();

    expect($queue->pop())->toBeNull()
        ->and($queue->pop('custom'))->toBeNull();
});

it('size returns zero', function (): void {
    $queue = createSyncQueue();

    expect($queue->size())->toBe(0)
        ->and($queue->size('custom'))->toBe(0);
});

it('clear returns zero', function (): void {
    $queue = createSyncQueue();

    expect($queue->clear())->toBe(0)
        ->and($queue->clear('custom'))->toBe(0);
});

it('push throws JobFailedException on job failure', function (): void {
    $queue = createSyncQueue();

    $job = new class () extends Job
    {
        public function handle(): void
        {
            throw new RuntimeException('Job failed');
        }
    };

    $queue->push($job);
})->throws(JobFailedException::class, 'Job failed');

it('SyncQueue handles job exceptions properly', function (): void {
    $queue = createSyncQueue();

    $job = new class () extends Job
    {
        public function handle(): void
        {
            throw new RuntimeException('Test exception message');
        }
    };

    expect(fn () => $queue->push($job))
        ->toThrow(JobFailedException::class)
        ->and(fn () => $queue->later(60, $job))
        ->toThrow(JobFailedException::class);
});
