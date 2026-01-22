<?php

declare(strict_types=1);

namespace Marko\Auth\Exceptions;

class InvalidCredentialsException extends AuthenticationException
{
    public static function invalidCredentials(): self
    {
        return new self(
            message: 'Invalid credentials',
            context: 'The provided credentials do not match our records',
            suggestion: 'Please check your username and password and try again',
        );
    }
}
