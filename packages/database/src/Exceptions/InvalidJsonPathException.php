<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when a JSON path expression fails validation.
 */
class InvalidJsonPathException extends MarkoException
{
    public static function invalidSegment(
        string $segment,
        string $path,
    ): self {
        return new self(
            message: "Invalid JSON path segment '$segment' in path '$path': segments must be valid identifiers",
            context: "Validating JSON path expression '$path'",
            suggestion: 'Use only letters, digits, and underscores in each path segment, e.g. "data->user->name"',
        );
    }

    public static function invalidPath(
        string $path,
    ): self {
        return new self(
            message: "Invalid JSON path expression '$path': contains disallowed characters or SQL injection patterns",
            context: "Validating JSON path expression '$path'",
            suggestion: 'JSON path expressions must not contain semicolons or SQL comments. Use the form "column->key->nested_key"',
        );
    }
}
