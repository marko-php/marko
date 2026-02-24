<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Exceptions;

class InvalidTokenException extends TokenException
{
    public static function forToken(
        string $token,
    ): self {
        return new self(
            message: 'Invalid token format',
            context: "The token '$token' has an invalid or malformed format",
            suggestion: 'Ensure the token is a valid personal access token',
        );
    }
}
