<?php

declare(strict_types=1);

namespace Marko\Session\Exceptions;

/**
 * Thrown when a value that cannot be stored safely in the session is flashed.
 */
class InvalidFlashDataException extends SessionException
{
    public static function forValue(
        string $path,
        mixed $value,
    ): self {
        $type = get_debug_type($value);

        return new self(
            message: "Cannot flash '$path': $type is not serialisable flash data",
            context: 'Flash data is stored in the session, which only holds null, scalars and arrays of them.',
            suggestion: 'Flash plain data instead: convert objects to arrays or scalars (for example a date to its ISO 8601 string) before calling put().',
        );
    }
}
