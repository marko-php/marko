<?php

declare(strict_types=1);

namespace Marko\Queue\Exceptions;

class SerializationException extends QueueException
{
    public static function invalidJobData(
        string $reason,
    ): self {
        return new self(
            message: 'Invalid job data: cannot deserialize job payload.',
            context: "Deserialization failed: $reason",
            suggestion: 'Ensure the job data was serialized correctly and the payload is not corrupted.',
        );
    }

    public static function unserializableClosure(
        string $jobClass,
    ): self {
        return new self(
            message: 'Job contains an unserializable closure.',
            context: "Job class '$jobClass' contains a Closure that cannot be serialized.",
            suggestion: 'Remove the Closure from the job or convert it to an invokable class.',
        );
    }
}
