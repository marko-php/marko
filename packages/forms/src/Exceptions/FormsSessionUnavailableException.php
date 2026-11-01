<?php

declare(strict_types=1);

namespace Marko\Forms\Exceptions;

/**
 * Thrown when RedirectBackOnValidationError runs on a route without a session,
 * so there is nowhere to flash the errors and old input.
 */
class FormsSessionUnavailableException extends FormsException
{
    public static function forRequest(
        string $method,
        string $path,
    ): self {
        return new self(
            message: "RedirectBackOnValidationError cannot handle $method $path: no session is available.",
            context: 'The middleware flashes validation errors and old input to the session for the next request, but this route runs without SessionMiddleware (no session driver is installed, or the route excludes it with #[WithoutMiddleware(SessionMiddleware::class)]).',
            suggestion: 'Install a session driver (marko/session-file or marko/session-database) and keep SessionMiddleware on the route, or remove RedirectBackOnValidationError from routes that do not use sessions.',
        );
    }
}
