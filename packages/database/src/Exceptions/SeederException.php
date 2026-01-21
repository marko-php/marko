<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown for seeder-related errors.
 */
class SeederException extends MarkoException
{
    public static function blockedInProduction(): self
    {
        return new self(
            message: 'Seeders cannot be run in production environment',
            context: 'Attempting to run seeders',
            suggestion: 'Seeders are meant for development and testing only. Set MARKO_ENV or APP_ENV to development/local to run seeders.',
        );
    }

    public static function seederNotFound(
        string $name,
    ): self {
        return new self(
            message: "Seeder '$name' not found",
            context: "While looking up seeder '$name'",
            suggestion: 'Ensure the seeder class has the #[Seeder] attribute with the correct name.',
        );
    }
}
