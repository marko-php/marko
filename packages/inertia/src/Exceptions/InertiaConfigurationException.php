<?php

declare(strict_types=1);

namespace Marko\Inertia\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Throwable;

class InertiaConfigurationException extends MarkoException
{
    public static function missingOrInvalid(
        string $key,
        Throwable $previous,
    ): self {
        return new self(
            sprintf('Inertia configuration key "%s" is missing or invalid.', $key),
            $previous->getMessage(),
            'Publish or update config/inertia.php with the expected Inertia configuration keys.',
            previous: $previous,
        );
    }

    public static function invalidVersion(
        string $key,
        mixed $value,
    ): self {
        return new self(
            sprintf('Inertia configuration key "%s" must be a string, number, or null.', $key),
            sprintf('Expected string, int, float, or null; got %s.', get_debug_type($value)),
            'Update config/inertia.php with a scalar asset version, or set the value to null to disable versioning.',
        );
    }

    public static function empty(
        string $key,
        string $suggestion,
    ): self {
        return new self(
            sprintf('Inertia configuration key "%s" must not be empty.', $key),
            sprintf('The "%s" value resolved to an empty string.', $key),
            $suggestion,
        );
    }
}
