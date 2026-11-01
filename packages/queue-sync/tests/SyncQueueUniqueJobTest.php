<?php

declare(strict_types=1);

use Marko\Queue\Exceptions\JobFailedException;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\Sync\NullFailedJobRepository;
use Marko\Queue\Sync\SyncQueue;
use Marko\Queue\Tests\Fixtures\UniqueJobStack;
use Marko\Queue\Tests\Fixtures\UniqueTestJob;
use Marko\Testing\Fake\FakeConfigRepository;

function uniqueSyncQueue(
    UniqueJobStack $stack,
): QueueInterface {
    return $stack->wrap(new SyncQueue(
        $stack->jobRunner(),
        $stack->failedJobHandler(new NullFailedJobRepository()),
        new QueueConfig(new FakeConfigRepository(['queue.queue' => 'default'])),
    ));
}

describe('SyncQueue unique jobs (no driver code)', function (): void {
    it('skips a duplicate pushed while the job runs and releases the lock once it has run', function (): void {
        $stack = new UniqueJobStack();
        $queue = uniqueSyncQueue($stack);
        $duringRun = null;
        $job = new UniqueTestJob(during: function () use ($queue, &$duringRun): void {
            $duringRun = $queue->push(new UniqueTestJob());
        });

        $result = $queue->push($job);

        expect($result->pushed)->toBeTrue()
            ->and($duringRun?->pushed)->toBeFalse()
            ->and($stack->lockIsFree($job))->toBeTrue();
    });

    it('releases the lock when the sync job fails', function (): void {
        $stack = new UniqueJobStack();
        $queue = uniqueSyncQueue($stack);
        $job = new UniqueTestJob(fail: true);

        expect(fn () => $queue->push($job))->toThrow(JobFailedException::class)
            ->and($stack->lockIsFree($job))->toBeTrue();
    });
});
