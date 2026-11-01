<?php

declare(strict_types=1);

namespace Marko\Authentication\Exceptions;

use Marko\Core\Exceptions\HttpExceptionInterface;

/**
 * The user has made too many password confirmation attempts.
 *
 * Rendered as 429 Too Many Requests with a Retry-After header by the routing
 * pipeline wherever it is thrown; catch it to render your own confirmation page.
 */
class TooManyPasswordConfirmationAttemptsException extends AuthException implements HttpExceptionInterface
{
    private int $retryAfter = 1;

    public static function lockedOut(
        int $retryAfter,
    ): self {
        $exception = new self(
            message: 'Too many password confirmation attempts. Please try again later.',
            context: "Password confirmation attempts for this user are locked out for $retryAfter more seconds",
            suggestion: 'Wait until the lockout ends, or tune authentication.password_confirmation.throttle (max_attempts, decay_seconds)',
        );
        $exception->retryAfter = max(1, $retryAfter);

        return $exception;
    }

    /**
     * Seconds until the user may try again.
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function getStatusCode(): int
    {
        return 429;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return ['Retry-After' => (string) $this->retryAfter];
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponseData(): array
    {
        return [
            'message' => $this->getMessage(),
            'retry_after' => $this->retryAfter,
        ];
    }
}
