<?php

declare(strict_types=1);

namespace Marko\Queue\Exceptions;

class JobAttemptsExhaustedException extends QueueException
{
    /**
     * A job whose reservations reached its max attempts without any attempt completing or
     * releasing it, because its worker crashed, was killed or timed out each time.
     */
    public static function afterCrashedReservations(
        string $jobId,
        int $attempts,
        int $maxAttempts,
    ): self {
        return new self(
            message: "Job $jobId exceeded max attempts after worker crash or timeout: "
                . "$attempts of $maxAttempts attempts were reserved without being completed or released.",
            context: 'Each reservation counts as an attempt, so a job that crashes or hangs its worker'
                . ' is failed once its attempts are used up instead of being retried forever.',
            suggestion: 'Check the worker logs for fatal errors, out-of-memory kills or timeouts while this job ran,'
                . ' fix the cause, then run queue:retry for the job.',
        );
    }
}
