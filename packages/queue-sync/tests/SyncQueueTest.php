<?php

declare(strict_types=1);

namespace Marko\Queue\Sync\Tests;

use Marko\Queue\Exceptions\JobFailedException;
use Marko\Queue\Job;
use Marko\Queue\QueueInterface;
use Marko\Queue\Sync\SyncQueue;
use RuntimeException;

it('implements QueueInterface', function (): void {
    $queue = new SyncQueue();

    expect($queue)->toBeInstanceOf(QueueInterface::class);
});

it('push executes job immediately', function (): void {
    $queue = new SyncQueue();
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

it('push returns job ID', function (): void {
    $queue = new SyncQueue();

    $job = new class () extends Job
    {
        public function handle(): void {}
    };

    $id = $queue->push($job);

    expect($id)->toBeString()
        ->not->toBeEmpty()
        ->and($job->getId())->toBe($id);
});

it('later executes job immediately', function (): void {
    $queue = new SyncQueue();
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

    $id = $queue->later(60, $job);

    expect($executed)->toBeTrue()
        ->and($id)->toBeString()->not->toBeEmpty()
        ->and($job->getId())->toBe($id);
});

it('pop returns null', function (): void {
    $queue = new SyncQueue();

    expect($queue->pop())->toBeNull()
        ->and($queue->pop('custom'))->toBeNull();
});

it('size returns zero', function (): void {
    $queue = new SyncQueue();

    expect($queue->size())->toBe(0)
        ->and($queue->size('custom'))->toBe(0);
});

it('clear returns zero', function (): void {
    $queue = new SyncQueue();

    expect($queue->clear())->toBe(0)
        ->and($queue->clear('custom'))->toBe(0);
});

it('push throws JobFailedException on job failure', function (): void {
    $queue = new SyncQueue();

    $job = new class () extends Job
    {
        public function handle(): void
        {
            throw new RuntimeException('Job failed');
        }
    };

    $queue->push($job);
})->throws(JobFailedException::class, 'Job failed');
