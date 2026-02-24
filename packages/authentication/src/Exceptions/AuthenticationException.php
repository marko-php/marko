<?php

declare(strict_types=1);

namespace Marko\Authentication\Exceptions;

class AuthenticationException extends AuthException
{
    public static function unauthenticated(
        string $guard,
    ): self {
        return new self(
            message: 'Unauthenticated',
            context: "Authentication required for guard: $guard",
            suggestion: 'Please log in to access this resource',
        );
    }
}
