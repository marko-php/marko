<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when an entity has no primary key declared.
 */
class MissingPrimaryKeyException extends MarkoException
{
    /**
     * @param class-string $entityClass
     */
    public static function noPrimaryKey(
        string $entityClass,
    ): self {
        return new self(
            message: "Entity '$entityClass' has no primary key declared",
            context: "Parsing entity '$entityClass' for database metadata",
            suggestion: "Add #[Column(primaryKey: true)] to a property in '$entityClass'",
        );
    }
}
