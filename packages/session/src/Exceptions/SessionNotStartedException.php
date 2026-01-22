<?php

declare(strict_types=1);

namespace Marko\Session\Exceptions;

class SessionNotStartedException extends SessionException
{
    public static function forOperation(
        string $operation,
    ): self {
        return new self(
            message: "Session not started: Cannot perform '$operation'",
            context: 'Attempted to access session before calling start()',
            suggestion: 'Call $session->start() before accessing session data',
        );
    }
}
