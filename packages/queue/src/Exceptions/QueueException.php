<?php

declare(strict_types=1);

namespace Marko\Queue\Exceptions;

use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Core\Exceptions\MarkoException;

class QueueException extends MarkoException
{
    public static function configFileNotFound(
        string $path,
    ): self {
        return new self(
            message: 'Queue configuration file queue.php not found.',
            context: "Expected config file at: $path",
            suggestion: 'Create a config/queue.php file with your queue configuration.',
        );
    }

    public static function noQueuesGiven(): self
    {
        return new self(
            message: 'No queues given to the queue worker.',
            context: 'The worker received an empty queue list.',
            suggestion: 'Pass queue names in priority order, e.g. --queue=high,default,'
                . ' or omit --queue to work the default queue.',
        );
    }

    public static function invalidQueueName(
        string $reason,
    ): self {
        return new self(
            message: 'Invalid queue name given to the queue worker.',
            context: $reason,
            suggestion: 'Pass queue names in priority order, e.g. --queue=high,default.',
        );
    }

    public static function invalidWorkerOption(
        string $option,
        string $value,
    ): self {
        return new self(
            message: "Invalid --$option value for the queue worker.",
            context: "--$option must be a whole number of zero or more; got '$value'.",
            suggestion: "Pass a non-negative integer, e.g. --$option=60, or 0 to turn the limit off.",
        );
    }

    public static function timeoutNotBelowRetryAfter(
        int $timeout,
        int $retryAfter,
    ): self {
        return new self(
            message: "The queue worker timeout ({$timeout}s) must be shorter than queue.retry_after ({$retryAfter}s).",
            context: 'A job still running when retry_after expires is reclaimed and handed to another worker,'
                . ' so it would run twice at once.',
            suggestion: 'Lower queue.timeout / --timeout, or raise queue.retry_after above it'
                . ' (leave a few seconds of margin).',
        );
    }

    public static function jobTimeoutNotBelowRetryAfter(
        string $jobClass,
        int $timeout,
        int $retryAfter,
    ): self {
        return new self(
            message: "Job '$jobClass' has a {$timeout}s timeout, which is not below queue.retry_after"
                . " ({$retryAfter}s), so the worker failed it without running it.",
            context: 'A job still running when retry_after expires is reclaimed and handed to another worker,'
                . ' so it would run twice at once.',
            suggestion: "Raise queue.retry_after above $timeout seconds (leave a few seconds of margin),"
                . " or lower the job's \$timeout, then queue:retry the job.",
        );
    }

    public static function invalidStopCondition(
        string $type,
    ): self {
        return new self(
            message: 'Invalid stop condition for the queue worker.',
            context: "WorkerOptions::\$stopConditions must hold only WorkerStopConditionInterface objects; got $type.",
            suggestion: 'Pass objects that implement Marko\Queue\WorkerStopConditionInterface.',
        );
    }

    public static function invalidBackoff(
        string $source,
        string $reason,
    ): self {
        return new self(
            message: "Invalid queue backoff in $source.",
            context: $reason,
            suggestion: 'Set backoff to a non-negative int (fixed seconds), a non-empty list of non-negative ints'
                . ' (seconds per attempt; the last value repeats), or null (2^attempts * 10 seconds).',
        );
    }

    public static function invalidReleaseDelay(
        string $jobClass,
        int $delaySeconds,
    ): self {
        return new self(
            message: "Invalid release delay for job $jobClass.",
            context: "release() was called with $delaySeconds seconds; the delay cannot be negative.",
            suggestion: 'Pass 0 to release the job for an immediate retry, or a positive number of seconds.',
        );
    }

    public static function invalidRetryDelay(
        int $delaySeconds,
    ): self {
        return new self(
            message: 'Invalid retry delay.',
            context: "A job cannot be released after $delaySeconds seconds; the delay cannot be negative.",
            suggestion: 'Release with 0 for an immediate retry, or a positive number of seconds.',
        );
    }

    public static function invalidUniqueFor(
        string $jobClass,
        int $seconds,
    ): self {
        return new self(
            message: "Invalid uniqueFor() for unique job $jobClass.",
            context: "uniqueFor() returned $seconds; the unique-job lock must expire after at least 1 second,"
                . ' or a crashed job would block every later push of it forever.',
            suggestion: 'Return the most seconds the job may stay queued and running, e.g. 3600 for one hour.',
        );
    }

    public static function noLockDriver(
        string $jobClass,
        MarkoException $previous,
    ): self {
        return new self(
            message: "Unique job $jobClass cannot be pushed: no lock driver is bound.",
            context: 'A UniqueJobInterface job takes an atomic lock through ' . LockFactoryInterface::class
                . ' (marko/cache), which needs the LockStoreInterface a cache driver ships. Resolving it failed: '
                . $previous->getMessage(),
            suggestion: $previous->getSuggestion(),
            previous: $previous,
        );
    }

    public static function invalidPushResult(
        string $reason,
    ): self {
        return new self(
            message: 'Invalid push result.',
            context: $reason,
            suggestion: 'Use PushResult::pushed($id) for a queued job or PushResult::skipped($reason)'
                . ' for a job that was not queued.',
        );
    }
}
