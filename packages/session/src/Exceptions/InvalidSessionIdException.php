<?php

declare(strict_types=1);

namespace Marko\Session\Exceptions;

class InvalidSessionIdException extends SessionException
{
    public static function forId(
        string $id,
    ): self {
        return new self(
            message: 'Invalid session ID format',
            context: "Provided session ID: $id",
            suggestion: 'Session IDs must be alphanumeric (with hyphens) and between 32-128 characters',
        );
    }
}
