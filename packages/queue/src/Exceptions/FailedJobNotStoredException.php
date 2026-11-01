<?php

declare(strict_types=1);

namespace Marko\Queue\Exceptions;

use Throwable;

/**
 * A job failed for good, but the failed-job repository could not store it.
 *
 * FailedJobHandler throws it before failed() runs or JobFailedEvent is dispatched, and the caller does not
 * delete the job, so the job stays in its queue (reserved, for drivers that reserve) and is failed again later.
 */
class FailedJobNotStoredException extends QueueException
{
    public static function failedJobNotStored(
        string $jobClass,
        ?string $jobId,
        string $queue,
        Throwable $jobException,
        Throwable $storageError,
    ): self {
        return new self(
            message: "Failed job $jobClass (id " . ($jobId ?? 'none') . ') could not be stored in the failed-job'
                . ' repository.',
            context: "The job failed for good on queue '$queue' with " . $jobException::class . ': '
                . $jobException->getMessage() . '. Storing it then threw ' . $storageError::class . ': '
                . $storageError->getMessage() . '. Its failed() hook was not called and JobFailedEvent was not'
                . ' dispatched. The job was not deleted from its queue, so it is failed again once its reservation'
                . ' expires (queue.retry_after).',
            suggestion: 'Check that the failed-job store is reachable and writable (for marko/queue-database, that'
                . ' the failed_jobs table exists and the connection works), then restart the worker.',
            previous: $storageError,
        );
    }
}
