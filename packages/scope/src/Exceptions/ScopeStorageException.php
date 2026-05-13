<?php

declare(strict_types=1);

namespace Marko\Scope\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when a storage operation fails due to missing scope columns or invalid state.
 */
class ScopeStorageException extends MarkoException
{
    public static function missingColumn(
        string $column,
        string $table,
    ): self
    {
        return new self(
            message: "Column '$column' is missing on table '$table' and is required for scope storage",
            context: "Saving scope data to table '$table'",
            suggestion: "Add the '$column' column to the '$table' table via a migration",
        );
    }
}
