<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class AdminAuthException extends MarkoException
{
    public static function duplicatePermission(
        string $key,
    ): self {
        return new self(
            message: "Permission with key '$key' is already registered",
            context: "While registering permission '$key'",
            suggestion: 'Ensure each permission has a unique key',
        );
    }
}
