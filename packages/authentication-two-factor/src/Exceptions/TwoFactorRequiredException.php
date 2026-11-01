<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor\Exceptions;

use Marko\Routing\Exceptions\HttpException;

/**
 * The 403 RequireTwoFactorMiddleware returns to a JSON request from a user
 * who has not confirmed two-factor authentication.
 */
class TwoFactorRequiredException extends HttpException
{
    public static function forGuard(
        string $guard,
    ): self {
        return new self(
            statusCode: 403,
            message: 'Two-factor authentication is required.',
            context: "The authenticated user on guard '$guard' has not confirmed two-factor authentication",
            suggestion: 'Enable and confirm two-factor authentication for this user before calling this route',
        );
    }
}
