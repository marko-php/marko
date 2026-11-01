<?php

declare(strict_types=1);

namespace Marko\Queue\Exceptions;

class JobTimedOutException extends QueueException
{
    public static function exceeded(
        string $jobClass,
        int $seconds,
    ): self {
        return new self(
            message: "Job '$jobClass' exceeded the $seconds-second timeout.",
            context: 'The worker stopped the job and exited so its process supervisor can start a fresh worker.',
            suggestion: 'Make the job finish sooner (for example, set timeouts on its external calls),'
                . " or raise the job's \$timeout property (or queue.timeout / --timeout for jobs that set none),"
                . ' keeping it below queue.retry_after.',
        );
    }
}
