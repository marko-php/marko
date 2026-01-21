<?php

declare(strict_types=1);

namespace Marko\Database\Schema;

use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;

/**
 * Registry of all discovered entity schemas.
 */
class SchemaRegistry
{
    /**
     * @var array<string, Table> Table name => Table schema
     */
    private array $tables = [];

    /**
     * @var array<string, class-string> Table name => Entity class
     */
    private array $entityClasses = [];

    /**
     * @var array<string, EntityMetadata> Table name => EntityMetadata
     */
    private array $metadata = [];

    public function __construct(
        private readonly EntityMetadataFactory $metadataFactory,
        private readonly SchemaBuilder $schemaBuilder,
    ) {}

    /**
     * Register an entity class in the registry.
     *
     * @param class-string $entityClass
     */
    public function registerEntity(
        string $entityClass,
    ): void {
        $metadata = $this->metadataFactory->parse($entityClass);
        $table = $this->schemaBuilder->build($metadata);

        $this->tables[$metadata->tableName] = $table;
        $this->entityClasses[$metadata->tableName] = $entityClass;
        $this->metadata[$metadata->tableName] = $metadata;
    }

    /**
     * Register multiple entity classes.
     *
     * @param array<class-string> $entityClasses
     */
    public function registerEntities(
        array $entityClasses,
    ): void {
        foreach ($entityClasses as $entityClass) {
            $this->registerEntity($entityClass);
        }
    }

    /**
     * Check if a table is registered.
     */
    public function hasTable(
        string $tableName,
    ): bool {
        return isset($this->tables[$tableName]);
    }

    /**
     * Get a table schema by name.
     */
    public function getTable(
        string $tableName,
    ): ?Table {
        return $this->tables[$tableName] ?? null;
    }

    /**
     * Get all registered tables.
     *
     * @return array<string, Table>
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * Get all table names.
     *
     * @return array<string>
     */
    public function getTableNames(): array
    {
        return array_keys($this->tables);
    }

    /**
     * Get the entity class for a table.
     *
     * @return class-string|null
     */
    public function getEntityClass(
        string $tableName,
    ): ?string {
        return $this->entityClasses[$tableName] ?? null;
    }

    /**
     * Get the entity metadata for a table.
     */
    public function getMetadata(
        string $tableName,
    ): ?EntityMetadata {
        return $this->metadata[$tableName] ?? null;
    }

    /**
     * Clear all registered tables.
     */
    public function clear(): void
    {
        $this->tables = [];
        $this->entityClasses = [];
        $this->metadata = [];
    }
}
