<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Queue\Exceptions\JobAttemptsExhaustedException;
use Marko\Queue\Exceptions\JobExceptionsExhaustedException;
use Marko\Queue\Exceptions\JobRetryDeadlinePassedException;
use Marko\Queue\Exceptions\QueueException;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Decides whether a job gets another attempt. The Worker and the drivers that check limits at pop time
 * (DatabaseQueue) all ask this class, so the precedence of the limits is coded once:
 *
 * 1. a retry deadline that has passed fails the job;
 * 2. then exceptions reaching maxExceptions fail it;
 * 3. then, only when the job has no retry deadline, attempts reaching maxAttempts
 *    (or the queue.max_attempts default) fail it;
 * 4. otherwise the job is released after the delay it asked for with release(), or its backoff.
 */
class RetryPolicy
{
    public function __construct(
        private readonly QueueConfig $config,
        private readonly ClockInterface $clock,
        private readonly BackoffValidator $backoffValidator = new BackoffValidator(),
    ) {}

    /**
     * Decide what happens after an attempt that threw $e, or that returned after asking to be released ($e null).
     *
     * A job whose backoff is invalid cannot be released, so it is failed with the backoff error.
     */
    public function decide(
        JobInterface $job,
        ?Throwable $e = null,
    ): RetryDecision {
        $failure = $this->limitReached($job);

        if ($failure !== null) {
            return RetryDecision::fail($failure);
        }

        if ($e === null && $job->releaseDelay !== null) {
            return RetryDecision::release($job->releaseDelay);
        }

        try {
            return RetryDecision::release($this->backoffFor($job));
        } catch (QueueException $backoffError) {
            return RetryDecision::fail($backoffError);
        }
    }

    /**
     * The limit the job has reached, in precedence order, or null when it may run again.
     */
    public function limitReached(
        JobInterface $job,
    ): ?QueueException {
        if ($job->retryDeadline !== null) {
            $now = $this->clock->now();

            if ($now >= $job->retryDeadline) {
                return JobRetryDeadlinePassedException::passed($job::class, $job->id, $job->retryDeadline, $now);
            }
        }

        if ($job->maxExceptions !== null && $job->exceptions >= $job->maxExceptions) {
            return JobExceptionsExhaustedException::exhausted(
                $job::class,
                $job->id,
                $job->exceptions,
                $job->maxExceptions,
            );
        }

        if ($job->retryDeadline === null) {
            $maxAttempts = $this->maxAttemptsFor($job);

            if ($job->attempts >= $maxAttempts) {
                return JobAttemptsExhaustedException::exhausted($job::class, $job->id, $job->attempts, $maxAttempts);
            }
        }

        return null;
    }

    /**
     * The job's maxAttempts, or the queue.max_attempts config default.
     */
    public function maxAttemptsFor(
        JobInterface $job,
    ): int {
        return $job->maxAttempts ?? $this->config->maxAttempts();
    }

    /**
     * Seconds to wait before retrying a job whose latest attempt failed.
     *
     * Uses the job's $backoff, then the `queue.backoff` config, then the exponential
     * curve 2^attempts * 10. A list backoff is indexed by attempt and its last value repeats.
     *
     * @throws QueueException When the job's $backoff or the queue.backoff config is invalid
     */
    public function backoffFor(
        JobInterface $job,
    ): int {
        $backoff = $job->backoff !== null
            ? $this->backoffValidator->validate($job->backoff, 'job ' . $job::class)
            : $this->config->backoff();

        if ($backoff === null) {
            return (int) pow(2, $job->attempts) * 10;
        }

        if (is_int($backoff)) {
            return $backoff;
        }

        return $backoff[min(max($job->attempts, 1), count($backoff)) - 1];
    }
}
