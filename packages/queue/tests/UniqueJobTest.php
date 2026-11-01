<?php

declare(strict_types=1);

use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Exceptions\NoDriverException;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Observer;
use Marko\Core\Attributes\Plugin;
use Marko\Queue\Event\JobFailedEvent;
use Marko\Queue\Event\JobProcessedEvent;
use Marko\Queue\Event\JobProcessingEvent;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\Job;
use Marko\Queue\JobInterface;
use Marko\Queue\Observer\ReleaseUniqueJobLockOnFailure;
use Marko\Queue\Observer\ReleaseUniqueJobLockOnProcessed;
use Marko\Queue\Observer\ReleaseUniqueJobLockOnProcessing;
use Marko\Queue\Plugin\UniqueJobPushPlugin;
use Marko\Queue\PushResult;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\Tests\Command\Helpers;
use Marko\Queue\Tests\Fixtures\FakeProcessControl;
use Marko\Queue\Tests\Fixtures\InMemoryQueue;
use Marko\Queue\Tests\Fixtures\UniqueJobStack;
use Marko\Queue\Tests\Fixtures\UniqueTestJob;
use Marko\Queue\UniqueJobInterface;
use Marko\Queue\UniqueUntil;
use Marko\Queue\Worker;
use Marko\Queue\WorkerOptions;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * A job that is not unique.
 */
class PlainUniqueTestJob extends Job
{
    public function handle(): void {}
}

/**
 * Process control that records whether the unique job's lock was already free when the worker exited.
 */
class LockCheckingProcessControl extends FakeProcessControl
{
    public ?bool $lockFreeAtExit = null;

    public ?Closure $checkLock = null;

    public function terminate(
        int $status,
    ): void {
        $this->lockFreeAtExit = ($this->checkLock)();
        parent::terminate($status);
    }
}

/**
 * Worker that stops instead of sleeping once the queue is empty.
 */
class UniqueTestWorker extends Worker
{
    protected function pause(
        int $seconds,
    ): void {
        $this->stop();
    }
}

function uniqueWorker(
    UniqueJobStack $stack,
    QueueInterface $queue,
    FakeProcessControl $processControl = new FakeProcessControl(),
): UniqueTestWorker {
    return new UniqueTestWorker(
        $queue,
        $stack->failedJobHandler(Helpers::createStubFailedJobRepository()),
        new QueueConfig(new FakeConfigRepository([
            'queue.queue' => 'default',
            'queue.max_attempts' => 3,
        ])),
        $stack->jobRunner(),
        processControl: $processControl,
    );
}

describe('UniqueJobInterface', function (): void {
    it('extends JobInterface and declares the unique-job contract', function (): void {
        $reflection = new ReflectionClass(UniqueJobInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->implementsInterface(JobInterface::class))->toBeTrue()
            ->and($reflection->hasMethod('uniqueId'))->toBeTrue()
            ->and($reflection->hasMethod('uniqueFor'))->toBeTrue()
            ->and($reflection->hasMethod('uniqueUntil'))->toBeTrue()
            ->and($reflection->hasMethod('setUniqueLockOwner'))->toBeTrue()
            ->and($reflection->hasProperty('uniqueLockOwner'))->toBeTrue();
    });

    it('has UniqueUntil cases Processing and Processed', function (): void {
        expect(UniqueUntil::cases())->toBe([UniqueUntil::Processing, UniqueUntil::Processed]);
    });

    it('gives Job the lock owner property and setter, carried in the payload', function (): void {
        $job = new UniqueTestJob();

        expect($job->uniqueLockOwner)->toBeNull();

        $job->setUniqueLockOwner('owner-token');
        $restored = UniqueTestJob::unserialize($job->serialize());

        expect($restored->uniqueLockOwner)->toBe('owner-token');
    });

    it('registers the push plugin on QueueInterface push() and later()', function (): void {
        $reflection = new ReflectionClass(UniqueJobPushPlugin::class);
        $plugin = $reflection->getAttributes(Plugin::class)[0]->newInstance();
        $push = $reflection->getMethod('beforePush')->getAttributes(Before::class)[0]->newInstance();
        $later = $reflection->getMethod('beforeLater')->getAttributes(Before::class)[0]->newInstance();

        expect($plugin->target)->toBe(QueueInterface::class)
            ->and($push->method)->toBe('push')
            ->and($later->method)->toBe('later');
    });

    it(
        'registers the release observers on the job lifecycle events',
        function (string $observer, string $event): void {
            $attribute = new ReflectionClass($observer)->getAttributes(Observer::class)[0]->newInstance();

            expect($attribute->event)->toBe($event)
                ->and($attribute->async)->toBeFalse();
        },
    )->with([
        [ReleaseUniqueJobLockOnProcessing::class, JobProcessingEvent::class],
        [ReleaseUniqueJobLockOnProcessed::class, JobProcessedEvent::class],
        [ReleaseUniqueJobLockOnFailure::class, JobFailedEvent::class],
    ]);
});

describe('unique job push gate', function (): void {
    it('pushes the first copy and stores the lock owner on the job', function (): void {
        $stack = new UniqueJobStack();
        $queue = $stack->wrap(new InMemoryQueue());
        $job = new UniqueTestJob();

        $result = $queue->push($job);

        expect($result->pushed)->toBeTrue()
            ->and($job->uniqueLockOwner)->toBeString()->not->toBe('')
            ->and($queue->size())->toBe(1);
    });

    it('skips a second push while the first is pending and queues nothing', function (): void {
        $stack = new UniqueJobStack();
        $queue = $stack->wrap(new InMemoryQueue());
        $queue->push(new UniqueTestJob());

        $result = $queue->push(new UniqueTestJob());

        expect($result)->toBeInstanceOf(PushResult::class)
            ->and($result->pushed)->toBeFalse()
            ->and($result->id)->toBeNull()
            ->and($result->skipReason)->toContain('unique-job:' . UniqueTestJob::class . ':42')
            ->and($queue->size())->toBe(1);
    });

    it('skips a duplicate later() the same way', function (): void {
        $stack = new UniqueJobStack();
        $queue = $stack->wrap(new InMemoryQueue());
        $queue->later(30, new UniqueTestJob());

        $result = $queue->later(30, new UniqueTestJob());

        expect($result->pushed)->toBeFalse()
            ->and($queue->size())->toBe(1);
    });

    it('pushes jobs with different unique ids', function (): void {
        $stack = new UniqueJobStack();
        $queue = $stack->wrap(new InMemoryQueue());

        expect($queue->push(new UniqueTestJob('1'))->pushed)->toBeTrue()
            ->and($queue->push(new UniqueTestJob('2'))->pushed)->toBeTrue()
            ->and($queue->size())->toBe(2);
    });

    it('lets the job be pushed again once the lock expires after uniqueFor()', function (): void {
        $stack = new UniqueJobStack();
        $queue = $stack->wrap(new InMemoryQueue());
        $queue->push(new UniqueTestJob(for: 60));

        $stack->clock->travel('+59 seconds');
        $beforeExpiry = $queue->push(new UniqueTestJob(for: 60));
        $stack->clock->travel('+2 seconds');
        $afterExpiry = $queue->push(new UniqueTestJob(for: 60));

        expect($beforeExpiry->pushed)->toBeFalse()
            ->and($afterExpiry->pushed)->toBeTrue();
    });

    it('rejects a uniqueFor() that is not positive', function (int $seconds): void {
        $stack = new UniqueJobStack();
        $queue = $stack->wrap(new InMemoryQueue());

        expect(fn () => $queue->push(new UniqueTestJob(for: $seconds)))
            ->toThrow(QueueException::class, 'Invalid uniqueFor() for unique job ' . UniqueTestJob::class);
    })->with([0, -1]);

    it('throws a clear exception naming LockFactoryInterface when no lock driver is bound', function (): void {
        $stack = new UniqueJobStack(withLockDriver: false);
        $queue = $stack->wrap(new InMemoryQueue());

        try {
            $queue->push(new UniqueTestJob());
            $this->fail('Pushing a unique job without a lock driver must throw.');
        } catch (QueueException $e) {
            expect($e->getMessage())->toContain('no lock driver is bound')
                ->and($e->getContext())->toContain(LockFactoryInterface::class)
                ->and($e->getSuggestion())->toContain('marko/cache-redis')
                ->and($e->getPrevious())->toBeInstanceOf(NoDriverException::class);
        }
    });

    it('pushes and runs jobs that are not unique without a lock driver', function (): void {
        $stack = new UniqueJobStack(withLockDriver: false);
        $queue = $stack->wrap(new InMemoryQueue());

        $result = $queue->push(new PlainUniqueTestJob());
        uniqueWorker($stack, $queue)->work(once: true);

        expect($result->pushed)->toBeTrue()
            ->and($queue->size())->toBe(0);
    });
});

describe('unique job lock release', function (): void {
    it('allows pushing again once the worker starts a UniqueUntil::Processing job', function (): void {
        $stack = new UniqueJobStack();
        $queue = $stack->wrap(new InMemoryQueue());
        $duplicate = null;
        $queue->push(new UniqueTestJob(
            until: UniqueUntil::Processing,
            during: function () use ($queue, &$duplicate): void {
                $duplicate = $queue->push(new UniqueTestJob(until: UniqueUntil::Processing));
            },
        ));

        uniqueWorker($stack, $queue)->work(once: true);

        expect($duplicate?->pushed)->toBeTrue();
    });

    it('blocks pushing a UniqueUntil::Processed job until it completes', function (): void {
        $stack = new UniqueJobStack();
        $queue = $stack->wrap(new InMemoryQueue());
        $duringRun = null;
        $job = new UniqueTestJob(during: function () use ($queue, &$duringRun): void {
            $duringRun = $queue->push(new UniqueTestJob());
        });
        $queue->push($job);

        uniqueWorker($stack, $queue)->work(once: true);

        expect($duringRun?->pushed)->toBeFalse()
            ->and($stack->lockIsFree($job))->toBeTrue()
            ->and($queue->push(new UniqueTestJob())->pushed)->toBeTrue();
    });

    it('keeps the lock across a retry and releases it after the final failure', function (): void {
        $stack = new UniqueJobStack();
        $queue = $stack->wrap(new InMemoryQueue());
        $job = new UniqueTestJob(fail: true, maxAttempts: 2);
        $queue->push($job);

        uniqueWorker($stack, $queue)->work(once: true);
        $afterRetry = $stack->lockIsFree($job);

        uniqueWorker($stack, $queue)->work(once: true);

        expect($afterRetry)->toBeFalse()
            ->and($job->attempts)->toBe(2)
            ->and($stack->lockIsFree($job))->toBeTrue();
    });

    it('releases a UniqueUntil::Processing job lock only once, at the start', function (): void {
        $stack = new UniqueJobStack();
        $queue = $stack->wrap(new InMemoryQueue());
        $job = new UniqueTestJob(until: UniqueUntil::Processing, fail: true, maxAttempts: 1);
        $queue->push($job);

        uniqueWorker($stack, $queue)->work(once: true);
        $next = new UniqueTestJob(until: UniqueUntil::Processing);
        $queue->push($next);

        // The failed job's late release must not free the lock the next push now holds
        expect($stack->lockIsFree($next))->toBeFalse();
    });

    it('releases the lock before the worker exits on a timeout', function (): void {
        $stack = new UniqueJobStack();
        $queue = $stack->wrap(new InMemoryQueue());
        $processControl = new LockCheckingProcessControl();
        $job = new UniqueTestJob(during: fn () => $processControl->fireTimer(), maxAttempts: 1);
        $processControl->checkLock = fn (): bool => $stack->lockIsFree($job);
        $queue->push($job);

        $previousErrorLog = ini_set('error_log', tempnam(sys_get_temp_dir(), 'unique-job-timeout-'));

        try {
            uniqueWorker($stack, $queue, $processControl)->work(options: new WorkerOptions(timeout: 5));
        } finally {
            unlink((string) ini_get('error_log'));
            ini_set('error_log', (string) $previousErrorLog);
        }

        expect($processControl->terminatedWith)->toBe(Worker::EXIT_TIMED_OUT)
            ->and($processControl->lockFreeAtExit)->toBeTrue();
    });

    it('keeps a UniqueUntil::Processed lock when the job asked to be released from handle()', function (): void {
        $stack = new UniqueJobStack();
        $queue = $stack->wrap(new InMemoryQueue());
        $job = new UniqueTestJob();
        $queue->push($job);
        $job->release(10);

        $stack->eventDispatcher->dispatch(new JobProcessedEvent($job));

        expect($stack->lockIsFree($job))->toBeFalse();
    });

    it('leaves a job pushed without the lock alone', function (): void {
        $stack = new UniqueJobStack();
        $job = new UniqueTestJob();

        $stack->eventDispatcher->dispatch(new JobFailedEvent($job, 'default', new RuntimeException('boom')));

        expect($job->uniqueLockOwner)->toBeNull()
            ->and($stack->lockIsFree($job))->toBeTrue();
    });
});
