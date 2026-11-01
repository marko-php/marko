<?php

declare(strict_types=1);

namespace Marko\Validation\Database;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\PropertyMetadata;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Exceptions\MissingPrimaryKeyException;
use Marko\Validation\Contracts\RecordLookupInterface;
use Marko\Validation\Database\Exceptions\RecordLookupException;

/**
 * Answers the Exists and Unique rules with one `SELECT 1 FROM <table> WHERE ... LIMIT 1` probe.
 *
 * The table and every column come from the entity's metadata, so callers name properties, never columns. Values
 * go through the entity's casts (a BackedEnum becomes its backing value), and every identifier is quoted by the
 * connection, so the same SQL runs on PostgreSQL and MySQL/MariaDB. A value an int or float column cannot hold
 * (`"abc"`, `"1abc"`) matches nothing, rather than reaching MySQL's lenient string-to-number cast.
 */
class EntityRecordLookup implements RecordLookupInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly EntityMetadataFactory $entityMetadataFactory,
        private readonly EntityHydrator $entityHydrator,
    ) {}

    /**
     * @throws RecordLookupException|EntityException|MissingPrimaryKeyException
     */
    public function exists(
        string $entityClass,
        string $property,
        mixed $value,
        array $where = [],
    ): bool {
        return $this->matches($entityClass, $property, $value, null, $where, false);
    }

    /**
     * @throws RecordLookupException|EntityException|MissingPrimaryKeyException
     */
    public function isTaken(
        string $entityClass,
        string $property,
        mixed $value,
        mixed $ignoreId = null,
        array $where = [],
        bool $caseInsensitive = false,
    ): bool {
        return $this->matches($entityClass, $property, $value, $ignoreId, $where, $caseInsensitive);
    }

    /**
     * @param array<string, mixed> $where
     *
     * @throws RecordLookupException|EntityException|MissingPrimaryKeyException
     */
    private function matches(
        string $entityClass,
        string $property,
        mixed $value,
        mixed $ignoreId,
        array $where,
        bool $caseInsensitive,
    ): bool {
        $metadata = $this->metadata($entityClass);
        $target = $this->property($metadata, $property);
        $column = $this->connection->quoteIdentifier($target->columnName);

        if ($caseInsensitive && $target->type !== 'string') {
            throw RecordLookupException::caseInsensitiveNeedsText($entityClass, $property, $target->type);
        }

        $databaseValue = $this->databaseValue($metadata, $target, $value);

        if (!$this->canMatch($target, $databaseValue)) {
            return false;
        }

        $conditions = [$caseInsensitive ? "LOWER($column) = LOWER(?)" : "$column = ?"];
        $bindings = [$databaseValue];

        foreach ($where as $name => $expected) {
            $condition = $this->property($metadata, $name);
            $conditionColumn = $this->connection->quoteIdentifier($condition->columnName);

            if ($expected === null) {
                $conditions[] = "$conditionColumn IS NULL";
                continue;
            }

            $expected = $this->databaseValue($metadata, $condition, $expected);

            if (!$this->canMatch($condition, $expected)) {
                return false;
            }

            $conditions[] = "$conditionColumn = ?";
            $bindings[] = $expected;
        }

        if ($ignoreId !== null) {
            $primaryKey = $this->property($metadata, $metadata->primaryKey);
            $ignoreId = $this->databaseValue($metadata, $primaryKey, $ignoreId);

            // An id the primary key cannot hold belongs to no record, so there is nothing to leave out.
            if ($this->canMatch($primaryKey, $ignoreId)) {
                $conditions[] = $this->connection->quoteIdentifier($primaryKey->columnName) . ' <> ?';
                $bindings[] = $ignoreId;
            }
        }

        $sql = sprintf(
            'SELECT 1 FROM %s WHERE %s LIMIT 1',
            $this->connection->quoteIdentifier($metadata->tableName),
            implode(' AND ', $conditions),
        );

        return $this->connection->query($sql, $bindings) !== [];
    }

    /**
     * @throws RecordLookupException|EntityException|MissingPrimaryKeyException
     */
    private function metadata(
        string $entityClass,
    ): EntityMetadata {
        if (!class_exists($entityClass)) {
            throw RecordLookupException::unknownEntity($entityClass);
        }

        return $this->entityMetadataFactory->parse($entityClass);
    }

    /**
     * @throws RecordLookupException
     */
    private function property(
        EntityMetadata $metadata,
        string $name,
    ): PropertyMetadata {
        return $metadata->getProperty($name) ?? throw RecordLookupException::unknownProperty(
            $metadata->entityClass,
            $name,
            array_keys($metadata->properties),
        );
    }

    /**
     * The value as the column stores it: through the property's cast, with an integer string as an int.
     *
     * @throws RecordLookupException|EntityException
     */
    private function databaseValue(
        EntityMetadata $metadata,
        PropertyMetadata $property,
        mixed $value,
    ): mixed {
        if ($property->encrypted) {
            throw RecordLookupException::encryptedProperty($metadata->entityClass, $property->name);
        }

        $value = $this->entityHydrator->toDatabaseValue($value, $property);

        if ($property->type === 'int' && is_string($value) && preg_match('/^-?[0-9]+$/D', $value) === 1) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * Whether the column could hold the value at all; a numeric column never holds a non-numeric string.
     */
    private function canMatch(
        PropertyMetadata $property,
        mixed $value,
    ): bool {
        return match ($property->type) {
            'int' => is_int($value),
            'float' => is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)),
            default => true,
        };
    }
}
