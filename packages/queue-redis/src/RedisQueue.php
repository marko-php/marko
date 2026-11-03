<?php

declare(strict_types=1);

namespace Marko\Queue\Redis;

use Marko\Queue\Exceptions\FailedJobNotStoredException;
use Marko\Queue\Exceptions\SerializationException;
use Marko\Queue\FailedJobHandlerInterface;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobIdGenerator;
use Marko\Queue\JobInterface;
use Marko\Queue\PushResult;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\Redis\Exceptions\RedisQueueException;
use Marko\Queue\RetryPolicy;
use Marko\Redis\Exceptions\RedisConnectionException;
use Marko\Redis\RedisConnectionFactoryInterface;
use Predis\ClientInterface;
use Psr\Clock\ClockInterface;
use Random\RandomException;

/**
 * Redis-backed queue. See RedisQueueScripts for the key layout.
 *
 * Reliable pop: one Lua script moves due delayed jobs and expired reservations back to ready, pops one job and
 * adds it to the reserved set, atomically. A worker that crashes after the pop leaves the job reserved, and the
 * first pop after the reservation expires (the queue's retry_after seconds later) puts it back.
 *
 * Attempt counting works like DatabaseQueue: every reservation increments the attempts field of the job hash,
 * so it counts attempts started, including ones whose worker died. pop() brings the popped job's attempts up to
 * the reservations made before this one (the worker then increments it for the current run); release() stores the job the worker hands back with every piece of per-job state. A popped job
 * that has already reached a RetryPolicy limit is failed through the FailedJobHandlerInterface and deleted
 * instead of being returned.
 *
 * Blocking pop: when the caller can block (the Worker passes true only for a single queue) and
 * queue-redis.block_for is above 0, an empty pop waits on the queue's notify list with BLPOP for up to block_for
 * seconds, then tries once more. Due delayed jobs and expired reservations do not wake it; the next pop finds them.
 *
 * Times come from the clock in whole seconds, so every process that shares the queue needs a synchronised clock.
 */
class RedisQueue implements QueueInterface
{
    public function __construct(
        private readonly RedisConnectionFactoryInterface $connectionFactory,
        private readonly RedisQueueConfig $redisQueueConfig,
        private readonly QueueConfig $queueConfig,
        private readonly JobEnvelope $jobEnvelope,
        private readonly FailedJobHandlerInterface $failedJobHandler,
        private readonly RetryPolicy $retryPolicy,
        private readonly ClockInterface $clock,
        private readonly JobIdGenerator $jobIdGenerator,
    ) {}

    /**
     * @throws RandomException|RedisConnectionException|SerializationException
     */
    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        return PushResult::pushed($this->store($job, $queue, 0));
    }

    /**
     * @throws RandomException|RedisConnectionException|SerializationException
     */
    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        return PushResult::pushed($this->store($job, $queue, $delay));
    }

    /**
     * A job failed here that the failed-job repository cannot store stays reserved, and the handler's
     * FailedJobNotStoredException is thrown; the job is failed again once the reservation expires.
     *
     * @throws FailedJobNotStoredException|RedisConnectionException|RedisQueueException|SerializationException
     */
    public function pop(
        ?string $queue = null,
        bool $block = false,
    ): ?JobInterface {
        $queueName = $queue ?? $this->queueConfig->queue();
        $blockFor = $block ? $this->redisQueueConfig->blockFor() : 0;

        while (true) {
            $job = $this->reserveNext($queueName);

            if ($job === null && $blockFor > 0) {
                $this->client()->blpop([$this->queueKey($queueName, 'notify')], $blockFor);
                // Wait once per pop() call, so a worker still checks its stop conditions between waits
                $blockFor = 0;
                $job = $this->reserveNext($queueName);
            }

            if ($job === null) {
                return null;
            }

            $failure = $this->retryPolicy->limitReachedOnReservation($job);

            if ($failure === null) {
                return $job;
            }

            // A limit was reached before this attempt could start: fail the job, then try the next one.
            // The job is still reserved, so no other worker picks it up while it is being failed.
            $this->failedJobHandler->handle($job, $queueName, $failure);
            $this->delete((string) $job->id);
        }
    }

    /**
     * Ready jobs plus delayed jobs that are due, like DatabaseQueue::size(). Reserved jobs are not counted.
     *
     * @throws RedisConnectionException
     */
    public function size(
        ?string $queue = null,
    ): int {
        $queueName = $queue ?? $this->queueConfig->queue();
        $client = $this->client();

        return (int) $client->llen($this->queueKey($queueName))
            + (int) $client->zcount($this->queueKey($queueName, 'delayed'), '-inf', $this->now());
    }

    /**
     * Remove every ready, delayed and reserved job of the queue.
     *
     * @throws RedisConnectionException
     */
    public function clear(
        ?string $queue = null,
    ): int {
        $queueName = $queue ?? $this->queueConfig->queue();

        return (int) $this->client()->eval(
            RedisQueueScripts::CLEAR,
            4,
            $this->queueKey($queueName),
            $this->queueKey($queueName, 'delayed'),
            $this->queueKey($queueName, 'reserved'),
            $this->queueKey($queueName, 'notify'),
            $this->jobKey(''),
        );
    }

    /**
     * @throws RedisConnectionException
     */
    public function delete(
        string $jobId,
    ): bool {
        $queueName = $this->queueOf($jobId);

        if ($queueName === null) {
            return false;
        }

        return (int) $this->client()->eval(
            RedisQueueScripts::DELETE,
            4,
            $this->jobKey($jobId),
            $this->queueKey($queueName),
            $this->queueKey($queueName, 'delayed'),
            $this->queueKey($queueName, 'reserved'),
            $jobId,
        ) === 1;
    }

    /**
     * Release a reserved job back to its queue, storing the given job (attempts, exceptions, retry deadline) in
     * place of the payload it was reserved with.
     *
     * @throws RedisConnectionException|SerializationException
     */
    public function release(
        JobInterface $job,
        int $delay = 0,
    ): bool {
        if ($job->id === null) {
            return false;
        }

        $queueName = $this->queueOf($job->id);

        if ($queueName === null) {
            return false;
        }

        return (int) $this->client()->eval(
            RedisQueueScripts::RELEASE,
            5,
            $this->jobKey($job->id),
            $this->queueKey($queueName),
            $this->queueKey($queueName, 'delayed'),
            $this->queueKey($queueName, 'notify'),
            $this->queueKey($queueName, 'reserved'),
            $this->jobEnvelope->wrap($job->serialize()),
            $job->attempts,
            $job->id,
            $this->now() + $delay,
            $delay > 0 ? '1' : '0',
        ) === 1;
    }

    /**
     * @throws RandomException|RedisConnectionException|SerializationException
     */
    private function store(
        JobInterface $job,
        ?string $queue,
        int $delay,
    ): string {
        $id = $this->jobIdGenerator->generate();
        $job->setId($id);
        $queueName = $queue ?? $this->queueConfig->queue();

        $this->client()->eval(
            RedisQueueScripts::PUSH,
            4,
            $this->jobKey($id),
            $this->queueKey($queueName),
            $this->queueKey($queueName, 'delayed'),
            $this->queueKey($queueName, 'notify'),
            $queueName,
            $this->jobEnvelope->wrap($job->serialize()),
            $job->attempts,
            $id,
            $this->now() + $delay,
            $delay > 0 ? '1' : '0',
        );

        return $id;
    }

    /**
     * Reserve the next ready job, or return null when none is ready.
     *
     * @throws RedisConnectionException|SerializationException
     */
    private function reserveNext(
        string $queueName,
    ): ?JobInterface {
        $now = $this->now();

        /** @var array{0: string, 1: string, 2: int}|null $reserved */
        $reserved = $this->client()->eval(
            RedisQueueScripts::POP,
            4,
            $this->queueKey($queueName),
            $this->queueKey($queueName, 'delayed'),
            $this->queueKey($queueName, 'reserved'),
            $this->queueKey($queueName, 'notify'),
            $now,
            $now + $this->queueConfig->retryAfter($queueName),
            $this->jobKey(''),
        );

        if ($reserved === null) {
            return null;
        }

        [$id, $payload, $attempts] = $reserved;

        /** @var JobInterface $job */
        $job = unserialize($this->jobEnvelope->verifyAndUnwrap($payload));

        while ($job->attempts < (int) $attempts) {
            $job->incrementAttempts();
        }

        $job->setId($id);

        return $job;
    }

    /**
     * The queue a stored job belongs to, or null when no job has the id. A job never changes queue, so the
     * scripts can be given the queue's keys after this lookup.
     *
     * @throws RedisConnectionException
     */
    private function queueOf(
        string $jobId,
    ): ?string {
        $queueName = $this->client()->hget($this->jobKey($jobId), 'queue');

        return $queueName === null ? null : (string) $queueName;
    }

    private function queueKey(
        string $queueName,
        ?string $suffix = null,
    ): string {
        return $this->redisQueueConfig->prefix() . "queues:$queueName" . ($suffix === null ? '' : ":$suffix");
    }

    private function jobKey(
        string $jobId,
    ): string {
        return $this->redisQueueConfig->prefix() . "jobs:$jobId";
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }

    /**
     * @throws RedisConnectionException
     */
    private function client(): ClientInterface
    {
        return $this->connectionFactory->connection($this->redisQueueConfig->connection())->client();
    }
}
