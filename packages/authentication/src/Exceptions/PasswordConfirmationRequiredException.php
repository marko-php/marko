<?php

declare(strict_types=1);

namespace Marko\Authentication\Exceptions;

use Marko\Routing\Exceptions\HttpException;

/**
 * A JSON request reached a route behind RequirePasswordConfirmationMiddleware
 * without a recent password confirmation. Rendered as 423 Locked; the client
 * should ask the user for their password, confirm it, and retry.
 */
class PasswordConfirmationRequiredException extends HttpException
{
    public static function create(): self
    {
        return new self(
            statusCode: 423,
            message: 'Password confirmation required.',
            context: 'The session has no password confirmation within authentication.password_confirmation.timeout_seconds',
            suggestion: 'Confirm the password (PasswordConfirmationInterface::confirm()), then retry the request',
        );
    }
}
