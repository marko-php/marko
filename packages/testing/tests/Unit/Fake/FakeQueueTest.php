<?php

declare(strict_types=1);

use Marko\Queue\Job;
use Marko\Queue\QueueInterface;
use Marko\Testing\Exceptions\AssertionFailedException;
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

    $id = $queue->push($job, 'default');

    expect($queue->pushed)->toHaveCount(1)
        ->and($queue->pushed[0]['job'])->toBe($job)
        ->and($queue->pushed[0]['queue'])->toBe('default')
        ->and($queue->pushed[0]['delay'])->toBe(0)
        ->and($queue->pushed[0]['id'])->toBe($id);
});

it('captures delayed jobs with delay and queue name', function () {
    $queue = new FakeQueue();
    $job = new class () extends Job
    {
        public function handle(): void {}
    };

    $id = $queue->later(60, $job, 'deferred');

    expect($queue->pushed)->toHaveCount(1)
        ->and($queue->pushed[0]['job'])->toBe($job)
        ->and($queue->pushed[0]['queue'])->toBe('deferred')
        ->and($queue->pushed[0]['delay'])->toBe(60)
        ->and($queue->pushed[0]['id'])->toBe($id);
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

    $id1 = $queue->push($job1, 'default');
    $queue->push($job2, 'default');

    $deleted = $queue->delete($id1);

    expect($deleted)->toBeTrue()
        ->and($queue->pushed)->toHaveCount(1)
        ->and($queue->pushed[0]['job'])->toBe($job2);

    expect($queue->delete('nonexistent'))->toBeFalse();
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

    expect(fn () => $queue->assertNotPushed($job::class))->toThrow(AssertionFailedException::class);
    expect(fn () => $queue->assertNotPushed($otherJob::class))->not->toThrow(AssertionFailedException::class);
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

    expect(fn () => $queue->assertPushedCount(2))->not->toThrow(AssertionFailedException::class);
    expect(fn () => $queue->assertPushedCount(1))->toThrow(AssertionFailedException::class);
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
