<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown for migration-related errors.
 */
class MigrationException extends MarkoException
{
    public static function migrationFailed(
        string $migrationName,
        string $error,
    ): self {
        return new self(
            message: "Migration '$migrationName' failed: $error",
            context: "While running migration '$migrationName'",
            suggestion: 'Fix the migration error and try again. You may need to manually revert partial changes.',
        );
    }

    public static function migrationNotFound(
        string $migrationName,
    ): self {
        return new self(
            message: "Migration file for '$migrationName' not found",
            context: "While looking for migration '$migrationName'",
            suggestion: 'Ensure the migration file exists in the database/migrations/ directory.',
        );
    }

    public static function invalidMigration(
        string $migrationName,
    ): self {
        return new self(
            message: "Migration '$migrationName' is invalid",
            context: "While loading migration '$migrationName'",
            suggestion: 'Migration files must return an instance of Migration class.',
        );
    }
}
