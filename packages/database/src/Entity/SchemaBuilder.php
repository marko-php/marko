<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;

/**
 * Converts EntityMetadata to Schema value objects.
 */
class SchemaBuilder
{
    /**
     * Build a Table schema from EntityMetadata.
     */
    public function build(
        EntityMetadata $metadata,
    ): Table {
        $columns = array_map(
            fn (ColumnMetadata $col) => $this->buildColumn($col),
            $metadata->columns,
        );

        $indexes = array_map(
            fn (IndexMetadata $idx) => $this->buildIndex($idx),
            $metadata->indexes,
        );

        $foreignKeys = $this->buildForeignKeys($metadata->tableName, $metadata->columns);

        return new Table(
            name: $metadata->tableName,
            columns: $columns,
            indexes: $indexes,
            foreignKeys: $foreignKeys,
        );
    }

    /**
     * Build a Column schema from ColumnMetadata.
     */
    private function buildColumn(
        ColumnMetadata $metadata,
    ): Column {
        return new Column(
            name: $metadata->name,
            type: $metadata->type,
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
     */
    private function buildIndex(
        IndexMetadata $metadata,
    ): Index {
        return new Index(
            name: $metadata->name,
            columns: $metadata->columns,
            type: $metadata->unique ? IndexType::Unique : IndexType::Btree,
        );
    }

    /**
     * Build ForeignKey schemas from columns with references.
     *
     * @param string $tableName The table name (for generating FK names)
     * @param array<ColumnMetadata> $columns
     * @return array<ForeignKey>
     */
    private function buildForeignKeys(
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
            if (count($parts) !== 2) {
                continue;
            }

            [$referencedTable, $referencedColumn] = $parts;

            // Generate FK name: fk_{table}_{column}
            $fkName = "fk_{$tableName}_$column->name";

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
