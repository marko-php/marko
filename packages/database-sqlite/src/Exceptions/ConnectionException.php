<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Exceptions;

use JsonException;
use Marko\Core\Exceptions\MarkoException;
use PDOException;

/**
 * Exception thrown when a SQLite connection cannot be set up.
 */
class ConnectionException extends MarkoException
{
    public static function connectionFailed(
        string $database,
        PDOException $previous,
    ): self {
        return new self(
            message: "Failed to open SQLite database '$database'",
            context: $previous->getMessage(),
            suggestion: 'Verify the path is readable and writable by the PHP process and that the file is a SQLite database',
            previous: $previous,
        );
    }

    public static function databaseFileNotFound(
        string $path,
    ): self {
        return new self(
            message: "SQLite database file '$path' does not exist",
            context: 'While connecting with sqlite.create_if_missing set to false',
            suggestion: "Create the file, fix 'database' in config/database.php, or set 'sqlite' => ['create_if_missing' => true]",
        );
    }

    public static function directoryCreationFailed(
        string $directory,
        ?string $reason,
    ): self {
        $reason ??= 'unknown reason';

        return new self(
            message: "Failed to create the directory '$directory' for the SQLite database file",
            context: "While creating the database directory: $reason",
            suggestion: 'Create the directory yourself or make its parent writable by the PHP process',
        );
    }

    public static function unsupportedVersion(
        string $found,
        string $minimum,
    ): self {
        return new self(
            message: "SQLite $found is not supported, $minimum or newer is required",
            context: 'While checking the SQLite library version on connect',
            suggestion: 'Upgrade the SQLite library that PHP\'s pdo_sqlite extension is built against',
        );
    }

    public static function unknownPragma(
        string $name,
        string $allowed,
    ): self {
        return new self(
            message: "Unknown SQLite pragma '$name' in sqlite.pragmas",
            context: 'While applying the configured pragmas on connect',
            suggestion: "Use one of: $allowed",
        );
    }

    public static function unsafePragmaValue(
        string $name,
        string $description,
    ): self {
        return new self(
            message: "Unsafe value for SQLite pragma '$name' ($description)",
            context: 'While applying the configured pragmas on connect',
            suggestion: 'Use an integer or a single bare word such as WAL, NORMAL, ON or MEMORY',
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
