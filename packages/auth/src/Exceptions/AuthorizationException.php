<?php

declare(strict_types=1);

namespace Marko\Auth\Exceptions;

class AuthorizationException extends AuthException
{
    public static function forbidden(
        string $ability,
        string $resource,
    ): self {
        return new self(
            message: 'Forbidden',
            context: "Unable to perform '$ability' on '$resource'",
            suggestion: 'You do not have permission to perform this action',
        );
    }
}
