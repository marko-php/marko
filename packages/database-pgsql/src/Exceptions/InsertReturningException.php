<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when a PostgreSQL INSERT ... RETURNING result is missing the expected primary-key column.
 */
class InsertReturningException extends MarkoException
{
    public static function missingPrimaryKeyColumn(
        string $table,
        string $primaryKey,
    ): self {
        return new self(
            message: "INSERT into '$table' returned no '$primaryKey' column in the RETURNING result",
            context: "Reading back the generated primary key '$primaryKey' after INSERT into '$table'",
            suggestion: "Ensure the primary key column '$primaryKey' exists in the '$table' table, or pass the correct primaryKey name to insert()",
        );
    }
}
