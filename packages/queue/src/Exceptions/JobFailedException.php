<?php

declare(strict_types=1);

namespace Marko\Queue\Exceptions;

use Throwable;

class JobFailedException extends QueueException
{
    public static function fromException(
        string $jobClass,
        Throwable $exception,
    ): self {
        return new self(
            message: "Job '$jobClass' failed: {$exception->getMessage()}",
            context: "While executing job '$jobClass'",
            suggestion: 'Check the previous exception for details. Ensure all job dependencies are available.',
            previous: $exception,
        );
    }
}
