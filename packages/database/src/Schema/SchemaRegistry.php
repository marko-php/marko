<?php

declare(strict_types=1);

namespace Marko\Database\Schema;

use Marko\Database\Entity\ColumnMetadata;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\IndexMetadata;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Exceptions\EntityException;

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
     * For extender entities, use registerEntities() instead.
     *
     * @param class-string $entityClass
     *
     * @throws EntityException
     */
    public function registerEntity(
        string $entityClass,
    ): void {
        $metadata = $this->metadataFactory->parse($entityClass);

        if ($metadata->isExtender()) {
            throw EntityException::extenderRegisteredWithoutParent(
                $entityClass,
                $metadata->extends,
            );
        }

        $table = $this->schemaBuilder->build($metadata);

        $this->tables[$metadata->tableName] = $table;
        $this->entityClasses[$metadata->tableName] = $entityClass;
        $this->metadata[$metadata->tableName] = $metadata;
    }

    /**
     * Register multiple entity classes using a two-pass merge.
     * Pass 1: parse all classes, separate parents from extenders, validate extender parents are present.
     * Pass 2: build each parent table and merge extender columns/indexes/foreign-keys into it.
     *
     * @param array<class-string> $entityClasses
     *
     * @throws EntityException
     */
    public function registerEntities(
        array $entityClasses,
    ): void {
        // Pass 1: parse all metadata, separate parents from extenders
        /** @var array<class-string, EntityMetadata> $allMetadata */
        $allMetadata = [];

        /** @var array<class-string> $parentClasses */
        $parentClasses = [];

        /** @var array<class-string, class-string> $extenderToParent extender => parent class */
        $extenderToParent = [];

        foreach ($entityClasses as $entityClass) {
            $metadata = $this->metadataFactory->parse($entityClass);
            $allMetadata[$entityClass] = $metadata;

            if ($metadata->isExtender()) {
                $extenderToParent[$entityClass] = $metadata->extends;
            } else {
                $parentClasses[] = $entityClass;
            }
        }

        // Validate: every extender's parent must appear in this registration set
        $inputSet = array_flip($entityClasses);

        foreach ($extenderToParent as $extenderClass => $parentClass) {
            if (!isset($inputSet[$parentClass])) {
                throw EntityException::extenderRegisteredWithoutParent($extenderClass, $parentClass);
            }
        }

        // Build parent class => [extender classes] map
        /** @var array<class-string, array<class-string>> $parentToExtenders */
        $parentToExtenders = [];

        foreach ($extenderToParent as $extenderClass => $parentClass) {
            $parentToExtenders[$parentClass][] = $extenderClass;
        }

        // Pass 2: build each parent table, merge extenders
        foreach ($parentClasses as $parentClass) {
            $parentMetadata = $allMetadata[$parentClass];
            $table = $this->schemaBuilder->build($parentMetadata);

            $extenderClasses = $parentToExtenders[$parentClass] ?? [];

            if ($extenderClasses !== []) {
                // Track existing column/index names for conflict detection
                /** @var array<string, class-string> $columnSources column name => owning class */
                $columnSources = [];

                foreach ($parentMetadata->columns as $col) {
                    $columnSources[$col->name] = $parentClass;
                }

                /** @var array<string, class-string> $indexSources index name => owning class */
                $indexSources = [];

                foreach ($parentMetadata->indexes as $idx) {
                    $indexSources[$idx->name] = $parentClass;
                }

                foreach ($extenderClasses as $extenderClass) {
                    $extenderMetadata = $allMetadata[$extenderClass];

                    // Merge columns with conflict detection
                    foreach ($extenderMetadata->columns as $col) {
                        if (isset($columnSources[$col->name])) {
                            throw EntityException::duplicateColumnInExtender(
                                $col->name,
                                $columnSources[$col->name],
                                $extenderClass,
                            );
                        }

                        $columnSources[$col->name] = $extenderClass;
                        $table = $table->withColumn($this->buildColumn($col));
                    }

                    // Merge indexes with conflict detection
                    foreach ($extenderMetadata->indexes as $idx) {
                        if (isset($indexSources[$idx->name])) {
                            throw EntityException::duplicateIndexInExtender(
                                $idx->name,
                                $indexSources[$idx->name],
                                $extenderClass,
                            );
                        }

                        $indexSources[$idx->name] = $extenderClass;
                        $table = $table->withIndex($this->buildIndex($idx));
                    }

                    // Merge foreign keys (use parent table name for FK name generation)
                    foreach ($this->schemaBuilder->buildForeignKeysForTable($parentMetadata->tableName, $extenderMetadata->columns) as $fk) {
                        $table = $table->withForeignKey($fk);
                    }
                }

                // Update factory cache with linked extenders
                $parentMetadata = $this->metadataFactory->linkExtenders($parentClass, $extenderClasses);
            }

            $this->tables[$parentMetadata->tableName] = $table;
            $this->entityClasses[$parentMetadata->tableName] = $parentClass;
            $this->metadata[$parentMetadata->tableName] = $parentMetadata;
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

    /**
     * Build a Schema Column from ColumnMetadata.
     */
    private function buildColumn(
        ColumnMetadata $col,
    ): Column {
        return new Column(
            name: $col->name,
            type: $col->type,
            length: $col->length,
            nullable: $col->nullable,
            default: $col->default,
            unique: $col->unique,
            primaryKey: $col->primaryKey,
            autoIncrement: $col->autoIncrement,
            references: $col->references,
            onDelete: $col->onDelete,
            onUpdate: $col->onUpdate,
        );
    }

    /**
     * Build a Schema Index from IndexMetadata.
     */
    private function buildIndex(
        IndexMetadata $idx,
    ): Index {
        return new Index(
            name: $idx->name,
            columns: $idx->columns,
            type: $idx->unique ? IndexType::Unique : IndexType::Btree,
        );
    }
}
