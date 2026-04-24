<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when a column expression fails validation.
 */
class InvalidColumnException extends MarkoException
{
    public static function invalidAlias(
        string $alias,
    ): self {
        return new self(
            message: "Invalid alias '$alias': aliases must start with a letter or underscore and contain only letters, digits, and underscores",
            context: "Validating SELECT column alias '$alias'",
            suggestion: 'Use an alias matching /^[a-zA-Z_][a-zA-Z0-9_]*$/, e.g. "my_alias"',
        );
    }

    public static function invalidColumn(
        string $column,
    ): self {
        return new self(
            message: "Invalid column expression '$column': contains disallowed characters or SQL injection patterns",
            context: "Validating SELECT column expression '$column'",
            suggestion: 'Use a plain identifier (e.g. "name"), a qualified identifier (e.g. "users.name"), or a known aggregate (e.g. "COUNT(*) as total")',
        );
    }
}
