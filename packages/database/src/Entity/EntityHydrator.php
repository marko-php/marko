<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

use BackedEnum;
use DateTimeImmutable;
use ReflectionClass;
use WeakMap;

/**
 * Hydrates entity objects from database rows and extracts entity data for persistence.
 */
class EntityHydrator
{
    /**
     * Stores original values for entities for dirty checking.
     *
     * @var WeakMap<Entity, array<string, mixed>>
     */
    private WeakMap $originalValues;

    public function __construct()
    {
        $this->originalValues = new WeakMap();
    }

    /**
     * Hydrate an entity from a database row.
     *
     * @template T of Entity
     * @param class-string<T> $entityClass
     * @param array<string, mixed> $row Database row with column names as keys
     * @return T
     */
    public function hydrate(
        string $entityClass,
        array $row,
        EntityMetadata $metadata,
    ): Entity {
        $reflection = new ReflectionClass($entityClass);
        $entity = $reflection->newInstanceWithoutConstructor();

        $columnToProperty = $metadata->getColumnToPropertyMap();
        $originalValues = [];

        foreach ($metadata->properties as $propName => $propMeta) {
            $columnName = $propMeta->columnName;

            if (!array_key_exists($columnName, $row)) {
                continue;
            }

            $dbValue = $row[$columnName];
            $phpValue = $this->convertToPhpType($dbValue, $propMeta);

            $property = $reflection->getProperty($propName);
            $property->setValue($entity, $phpValue);

            $originalValues[$propName] = $phpValue;
        }

        $this->originalValues[$entity] = $originalValues;

        return $entity;
    }

    /**
     * Extract entity data to a row array for persistence.
     *
     * @return array<string, mixed> Column name => value
     */
    public function extract(
        Entity $entity,
        EntityMetadata $metadata,
    ): array {
        $reflection = new ReflectionClass($entity);
        $row = [];

        foreach ($metadata->properties as $propName => $propMeta) {
            $property = $reflection->getProperty($propName);
            $value = $property->getValue($entity);

            $row[$propMeta->columnName] = $this->convertToDbValue($value, $propMeta);
        }

        return $row;
    }

    /**
     * Check if an entity is new (not yet persisted).
     */
    public function isNew(
        Entity $entity,
        EntityMetadata $metadata,
    ): bool {
        $pkProperty = $metadata->getPrimaryKeyProperty();

        if ($pkProperty === null) {
            return true;
        }

        $reflection = new ReflectionClass($entity);
        $property = $reflection->getProperty($pkProperty->name);

        if (!$property->isInitialized($entity)) {
            return true;
        }

        $value = $property->getValue($entity);

        return $value === null;
    }

    /**
     * Snapshot an entity's current property values into the originalValues WeakMap.
     *
     * Enables dirty-checking for entities that never passed through hydrate(),
     * such as freshly inserted entities. Idempotent — overwrites any prior snapshot.
     */
    public function registerOriginalValues(
        Entity $entity,
        EntityMetadata $metadata,
    ): void {
        $reflection = new ReflectionClass($entity);
        $values = [];

        foreach ($metadata->properties as $propName => $propMeta) {
            $property = $reflection->getProperty($propName);

            if (!$property->isInitialized($entity)) {
                continue;
            }

            $values[$propName] = $property->getValue($entity);
        }

        $this->originalValues[$entity] = $values;
    }

    /**
     * Get the original values for an entity (values when it was hydrated).
     *
     * @return array<string, mixed> Property name => original value
     */
    public function getOriginalValues(
        Entity $entity,
    ): array {
        return $this->originalValues[$entity] ?? [];
    }

    /**
     * Check if the entity has any changed properties.
     */
    public function isDirty(
        Entity $entity,
        EntityMetadata $metadata,
    ): bool {
        return count($this->getDirtyProperties($entity, $metadata)) > 0;
    }

    /**
     * Get the list of property names that have changed.
     *
     * @return array<string>
     */
    public function getDirtyProperties(
        Entity $entity,
        EntityMetadata $metadata,
    ): array {
        $originalValues = $this->originalValues[$entity] ?? [];
        $reflection = new ReflectionClass($entity);
        $dirty = [];

        foreach ($metadata->properties as $propName => $propMeta) {
            if (!array_key_exists($propName, $originalValues)) {
                continue;
            }

            $property = $reflection->getProperty($propName);

            if (!$property->isInitialized($entity)) {
                continue;
            }

            $currentValue = $property->getValue($entity);
            $originalValue = $originalValues[$propName];

            if (!$this->valuesEqual($currentValue, $originalValue)) {
                $dirty[] = $propName;
            }
        }

        return $dirty;
    }

    /**
     * Convert a database value to the appropriate PHP type.
     */
    private function convertToPhpType(
        mixed $value,
        PropertyMetadata $propMeta,
    ): mixed {
        if ($value === null) {
            return null;
        }

        // Handle enums
        if ($propMeta->enumClass !== null) {
            return $this->convertToEnum($value, $propMeta->enumClass);
        }

        // Handle DateTimeImmutable
        if ($propMeta->type === DateTimeImmutable::class) {
            return new DateTimeImmutable($value);
        }

        // Handle scalar types
        return match ($propMeta->type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => (string) $value,
            default => $value,
        };
    }

    /**
     * Convert a PHP value to a database-compatible value.
     */
    private function convertToDbValue(
        mixed $value,
        PropertyMetadata $propMeta,
    ): mixed {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value;
    }

    /**
     * Convert a string/int value to a BackedEnum instance.
     *
     * @param class-string<BackedEnum> $enumClass
     */
    private function convertToEnum(
        mixed $value,
        string $enumClass,
    ): BackedEnum {
        return $enumClass::from($value);
    }

    /**
     * Compare two values for equality, handling special cases.
     */
    private function valuesEqual(
        mixed $a,
        mixed $b,
    ): bool {
        if ($a instanceof DateTimeImmutable && $b instanceof DateTimeImmutable) {
            return $a->getTimestamp() === $b->getTimestamp();
        }

        if ($a instanceof BackedEnum && $b instanceof BackedEnum) {
            return $a === $b;
        }

        return $a === $b;
    }
}
