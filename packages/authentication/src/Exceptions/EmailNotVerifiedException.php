<?php

declare(strict_types=1);

namespace Marko\Authentication\Exceptions;

use Marko\Routing\Exceptions\HttpException;

/**
 * A JSON request reached a route behind EnsureEmailVerifiedMiddleware from a user
 * whose email is not verified. Rendered as 403 Forbidden.
 */
class EmailNotVerifiedException extends HttpException
{
    public static function create(): self
    {
        return new self(
            statusCode: 403,
            message: 'Your email address is not verified.',
            context: 'The authenticated user implements MustVerifyEmailInterface and hasVerifiedEmail() returned false',
            suggestion: 'Send a verification link (EmailVerifierInterface::sendVerificationLink()), follow it, then retry the request',
        );
    }
}
