<?php

declare(strict_types=1);

namespace Marko\Security\Exceptions;

class CsrfTokenMismatchException extends SecurityException
{
    public static function invalidToken(): self
    {
        return new self(
            message: 'CSRF token validation failed.',
            context: 'The submitted CSRF token does not match the token stored in the session. This can happen when the session has expired, the token was not included in the request, or the token has been tampered with.',
            suggestion: 'Ensure your form includes a valid CSRF token field (_token) or X-CSRF-TOKEN header. If the session has expired, refresh the page to get a new token.',
        );
    }
}
