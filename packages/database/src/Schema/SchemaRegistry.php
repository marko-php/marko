<?php

declare(strict_types=1);

namespace Marko\Database\Schema;

use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\ForeignKeyMetadata;
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

    /**
     * @var array<string, array<string, class-string>> Table name => index name => owning class
     */
    private array $indexOwners = [];

    /**
     * @var array<string, array<string, class-string>> Table name => foreign key name => owning class
     */
    private array $foreignKeyOwners = [];

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

        $this->claimNames(
            $table,
            $this->ownedBy($table->indexes, $entityClass),
            $this->ownedBy($table->foreignKeys, $entityClass),
        );

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

            /** @var array<string, class-string> $indexSources index name => owning class */
            $indexSources = $this->ownedBy($table->indexes, $parentClass);

            /** @var array<string, class-string> $foreignKeySources foreign key name => owning class */
            $foreignKeySources = $this->ownedBy($table->foreignKeys, $parentClass);

            /** @var array<string, class-string> $referenceSources extender references: foreign key name => owning class */
            $referenceSources = [];

            if ($extenderClasses !== []) {
                // Track existing column/index names for conflict detection
                /** @var array<string, class-string> $columnSources column name => owning class */
                $columnSources = [];

                foreach ($parentMetadata->columns as $col) {
                    $columnSources[$col->name] = $parentClass;
                }

                $referenceForeignKeys = $this->schemaBuilder->buildForeignKeysForTable(
                    $parentClass,
                    $parentMetadata->tableName,
                    $parentMetadata->columns,
                );
                $declaredForeignKeys = array_map(
                    fn (ForeignKeyMetadata $foreignKey) => $this->schemaBuilder->buildForeignKey(
                        $foreignKey,
                        $parentClass,
                        $parentMetadata->tableName,
                    ),
                    $parentMetadata->foreignKeys,
                );

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
                        $table = $table->withColumn($this->schemaBuilder->buildColumn($col));
                    }

                    // Merge indexes with conflict detection
                    foreach ($extenderMetadata->indexes as $idx) {
                        $index = $this->schemaBuilder->buildIndex($idx, $extenderClass, $parentMetadata->tableName);

                        if (isset($indexSources[$index->name])) {
                            throw EntityException::duplicateIndexInExtender(
                                $index->name,
                                $indexSources[$index->name],
                                $extenderClass,
                            );
                        }

                        $indexSources[$index->name] = $extenderClass;
                        $table = $table->withIndex($index);
                    }

                    // Merge foreign keys (use parent table name for FK name generation)
                    foreach ($this->schemaBuilder->buildForeignKeysForTable(
                        $extenderClass,
                        $parentMetadata->tableName,
                        $extenderMetadata->columns,
                    ) as $fk) {
                        $referenceForeignKeys[] = $fk;
                        $referenceSources[$fk->name] ??= $extenderClass;
                        $table = $table->withForeignKey($fk);
                    }

                    if ($extenderMetadata->unmanagedIndexes !== []) {
                        $table = $table->withUnmanagedIndexes($extenderMetadata->unmanagedIndexes);
                    }
                }

                $mergedMetadata = [$parentMetadata, ...array_map(
                    fn (string $class) => $allMetadata[$class],
                    $extenderClasses,
                )];

                // Check declared indexes once every extender's columns are known, so the result never
                // depends on the order extenders are discovered in
                foreach ($extenderClasses as $extenderClass) {
                    foreach ($allMetadata[$extenderClass]->indexes as $index) {
                        $this->assertColumnsExist(
                            $index->columns,
                            '#[Index]',
                            $index->name !== null ? "'$index->name'" : 'on (' . implode(', ', $index->columns) . ')',
                            $extenderClass,
                            $columnSources,
                            $mergedMetadata,
                        );
                    }
                }

                // Merge declared foreign keys once every extender's columns are known, so the result never
                // depends on the order extenders are discovered in
                foreach ($extenderClasses as $extenderClass) {
                    foreach ($allMetadata[$extenderClass]->foreignKeys as $foreignKey) {
                        $this->assertColumnsExist(
                            $foreignKey->columns,
                            '#[ForeignKey]',
                            $foreignKey->name !== null
                                ? "'$foreignKey->name'"
                                : 'on (' . implode(', ', $foreignKey->columns) . ')',
                            $extenderClass,
                            $columnSources,
                            $mergedMetadata,
                        );

                        $built = $this->schemaBuilder->buildForeignKey(
                            $foreignKey,
                            $extenderClass,
                            $parentMetadata->tableName,
                        );

                        if (isset($foreignKeySources[$built->name])) {
                            throw EntityException::duplicateForeignKeyInExtender(
                                $built->name,
                                $foreignKeySources[$built->name],
                                $extenderClass,
                            );
                        }

                        $foreignKeySources[$built->name] = $extenderClass;
                        $declaredForeignKeys[] = $built;
                        $table = $table->withForeignKey($built);
                    }
                }

                $this->schemaBuilder->assertForeignKeysDistinct(
                    $parentClass,
                    $parentMetadata->tableName,
                    $declaredForeignKeys,
                    $referenceForeignKeys,
                );

                // Update factory cache with linked extenders
                $parentMetadata = $this->metadataFactory->linkExtenders($parentClass, $extenderClasses);
            }

            $this->claimNames($table, $indexSources, $foreignKeySources + $referenceSources);

            $this->tables[$parentMetadata->tableName] = $table;
            $this->entityClasses[$parentMetadata->tableName] = $parentClass;
            $this->metadata[$parentMetadata->tableName] = $parentMetadata;
        }
    }

    /**
     * @param array<Index|ForeignKey> $named
     * @param class-string $class
     * @return array<string, class-string> Name => owning class
     */
    private function ownedBy(
        array $named,
        string $class,
    ): array {
        return array_fill_keys(
            array_map(fn (Index|ForeignKey $item) => $item->name, $named),
            $class,
        );
    }

    /**
     * Record a table's index and foreign key names, rejecting any that another table already uses, or that a
     * foreign key and an index of this table share while covering different columns.
     *
     * @param array<string, class-string> $indexSources index name => owning class
     * @param array<string, class-string> $foreignKeySources foreign key name => owning class
     *
     * @throws EntityException
     */
    private function claimNames(
        Table $table,
        array $indexSources,
        array $foreignKeySources,
    ): void {
        $this->assertNoNameSharedAcrossTables('Index', $table->name, $indexSources, $this->indexOwners);
        $this->assertNoNameSharedAcrossTables(
            'Foreign key',
            $table->name,
            $foreignKeySources,
            $this->foreignKeyOwners,
        );

        foreach ($table->foreignKeys as $foreignKey) {
            $index = array_find($table->indexes, fn (Index $index) => $index->name === $foreignKey->name);

            if (
                $index !== null
                && $index->columns !== $foreignKey->columns
            ) {
                throw EntityException::foreignKeyAndIndexShareName(
                    $table->name,
                    $foreignKey->name,
                    $foreignKeySources[$foreignKey->name],
                    $indexSources[$index->name],
                );
            }
        }

        $this->indexOwners[$table->name] = $indexSources;
        $this->foreignKeyOwners[$table->name] = $foreignKeySources;
    }

    /**
     * @param array<string, class-string> $sources name => owning class
     * @param array<string, array<string, class-string>> $registered Table name => name => owning class
     *
     * @throws EntityException
     */
    private function assertNoNameSharedAcrossTables(
        string $kind,
        string $tableName,
        array $sources,
        array $registered,
    ): void {
        foreach ($registered as $otherTable => $otherSources) {
            if ($otherTable === $tableName) {
                continue;
            }

            foreach ($sources as $name => $class) {
                if (isset($otherSources[$name])) {
                    throw EntityException::nameSharedAcrossTables(
                        $kind,
                        $name,
                        $otherTable,
                        $otherSources[$name],
                        $tableName,
                        $class,
                    );
                }
            }
        }
    }

    /**
     * Reject an extender attribute naming a column the merged parent and extenders do not have.
     *
     * @param array<string> $attributeColumns
     * @param class-string $extenderClass
     * @param array<string, class-string> $columnSources Merged column name => owning class
     * @param array<EntityMetadata> $mergedMetadata The parent and all its extenders, searched for a property name to suggest
     *
     * @throws EntityException
     */
    private function assertColumnsExist(
        array $attributeColumns,
        string $attribute,
        string $declarationName,
        string $extenderClass,
        array $columnSources,
        array $mergedMetadata,
    ): void {
        foreach ($attributeColumns as $column) {
            if (isset($columnSources[$column])) {
                continue;
            }

            $declaringMetadata = array_find(
                $mergedMetadata,
                fn (EntityMetadata $metadata) => isset($metadata->properties[$column]),
            );

            throw EntityException::unknownAttributeColumn(
                $extenderClass,
                $attribute,
                $declarationName,
                $column,
                $declaringMetadata?->properties[$column]->columnName,
            );
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
        $this->indexOwners = [];
        $this->foreignKeyOwners = [];
    }
}
