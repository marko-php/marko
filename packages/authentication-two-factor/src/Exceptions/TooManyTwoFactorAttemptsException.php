<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor\Exceptions;

use Marko\Routing\Exceptions\HttpException;

/**
 * Too many two-factor codes were tried for this pending user.
 *
 * Rendered as 429 Too Many Requests with a Retry-After header by the routing
 * pipeline wherever it is thrown; catch it to render your own challenge page.
 */
class TooManyTwoFactorAttemptsException extends HttpException
{
    public static function lockedOut(
        string $guard,
        int $retryAfter,
    ): self {
        $retryAfter = max(1, $retryAfter);

        return new self(
            statusCode: 429,
            message: 'Too many two-factor attempts. Please try again later.',
            headers: ['Retry-After' => (string) $retryAfter],
            data: ['retry_after' => $retryAfter],
            context: "Two-factor codes for this pending user on guard '$guard' are locked out for $retryAfter more seconds",
            suggestion: 'Wait until the lockout ends, or tune authentication-two-factor.throttle (max_attempts, decay_seconds)',
        );
    }

    /**
     * Seconds until the user may try again.
     */
    public function getRetryAfter(): int
    {
        return (int) $this->getHeaders()['Retry-After'];
    }
}
