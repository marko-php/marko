<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Exceptions;

use JsonException;
use Marko\Core\Exceptions\MarkoException;
use PDOException;

/**
 * Exception thrown when a PostgreSQL connection fails.
 */
class ConnectionException extends MarkoException
{
    public static function connectionFailed(
        string $host,
        int $port,
        string $database,
        PDOException $previous,
    ): self {
        return new self(
            message: "Failed to connect to PostgreSQL database '$database' on $host:$port",
            context: $previous->getMessage(),
            suggestion: 'Verify the database server is running, credentials are correct, and the database exists',
            previous: $previous,
        );
    }

    public static function invalidArrayBinding(
        int|string $parameter,
        JsonException $previous,
    ): self {
        return new self(
            message: "Failed to JSON-encode array bound to parameter '$parameter'",
            context: $previous->getMessage(),
            suggestion: 'Ensure array values are JSON-encodable (no resources, recursive references, NAN, or INF)',
            previous: $previous,
        );
    }
}
