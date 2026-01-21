<?php

declare(strict_types=1);

namespace Marko\Database\Introspection;

use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\Table;

interface IntrospectorInterface
{
    /**
     * Get all table names in the database.
     *
     * @return array<string>
     */
    public function getTables(): array;

    /**
     * Get a table's schema by name.
     */
    public function getTable(string $name): ?Table;

    /**
     * Check if a table exists in the database.
     */
    public function tableExists(string $name): bool;

    /**
     * Get all columns for a table.
     *
     * @return array<Column>
     */
    public function getColumns(string $table): array;

    /**
     * Get all indexes for a table.
     *
     * @return array<Index>
     */
    public function getIndexes(string $table): array;

    /**
     * Get all foreign keys for a table.
     *
     * @return array<ForeignKey>
     */
    public function getForeignKeys(string $table): array;

    /**
     * Get the primary key column names for a table.
     *
     * @return array<string>
     */
    public function getPrimaryKey(string $table): array;
}
