<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown for batch insert operation errors.
 */
class BatchInsertException extends MarkoException
{
    public static function emptyBatch(): self
    {
        return new self(
            message: 'Cannot insert an empty batch of entities',
            context: 'Calling insertBatch() with an empty array',
            suggestion: 'Ensure you pass at least one entity to insertBatch()',
        );
    }

    /**
     * @param class-string $expectedClass
     * @param class-string $actualClass
     */
    public static function heterogeneousBatch(
        string $expectedClass,
        string $actualClass,
        int $index,
    ): self {
        return new self(
            message: "All entities in the batch must be of the same class; expected '$expectedClass', got '$actualClass' at index $index",
            context: 'Calling insertBatch() with a mixed-type batch',
            suggestion: "Ensure all entities passed to insertBatch() are instances of '$expectedClass'",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function columnSetMismatch(
        string $entityClass,
        int $index,
    ): self {
        return new self(
            message: "Entity at index $index of class '$entityClass' has a different column set than the first entity in the batch",
            context: 'Calling insertBatch() where entities produce differing column lists',
            suggestion: 'Ensure all entities have the same columns initialised before passing them to insertBatch()',
        );
    }
}
