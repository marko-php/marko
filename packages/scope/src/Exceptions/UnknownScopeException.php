<?php

declare(strict_types=1);

namespace Marko\Scope\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when a scope path cannot be found for a given axis.
 */
class UnknownScopeException extends MarkoException
{
    public static function forAxisAndPath(
        string $axis,
        string $path,
    ): self
    {
        return new self(
            message: "Scope path '$path' does not exist on axis '$axis'",
            context: "Resolving scope '$path' on axis '$axis'",
            suggestion: "Ensure the scope '$path' is defined in the '$axis' axis configuration",
        );
    }
}
