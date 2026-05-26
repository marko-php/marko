<?php

declare(strict_types=1);

namespace Marko\Database\ReadWrite\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when read/write connection configuration is invalid.
 */
class ReadWriteConfigException extends MarkoException
{
    public static function missingWriteConnection(): self
    {
        return new self(
            message: "The 'connections.write' key is missing or not an array",
            context: 'While validating read/write connection configuration',
            suggestion: "Add a 'write' key under 'connections' in your database configuration with the primary connection details",
        );
    }

    public static function emptyReadConnections(): self
    {
        return new self(
            message: "The 'connections.read' key is missing or empty",
            context: 'While validating read/write connection configuration',
            suggestion: "Add at least one entry to 'connections.read' in your database configuration",
        );
    }

    public static function invalidWeight(mixed $weight): self
    {
        $type = gettype($weight);

        return new self(
            message: "Invalid weight value '$weight' (type: $type); weights must be positive integers greater than 0",
            context: 'While validating read connection weights for weighted strategy',
            suggestion: "Set each read connection's 'weight' to a positive integer (e.g., 1, 2, 3)",
        );
    }

    public static function unknownReadStrategy(string $strategy): self
    {
        return new self(
            message: "Unknown read_strategy '$strategy'; supported strategies are 'random' and 'weighted'",
            context: 'While validating read/write connection configuration',
            suggestion: "Set 'read_strategy' to 'random' or 'weighted' in your connections configuration",
        );
    }

    public static function missingConnectionsKey(): self
    {
        return new self(
            message: "The 'connections' key is missing from the database configuration",
            context: 'While validating read/write connection configuration',
            suggestion: "Add a 'connections' key to your database configuration with 'write' and 'read' sub-keys",
        );
    }
}
