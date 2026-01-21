<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when database configuration is invalid.
 */
class ConfigurationException extends MarkoException
{
    public static function configFileNotFound(
        string $path,
    ): self {
        return new self(
            message: "Database configuration file not found at: $path",
            context: 'While loading database configuration',
            suggestion: 'Create a config/database.php file with your database settings',
        );
    }

    public static function missingRequiredKey(
        string $key,
    ): self {
        return new self(
            message: "Required database configuration key '$key' is missing",
            context: 'While validating database configuration',
            suggestion: "Add the '$key' key to your config/database.php file",
        );
    }
}
