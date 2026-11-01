<?php

declare(strict_types=1);

use Marko\Cache\Lock\LockFactory;
use Marko\Cache\Lock\UsleepSleeper;
use Marko\Cache\Memory\Lock\ArrayLockStore;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\Job;
use Marko\Queue\PushResult;
use Marko\Queue\QueueInterface;
use Marko\Queue\UniqueJobInterface;
use Marko\Queue\UniqueUntil;
use Marko\Testing\Exceptions\AssertionFailedException;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeQueue;

it('implements QueueInterface', function () {
    $queue = new FakeQueue();

    expect($queue)->toBeInstanceOf(QueueInterface::class);
});

it('captures pushed jobs with queue name', function () {
    $queue = new FakeQueue();
    $job = new class () extends Job
    {
        public function handle(): void {}
    };

    $result = $queue->push($job, 'default');

    expect($result)->toBeInstanceOf(PushResult::class)
        ->and($result->pushed)->toBeTrue()
        ->and($queue->pushed)->toHaveCount(1)
        ->and($queue->pushed[0]['job'])->toBe($job)
        ->and($queue->pushed[0]['queue'])->toBe('default')
        ->and($queue->pushed[0]['delay'])->toBe(0)
        ->and($queue->pushed[0]['id'])->toBe($result->id);
});

it('captures delayed jobs with delay and queue name', function () {
    $queue = new FakeQueue();
    $job = new class () extends Job
    {
        public function handle(): void {}
    };

    $result = $queue->later(60, $job, 'deferred');

    expect($result->pushed)->toBeTrue()
        ->and($queue->pushed)->toHaveCount(1)
        ->and($queue->pushed[0]['job'])->toBe($job)
        ->and($queue->pushed[0]['queue'])->toBe('deferred')
        ->and($queue->pushed[0]['delay'])->toBe(60)
        ->and($queue->pushed[0]['id'])->toBe($result->id);
});

it('stores the released job with its current state and the new delay', function () {
    $queue = new FakeQueue();
    $job = new class () extends Job
    {
        public function handle(): void {}
    };
    $queue->push($job, 'default');
    $job->incrementAttempts();
    $job->incrementExceptions();

    $released = $queue->release($job, 30);

    expect($released)->toBeTrue()
        ->and($queue->pushed[0]['job'])->toBe($job)
        ->and($queue->pushed[0]['job']->attempts)->toBe(1)
        ->and($queue->pushed[0]['job']->exceptions)->toBe(1)
        ->and($queue->pushed[0]['delay'])->toBe(30);
});

it('returns false when releasing a job it does not hold', function () {
    $job = new class () extends Job
    {
        public function handle(): void {}
    };
    $job->setId('unknown');

    expect(new FakeQueue()->release($job))->toBeFalse();
});

it('pops jobs in FIFO order', function () {
    $queue = new FakeQueue();
    $first = new class () extends Job
    {
        public function handle(): void {}
    };
    $second = new class () extends Job
    {
        public function handle(): void {}
    };

    $queue->push($first, 'default');
    $queue->push($second, 'default');

    expect($queue->pop('default'))->toBe($first)
        ->and($queue->pop('default'))->toBe($second)
        ->and($queue->pushed)->toHaveCount(0);
});

it('returns null when popping from empty queue', function () {
    $queue = new FakeQueue();

    expect($queue->pop('default'))->toBeNull();
});

it('returns queue size', function () {
    $queue = new FakeQueue();
    $job1 = new class () extends Job
    {
        public function handle(): void {}
    };
    $job2 = new class () extends Job
    {
        public function handle(): void {}
    };
    $job3 = new class () extends Job
    {
        public function handle(): void {}
    };

    $queue->push($job1, 'default');
    $queue->push($job2, 'default');
    $queue->push($job3, 'other');

    expect($queue->size('default'))->toBe(2)
        ->and($queue->size('other'))->toBe(1)
        ->and($queue->size('empty'))->toBe(0);
});

it('clears all jobs from a queue', function () {
    $queue = new FakeQueue();
    $job1 = new class () extends Job
    {
        public function handle(): void {}
    };
    $job2 = new class () extends Job
    {
        public function handle(): void {}
    };
    $job3 = new class () extends Job
    {
        public function handle(): void {}
    };

    $queue->push($job1, 'default');
    $queue->push($job2, 'default');
    $queue->push($job3, 'other');

    $cleared = $queue->clear('default');

    expect($cleared)->toBe(2)
        ->and($queue->size('default'))->toBe(0)
        ->and($queue->size('other'))->toBe(1);
});

it('deletes a specific job by ID', function () {
    $queue = new FakeQueue();
    $job1 = new class () extends Job
    {
        public function handle(): void {}
    };
    $job2 = new class () extends Job
    {
        public function handle(): void {}
    };

    $id1 = $queue->push($job1, 'default')->id;
    $queue->push($job2, 'default');

    $deleted = $queue->delete($id1);

    expect($deleted)->toBeTrue()
        ->and($queue->pushed)->toHaveCount(1)
        ->and($queue->pushed[0]['job'])->toBe($job2)
        ->and($queue->delete('nonexistent'))->toBeFalse();

});

it('asserts job was pushed by class name', function () {
    $queue = new FakeQueue();
    $job = new class () extends Job
    {
        public function handle(): void {}
    };

    $queue->push($job, 'default');

    expect(fn () => $queue->assertPushed($job::class))->not->toThrow(AssertionFailedException::class);
});

it('throws AssertionFailedException when asserting pushed job that was not pushed', function () {
    $queue = new FakeQueue();

    expect(fn () => $queue->assertPushed(Job::class))->toThrow(AssertionFailedException::class);
});

it('asserts job was not pushed', function () {
    $queue = new FakeQueue();
    $job = new class () extends Job
    {
        public function handle(): void {}
    };
    $otherJob = new class () extends Job
    {
        public function handle(): void {}
    };

    $queue->push($job, 'default');

    expect(fn () => $queue->assertNotPushed($job::class))->toThrow(AssertionFailedException::class)
        ->and(fn () => $queue->assertNotPushed($otherJob::class))->not->toThrow(AssertionFailedException::class);
});

it('asserts pushed count', function () {
    $queue = new FakeQueue();
    $job1 = new class () extends Job
    {
        public function handle(): void {}
    };
    $job2 = new class () extends Job
    {
        public function handle(): void {}
    };

    $queue->push($job1, 'default');
    $queue->push($job2, 'default');

    expect(fn () => $queue->assertPushedCount(2))->not->toThrow(AssertionFailedException::class)
        ->and(fn () => $queue->assertPushedCount(1))->toThrow(AssertionFailedException::class);
});

it('asserts nothing was pushed', function () {
    $queue = new FakeQueue();

    expect(fn () => $queue->assertNothingPushed())->not->toThrow(AssertionFailedException::class);

    $job = new class () extends Job
    {
        public function handle(): void {}
    };
    $queue->push($job, 'default');

    expect(fn () => $queue->assertNothingPushed())->toThrow(AssertionFailedException::class);
});

/**
 * A unique job keyed by $key, released when it completes.
 */
class FakeQueueUniqueJob extends Job implements UniqueJobInterface
{
    public function __construct(
        private readonly string $key = '42',
    ) {}

    public function handle(): void {}

    public function uniqueId(): string
    {
        return $this->key;
    }

    public function uniqueFor(): int
    {
        return 60;
    }

    public function uniqueUntil(): UniqueUntil
    {
        return UniqueUntil::Processed;
    }
}

function fakeQueueWithArrayLocks(): FakeQueue
{
    $clock = new FakeClock();

    return FakeQueue::withLockFactory(new LockFactory(new ArrayLockStore($clock), $clock, new UsleepSleeper()));
}

it('skips a duplicate unique job through the unique-job push plugin', function () {
    $queue = fakeQueueWithArrayLocks();

    $first = $queue->push(new FakeQueueUniqueJob());
    $second = $queue->push(new FakeQueueUniqueJob());
    $later = $queue->later(30, new FakeQueueUniqueJob());

    expect($first->pushed)->toBeTrue()
        ->and($second->pushed)->toBeFalse()
        ->and($second->skipReason)->toContain('unique-job:' . FakeQueueUniqueJob::class . ':42')
        ->and($later->pushed)->toBeFalse()
        ->and($queue->pushed)->toHaveCount(1)
        ->and($queue->skipped)->toHaveCount(2)
        ->and($queue->skipped[0]['reason'])->toBe($second->skipReason);
});

it('throws when a unique job is pushed to a FakeQueue without a lock factory', function () {
    $queue = new FakeQueue();

    expect(fn () => $queue->push(new FakeQueueUniqueJob()))
        ->toThrow(QueueException::class, 'no lock driver is bound');
});

it('pushes jobs that are not unique without a lock factory', function () {
    $queue = new FakeQueue();
    $job = new class () extends Job
    {
        public function handle(): void {}
    };

    expect($queue->push($job)->pushed)->toBeTrue();
});

it('asserts a job class was pushed exactly once', function () {
    $queue = fakeQueueWithArrayLocks();
    $queue->push(new FakeQueueUniqueJob());
    $queue->push(new FakeQueueUniqueJob());

    $queue->assertPushedOnce(FakeQueueUniqueJob::class);

    $queue->push(new FakeQueueUniqueJob('43'));

    expect(fn () => $queue->assertPushedOnce(FakeQueueUniqueJob::class))
        ->toThrow(
            AssertionFailedException::class,
            'Expected 1 ' . FakeQueueUniqueJob::class . ' jobs pushed but got 2.',
        );
});

it('fails assertPushedOnce when the job class was never pushed', function () {
    expect(fn () => fakeQueueWithArrayLocks()->assertPushedOnce(FakeQueueUniqueJob::class))
        ->toThrow(AssertionFailedException::class);
});

it('asserts a job class was skipped', function () {
    $queue = fakeQueueWithArrayLocks();
    $queue->push(new FakeQueueUniqueJob());

    expect(fn () => $queue->assertSkipped(FakeQueueUniqueJob::class))
        ->toThrow(AssertionFailedException::class);

    $queue->push(new FakeQueueUniqueJob());

    $queue->assertSkipped(FakeQueueUniqueJob::class);
    $queue->assertSkipped(FakeQueueUniqueJob::class, fn (FakeQueueUniqueJob $job) => $job->uniqueId() === '42');

    expect(fn () => $queue->assertSkipped(
        FakeQueueUniqueJob::class,
        fn (FakeQueueUniqueJob $job) => $job->uniqueId() === '99',
    ))->toThrow(AssertionFailedException::class);
});
