<?php

declare(strict_types=1);

namespace Marko\Queue\Exceptions;

class JobExceptionsExhaustedException extends QueueException
{
    public static function exhausted(
        string $jobClass,
        ?string $jobId,
        int $exceptions,
        int $maxExceptions,
    ): self {
        return new self(
            message: "Job $jobClass" . ($jobId !== null ? " ($jobId)" : '')
                . " threw $exceptions of its $maxExceptions allowed exceptions.",
            context: 'maxExceptions counts attempts that ended in an exception or timed out. Deliberate releases'
                . ' from inside handle() are not counted.',
            suggestion: "Check the job's exception for the cause, fix it, then run queue:retry for the job."
                . ' Raise maxExceptions if the job should tolerate more failures.',
        );
    }
}
