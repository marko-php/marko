<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

/**
 * Holds parsed metadata from entity class attributes.
 *
 * @template T of Entity
 */
readonly class EntityMetadata
{
    /**
     * @param class-string<T> $entityClass
     * @param array<string, PropertyMetadata> $properties Property name => metadata
     * @param array<ColumnMetadata> $columns
     * @param array<IndexMetadata> $indexes
     */
    public function __construct(
        public string $entityClass,
        public string $tableName,
        public string $primaryKey = 'id',
        public array $properties = [],
        public array $columns = [],
        public array $indexes = [],
    ) {}

    /**
     * Get property metadata by property name.
     */
    public function getProperty(
        string $name,
    ): ?PropertyMetadata {
        return $this->properties[$name] ?? null;
    }

    /**
     * Get the primary key property metadata.
     */
    public function getPrimaryKeyProperty(): ?PropertyMetadata
    {
        return $this->properties[$this->primaryKey] ?? null;
    }

    /**
     * Get all column names mapped to property names.
     *
     * @return array<string, string> Column name => property name
     */
    public function getColumnToPropertyMap(): array
    {
        $map = [];
        foreach ($this->properties as $propName => $meta) {
            $map[$meta->columnName] = $propName;
        }

        return $map;
    }

    /**
     * Get all property names mapped to column names.
     *
     * @return array<string, string> Property name => column name
     */
    public function getPropertyToColumnMap(): array
    {
        return array_map(fn ($meta) => $meta->columnName, $this->properties);
    }
}
