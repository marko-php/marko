<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Exceptions;

use Marko\Routing\Exceptions\HttpException;

/**
 * Too many passkey assertions failed from this client. Rendered as 429 Too
 * Many Requests with a Retry-After header.
 */
class TooManyPasskeyAttemptsException extends HttpException
{
    private int $retryAfter = 1;

    public static function lockedOut(
        int $retryAfter,
    ): self {
        $retryAfter = max(1, $retryAfter);
        $exception = new self(
            statusCode: 429,
            message: 'Too many passkey attempts. Please try again later.',
            headers: ['Retry-After' => (string) $retryAfter],
            data: ['retry_after' => $retryAfter],
            context: "Passkey assertions from this client are locked out for $retryAfter more seconds",
            suggestion: 'Wait until the lockout ends, or tune authentication-passkey.throttle (max_attempts, decay_seconds)',
        );
        $exception->retryAfter = $retryAfter;

        return $exception;
    }

    /**
     * Seconds until the client may try again.
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
