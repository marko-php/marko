<?php

declare(strict_types=1);

use Marko\Queue\JobIdGenerator;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\Redis\RedisFailedJobRepository;
use Marko\Queue\Redis\RedisQueue;
use Marko\Queue\Redis\RedisQueueConfig;
use Marko\Queue\Redis\Tests\Fixtures\IntegrationRedis;
use Marko\Queue\Redis\Tests\Fixtures\QueueHarness;
use Marko\Queue\Redis\Tests\Fixtures\UniqueJobStack;
use Marko\Queue\Redis\Tests\Fixtures\UniqueTestJob;
use Marko\Queue\RetryPolicy;
use Marko\Testing\Fake\FakeConfigRepository;

/*
 * Unique jobs on RedisQueue against a real Redis server: the marko/queue push plugin and lifecycle observers do
 * all the work, with no driver code.
 */

pest()->group('integration-services');

beforeEach(function (): void {
    if (IntegrationRedis::unavailable()) {
        $this->markTestSkipped(IntegrationRedis::skipReason());
    }

    // Only used for its unique prefix and its cleanup
    $this->harness = new QueueHarness();
});

afterEach(function (): void {
    ($this->harness ?? null)?->flush();
});

function uniqueRedisQueue(
    UniqueJobStack $stack,
    string $prefix,
): QueueInterface {
    $config = new FakeConfigRepository([
        'queue.queue' => 'default',
        'queue.retry_after' => 90,
        'queue.max_attempts' => 3,
        'queue-redis.connection' => IntegrationRedis::CONNECTION,
        'queue-redis.prefix' => $prefix,
        'queue-redis.block_for' => 0,
    ]);
    $redisQueueConfig = new RedisQueueConfig($config);
    $queueConfig = new QueueConfig($config);

    return $stack->wrap(new RedisQueue(
        connectionFactory: IntegrationRedis::connectionFactory(),
        redisQueueConfig: $redisQueueConfig,
        queueConfig: $queueConfig,
        jobEnvelope: $stack->jobEnvelope,
        failedJobHandler: $stack->failedJobHandler(
            new RedisFailedJobRepository(IntegrationRedis::connectionFactory(), $redisQueueConfig),
        ),
        retryPolicy: new RetryPolicy($queueConfig, $stack->clock),
        clock: $stack->clock,
        jobIdGenerator: new JobIdGenerator(),
    ));
}

describe('RedisQueue unique jobs (no driver code)', function (): void {
    it('queues one job for duplicate pushes and releases the lock from the stored payload', function (): void {
        $stack = new UniqueJobStack();
        $queue = uniqueRedisQueue($stack, $this->harness->prefix);
        $first = new UniqueTestJob();

        $pushed = $queue->push($first);
        $skipped = $queue->push(new UniqueTestJob());
        $size = $queue->size();
        $popped = $queue->pop();

        expect($pushed->pushed)->toBeTrue()
            ->and($skipped->pushed)->toBeFalse()
            ->and($size)->toBe(1)
            ->and($popped->uniqueLockOwner)->toBe($first->uniqueLockOwner);

        $stack->jobRunner()->run($popped, 'default');

        expect($stack->lockIsFree($first))->toBeTrue();
    });

    it('releases the lock when pop() fails a job exhausted by crashed reservations', function (): void {
        $stack = new UniqueJobStack();
        $queue = uniqueRedisQueue($stack, $this->harness->prefix);
        $job = new UniqueTestJob(maxAttempts: 1);
        $queue->push($job);

        $queue->pop();
        $stack->clock->travel('+90 seconds');

        expect($queue->pop())->toBeNull()
            ->and($stack->lockIsFree($job))->toBeTrue();
    });
});
