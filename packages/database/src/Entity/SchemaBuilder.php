<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

use Marko\Database\Exceptions\EntityException;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\IdentifierName;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;

/**
 * Converts EntityMetadata to Schema value objects.
 */
class SchemaBuilder
{
    /**
     * Type synonyms the SQL generators accept, mapped to the name introspectors report, so a column declared as
     * `int` compares equal to the `integer` column the database holds.
     *
     * @var array<string, string>
     */
    private const array TYPE_SYNONYMS = [
        'int' => 'integer',
        'bool' => 'boolean',
        'string' => 'varchar',
    ];

    /**
     * Build a Table schema from EntityMetadata.
     *
     * @throws EntityException When a declared index or foreign key name is longer than 63 bytes, two of them resolve
     *                         to one name, a column has a foreign key both ways, or a references value is malformed
     */
    public function build(
        EntityMetadata $metadata,
    ): Table {
        $columns = array_map(
            fn (ColumnMetadata $col) => $this->buildColumn($col),
            $metadata->columns,
        );

        $indexes = array_map(
            fn (IndexMetadata $idx) => $this->buildIndex($idx, $metadata->entityClass, $metadata->tableName),
            $metadata->indexes,
        );

        $this->assertDistinctIndexNames($indexes, $metadata->entityClass, $metadata->tableName);

        $referenceForeignKeys = $this->buildForeignKeys(
            $metadata->entityClass,
            $metadata->tableName,
            $metadata->columns,
        );
        $declaredForeignKeys = array_map(
            fn (ForeignKeyMetadata $foreignKey) => $this->buildForeignKey(
                $foreignKey,
                $metadata->entityClass,
                $metadata->tableName,
            ),
            $metadata->foreignKeys,
        );

        $this->assertForeignKeysDistinct(
            $metadata->entityClass,
            $metadata->tableName,
            $declaredForeignKeys,
            $referenceForeignKeys,
        );

        $foreignKeys = [...$referenceForeignKeys, ...$declaredForeignKeys];

        return new Table(
            name: $metadata->tableName,
            columns: $columns,
            indexes: $indexes,
            foreignKeys: $foreignKeys,
            unmanagedIndexes: $metadata->unmanagedIndexes,
        );
    }

    /**
     * Build a Column schema from ColumnMetadata.
     */
    public function buildColumn(
        ColumnMetadata $metadata,
    ): Column {
        return new Column(
            name: $metadata->name,
            type: self::TYPE_SYNONYMS[strtolower($metadata->type)] ?? $metadata->type,
            length: $metadata->length,
            nullable: $metadata->nullable,
            default: $metadata->default,
            unique: $metadata->unique,
            primaryKey: $metadata->primaryKey,
            autoIncrement: $metadata->autoIncrement,
            references: $metadata->references,
            onDelete: $metadata->onDelete,
            onUpdate: $metadata->onUpdate,
        );
    }

    /**
     * Build an Index schema from IndexMetadata.
     *
     * A declared name is used literally and never shortened: one over 63 bytes is rejected, since PostgreSQL would
     * silently truncate it and MySQL would refuse it. Without a name, `<table>_<columns>_index` (or `_unique`) is
     * derived and shortened to fit.
     *
     * @param class-string $entityClass The entity that declares the index, named in the error
     * @param string $tableName The table the index belongs to, named in the error
     * @throws EntityException When the declared name is longer than 63 bytes
     */
    public function buildIndex(
        IndexMetadata $metadata,
        string $entityClass,
        string $tableName,
    ): Index {
        if (
            $metadata->name !== null
            && !IdentifierName::fits($metadata->name)
        ) {
            throw EntityException::indexNameTooLong(
                entityClass: $entityClass,
                tableName: $tableName,
                indexName: $metadata->name,
                byteLength: IdentifierName::byteLength($metadata->name),
            );
        }

        return new Index(
            name: $metadata->name ?? $this->deriveIndexName($metadata, $tableName),
            columns: $metadata->columns,
            type: $metadata->unique ? IndexType::Unique : IndexType::Btree,
            where: $metadata->where,
        );
    }

    /**
     * Build a ForeignKey schema from ForeignKeyMetadata.
     *
     * A declared name is used literally and never shortened: one over 63 bytes is rejected. Without a name,
     * `fk_<table>_<columns>` is derived and shortened to fit.
     *
     * @param class-string $entityClass The entity that declares the foreign key, named in the error
     * @param string $tableName The table the foreign key belongs to, named in the error
     * @throws EntityException When the declared name is longer than 63 bytes
     */
    public function buildForeignKey(
        ForeignKeyMetadata $metadata,
        string $entityClass,
        string $tableName,
    ): ForeignKey {
        if (
            $metadata->name !== null
            && !IdentifierName::fits($metadata->name)
        ) {
            throw EntityException::foreignKeyNameTooLong(
                entityClass: $entityClass,
                tableName: $tableName,
                foreignKeyName: $metadata->name,
                byteLength: IdentifierName::byteLength($metadata->name),
            );
        }

        return new ForeignKey(
            name: $metadata->name ?? IdentifierName::derive(
                $tableName . '_' . implode('_', $metadata->columns),
                prefix: 'fk_',
            ),
            columns: $metadata->columns,
            referencedTable: $metadata->referencedTable,
            referencedColumns: $metadata->referencedColumns,
            onDelete: $metadata->onDelete,
            onUpdate: $metadata->onUpdate,
        );
    }

    /**
     * Reject two indexes of one table that resolve to the same name.
     *
     * @param array<Index> $indexes
     * @param class-string $entityClass
     * @throws EntityException When two indexes share a name
     */
    public function assertDistinctIndexNames(
        array $indexes,
        string $entityClass,
        string $tableName,
    ): void {
        $seen = [];

        foreach ($indexes as $index) {
            if (isset($seen[$index->name])) {
                throw EntityException::duplicateIndexName($entityClass, $tableName, $index->name);
            }

            $seen[$index->name] = true;
        }
    }

    /**
     * Reject foreign keys of one table that clash.
     *
     * @param class-string $entityClass The entity whose declaration is being added, named in the error
     * @param array<ForeignKey> $declaredForeignKeys Built from #[ForeignKey]
     * @param array<ForeignKey> $referenceForeignKeys Derived from #[Column(references:)]
     * @throws EntityException When two foreign keys share a name, or one column has a foreign key both ways
     */
    public function assertForeignKeysDistinct(
        string $entityClass,
        string $tableName,
        array $declaredForeignKeys,
        array $referenceForeignKeys,
    ): void {
        $seen = [];

        foreach ([...$referenceForeignKeys, ...$declaredForeignKeys] as $foreignKey) {
            if (isset($seen[$foreignKey->name])) {
                throw EntityException::duplicateForeignKeyName($entityClass, $tableName, $foreignKey->name);
            }

            $seen[$foreignKey->name] = true;
        }

        $referenceColumns = array_map(
            fn (ForeignKey $foreignKey) => $foreignKey->columns[0],
            $referenceForeignKeys,
        );

        foreach ($declaredForeignKeys as $foreignKey) {
            if (
                count($foreignKey->columns) === 1
                && in_array($foreignKey->columns[0], $referenceColumns, true)
            ) {
                throw EntityException::foreignKeyDeclaredTwice($entityClass, $tableName, $foreignKey->columns[0]);
            }
        }
    }

    private function deriveIndexName(
        IndexMetadata $metadata,
        string $tableName,
    ): string {
        return IdentifierName::derive(
            $tableName . '_' . implode('_', $metadata->columns),
            suffix: $metadata->unique ? '_unique' : '_index',
        );
    }

    /**
     * Build ForeignKey schemas from columns with references, using an explicit table name.
     * Used when merging extender columns under the parent's table name.
     *
     * @param class-string $entityClass The entity that declares the columns, named in the error
     * @param array<ColumnMetadata> $columns
     * @return array<ForeignKey>
     * @throws EntityException When a references value is not in table.column format
     */
    public function buildForeignKeysForTable(
        string $entityClass,
        string $tableName,
        array $columns,
    ): array {
        return $this->buildForeignKeys($entityClass, $tableName, $columns);
    }

    /**
     * Build ForeignKey schemas from columns with references.
     *
     * @param class-string $entityClass The entity that declares the columns, named in the error
     * @param string $tableName The table name (for generating FK names)
     * @param array<ColumnMetadata> $columns
     * @return array<ForeignKey>
     * @throws EntityException When a references value is not in table.column format
     */
    private function buildForeignKeys(
        string $entityClass,
        string $tableName,
        array $columns,
    ): array {
        $foreignKeys = [];

        foreach ($columns as $column) {
            if ($column->references === null) {
                continue;
            }

            // Parse references format: "table.column"
            $parts = explode('.', $column->references);
            if (
                count($parts) !== 2
                || in_array('', $parts, true)
            ) {
                throw EntityException::malformedColumnReference($entityClass, $column->name, $column->references);
            }

            [$referencedTable, $referencedColumn] = $parts;

            // FK name: fk_{table}_{column}, shortened to 63 bytes when longer
            $fkName = IdentifierName::derive("{$tableName}_$column->name", prefix: 'fk_');

            $foreignKeys[] = new ForeignKey(
                name: $fkName,
                columns: [$column->name],
                referencedTable: $referencedTable,
                referencedColumns: [$referencedColumn],
                onDelete: $column->onDelete,
                onUpdate: $column->onUpdate,
            );
        }

        return $foreignKeys;
    }
}
