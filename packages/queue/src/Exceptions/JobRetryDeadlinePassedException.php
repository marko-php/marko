<?php

declare(strict_types=1);

namespace Marko\Queue\Exceptions;

use DateTimeImmutable;
use DateTimeInterface;

class JobRetryDeadlinePassedException extends QueueException
{
    public static function passed(
        string $jobClass,
        ?string $jobId,
        DateTimeImmutable $deadline,
        DateTimeImmutable $now,
    ): self {
        return new self(
            message: "Job $jobClass" . ($jobId !== null ? " ($jobId)" : '') . ' passed its retry deadline of '
                . $deadline->format(DateTimeInterface::ATOM) . '.',
            context: 'The deadline was captured from retryUntil() when the job was pushed; it was '
                . $now->format(DateTimeInterface::ATOM) . ' when the job was checked again.',
            suggestion: 'Return a later time from retryUntil() if the job needs longer, then run queue:retry for'
                . ' the job: it is pushed again with a fresh deadline.',
        );
    }
}
