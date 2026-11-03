<?php

declare(strict_types=1);

namespace Marko\Queue\Exceptions;

use Marko\Cache\Contracts\CacheInterface;
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

    /**
     * @param string $retryAfterKey The config key the queue's retry_after comes from
     */
    public static function timeoutNotBelowRetryAfter(
        int $timeout,
        int $retryAfter,
        string $queue,
        string $retryAfterKey,
    ): self {
        return new self(
            message: "The queue worker timeout ({$timeout}s) must be shorter than $retryAfterKey ({$retryAfter}s)"
                . " on queue '$queue'.",
            context: 'A job still running when retry_after expires is reclaimed and handed to another worker,'
                . ' so it would run twice at once.',
            suggestion: "Lower queue.timeout / --timeout, or raise $retryAfterKey above it"
                . self::perQueueHint($queue, $retryAfterKey)
                . ' (leave a few seconds of margin).',
        );
    }

    /**
     * @param string $retryAfterKey The config key the queue's retry_after comes from
     */
    public static function jobTimeoutNotBelowRetryAfter(
        string $jobClass,
        int $timeout,
        int $retryAfter,
        string $queue,
        string $retryAfterKey,
    ): self {
        return new self(
            message: "Job '$jobClass' has a {$timeout}s timeout, which is not below $retryAfterKey"
                . " ({$retryAfter}s) on queue '$queue', so the worker failed it without running it.",
            context: 'A job still running when retry_after expires is reclaimed and handed to another worker,'
                . ' so it would run twice at once.',
            suggestion: "Raise $retryAfterKey above $timeout seconds (leave a few seconds of margin)"
                . self::perQueueHint($queue, $retryAfterKey)
                . ", or lower the job's \$timeout, then queue:retry the job.",
        );
    }

    public static function invalidQueueSettings(
        string $key,
        string $type,
    ): self {
        return new self(
            message: "Invalid $key in the queue config.",
            context: "$key must be an array of per-queue settings; got $type.",
            suggestion: "Set queue.queues to a map of queue name to settings, e.g. 'queues' => ['engine' =>"
                . " ['retry_after' => 3900]].",
        );
    }

    public static function invalidQueueRetryAfter(
        string $queue,
        mixed $value,
    ): self {
        $got = is_int($value) ? (string) $value : get_debug_type($value);

        return new self(
            message: "Invalid queue.queues.$queue.retry_after in the queue config.",
            context: "A queue's retry_after must be a whole number of seconds above zero; got $got.",
            suggestion: "Set queue.queues.$queue.retry_after to a positive integer, e.g. 3900, or remove it so"
                . ' the queue uses queue.retry_after.',
        );
    }

    /**
     * When a queue uses the global retry_after, point out that it can have its own instead.
     */
    private static function perQueueHint(
        string $queue,
        string $retryAfterKey,
    ): string {
        if ($retryAfterKey !== 'queue.retry_after') {
            return '';
        }

        return ", or give queue '$queue' its own queue.queues.$queue.retry_after";
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

    public static function lockPackageNotInstalled(
        string $jobClass,
    ): self {
        return new self(
            message: "Unique job $jobClass cannot be pushed: marko/cache is not installed.",
            context: 'A UniqueJobInterface job takes an atomic lock through ' . LockFactoryInterface::class
                . ', which marko/cache provides. marko/queue only suggests marko/cache, so it is not installed'
                . ' unless your app requires it.',
            suggestion: 'Run `composer require marko/cache marko/cache-redis` (marko/cache-redis shares the lock'
                . ' across servers; marko/cache-file locks one host).',
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

    public static function restartSignalNotWritable(
        string $path,
        string $reason,
    ): self {
        return new self(
            message: "Could not write the queue restart signal to '$path'.",
            context: $reason,
            suggestion: 'Make sure the directory of queue.restart_signal can be created and written by the user'
                . ' that runs queue:restart, or point queue.restart_signal (QUEUE_RESTART_SIGNAL) at a writable path.',
        );
    }

    public static function restartSignalNotReadable(
        string $path,
        string $reason,
    ): self {
        return new self(
            message: "Could not read the queue restart signal at '$path'.",
            context: "$reason Workers cannot tell whether a restart was requested.",
            suggestion: 'Make sure the user that runs queue:work can read the queue.restart_signal file.',
        );
    }

    /**
     * @param list<string> $drivers
     */
    public static function unknownRestartSignalDriver(
        string $driver,
        array $drivers,
    ): self {
        return new self(
            message: "Unknown queue.restart_signal_driver '$driver'.",
            context: 'queue.restart_signal_driver picks where queue:restart stores the token every queue:work'
                . ' watches.',
            suggestion: "Set queue.restart_signal_driver (QUEUE_RESTART_SIGNAL_DRIVER) to one of: '"
                . implode("', '", $drivers) . "'. Use 'file' for workers on one host or a shared disk, and 'cache'"
                . ' for workers on hosts that share nothing.',
        );
    }

    public static function restartSignalCachePackageNotInstalled(): self
    {
        return new self(
            message: 'The cache restart signal cannot be used: marko/cache is not installed.',
            context: "queue.restart_signal_driver is 'cache', which stores the restart token through "
                . CacheInterface::class . '. marko/queue only suggests marko/cache, so it is not installed unless'
                . ' your app requires it.',
            suggestion: 'Run `composer require marko/cache marko/cache-redis` (marko/cache-redis shares the token'
                . " across hosts), or set queue.restart_signal_driver (QUEUE_RESTART_SIGNAL_DRIVER) to 'file'.",
        );
    }

    public static function noRestartSignalCacheDriver(
        MarkoException $previous,
    ): self {
        return new self(
            message: 'The cache restart signal cannot be used: no cache driver is bound.',
            context: "queue.restart_signal_driver is 'cache', which stores the restart token through "
                . CacheInterface::class . ' (marko/cache), so a cache driver must be installed. Resolving it failed: '
                . $previous->getMessage(),
            suggestion: $previous->getSuggestion() . "\nWorkers on several hosts need a store they all reach, such"
                . ' as marko/cache-redis.',
            previous: $previous,
        );
    }

    public static function restartSignalCacheNotWritable(
        string $key,
    ): self {
        return new self(
            message: "Could not store the queue restart signal in the cache key '$key'.",
            context: 'The cache driver refused to store the token, so workers would not see the restart.',
            suggestion: 'Check that the cache store behind marko/cache (e.g. the Redis server of marko/cache-redis)'
                . ' is reachable and writable by the user that runs queue:restart.',
        );
    }

    public static function restartSignalCacheValueInvalid(
        string $key,
        string $type,
    ): self {
        return new self(
            message: "The cache key '$key' does not hold a queue restart token.",
            context: "The key holds a value of type $type; queue:restart stores a non-empty string there, so"
                . ' something else wrote to the key. Workers cannot tell whether a restart was requested.',
            suggestion: "Delete the cache key '$key' and run queue:restart again, and keep other code from writing"
                . ' to that key.',
        );
    }
}
