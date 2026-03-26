<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown for database-related errors.
 */
class DatabaseException extends MarkoException
{
    private const array KNOWN_DRIVERS = [
        'mysql' => 'marko/database-mysql',
        'pgsql' => 'marko/database-pgsql',
    ];

    public static function noDriverInstalled(
        string $driver,
    ): self {
        $package = self::KNOWN_DRIVERS[$driver] ?? "marko/database-$driver";

        return new self(
            message: "The '$driver' database driver is not installed",
            context: "Attempting to use the '$driver' database driver",
            suggestion: "Install the driver package with: composer require $package",
        );
    }
}
