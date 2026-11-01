<?php

declare(strict_types=1);

namespace Marko\Validation\Contracts;

/**
 * Looks up stored records for the Exists and Unique rules.
 *
 * Records are addressed by entity class and property name, never by table or column name: the driver maps
 * them through the entity's metadata. marko/validation ships no implementation; install marko/validation-database.
 */
interface RecordLookupInterface
{
    /**
     * Whether a record of $entityClass has $property equal to $value and matches every $where condition.
     *
     * @param class-string $entityClass
     * @param array<string, mixed> $where Property => value conditions; a null value matches NULL
     */
    public function exists(
        string $entityClass,
        string $property,
        mixed $value,
        array $where = [],
    ): bool;

    /**
     * Whether a record other than $ignoreId already has $property equal to $value and matches every $where
     * condition.
     *
     * @param class-string $entityClass
     * @param mixed $ignoreId Primary key of a record to leave out (the record being updated), or null for none
     * @param array<string, mixed> $where Property => value conditions; a null value matches NULL
     * @param bool $caseInsensitive Compare $value with LOWER() on both sides instead of the column's collation
     */
    public function isTaken(
        string $entityClass,
        string $property,
        mixed $value,
        mixed $ignoreId = null,
        array $where = [],
        bool $caseInsensitive = false,
    ): bool;
}
