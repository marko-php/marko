<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use PDOException;

class ConnectionException extends MarkoException
{
    public static function connectionFailed(
        string $host,
        int $port,
        string $database,
        PDOException $previous,
    ): self {
        return new self(
            message: "Failed to connect to MySQL database '$database' at $host:$port",
            context: "While establishing database connection: {$previous->getMessage()}",
            suggestion: 'Verify MySQL is running and credentials are correct. Check host, port, database name, username, and password.',
            previous: $previous,
        );
    }
}
