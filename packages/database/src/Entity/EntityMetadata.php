<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

/**
 * Holds parsed metadata from entity class attributes.
 *
 * @template T of Entity
 *
 * @property ?class-string $extends The parent entity class this entity extends (set on an extender).
 * @property array<class-string> $extenders The list of extender classes registered on this entity (set on a parent).
 */
readonly class EntityMetadata
{
    /**
     * @param class-string<T> $entityClass
     * @param array<string, PropertyMetadata> $properties Property name => metadata
     * @param array<ColumnMetadata> $columns
     * @param array<IndexMetadata> $indexes
     * @param array<string, RelationshipMetadata> $relationships Property name => metadata
     * @param ?class-string $extends Parent entity class this entity extends (set on an extender)
     * @param array<class-string> $extenders List of extender classes registered on this entity (set on a parent)
     */
    public function __construct(
        public string $entityClass,
        public string $tableName,
        public string $primaryKey,
        public array $properties = [],
        public array $columns = [],
        public array $indexes = [],
        public array $relationships = [],
        public ?string $extends = null,
        public array $extenders = [],
    ) {}

    /**
     * Returns true when this entity extends another entity.
     */
    public function isExtender(): bool
    {
        return $this->extends !== null;
    }

    /**
     * Returns true when this entity has been extended by other entities.
     */
    public function isExtended(): bool
    {
        return $this->extenders !== [];
    }

    /**
     * Returns a new instance with the given extenders list, leaving the original unchanged.
     *
     * @param array<class-string> $extenders
     */
    public function withExtenders(array $extenders): self
    {
        return new self(
            entityClass: $this->entityClass,
            tableName: $this->tableName,
            primaryKey: $this->primaryKey,
            properties: $this->properties,
            columns: $this->columns,
            indexes: $this->indexes,
            relationships: $this->relationships,
            extends: $this->extends,
            extenders: $extenders,
        );
    }

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
        return array_map(fn (PropertyMetadata $meta) => $meta->columnName, $this->properties);
    }

    /**
     * Get relationship metadata by property name.
     */
    public function getRelationship(string $name): ?RelationshipMetadata
    {
        return $this->relationships[$name] ?? null;
    }

    /**
     * Get all relationship metadata.
     *
     * @return array<string, RelationshipMetadata>
     */
    public function getRelationships(): array
    {
        return $this->relationships;
    }
}
