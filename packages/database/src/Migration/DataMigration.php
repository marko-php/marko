<?php

declare(strict_types=1);

namespace Marko\Database\Migration;

use Marko\Database\Connection\ConnectionInterface;

/**
 * Base class for data migrations.
 *
 * Data migrations insert, update, or delete required module data.
 * Unlike seeders, data migrations run in production and are tracked
 * alongside schema migrations.
 */
abstract class DataMigration extends Migration
{
    /**
     * Insert one or more rows into a table.
     *
     * @param ConnectionInterface $connection
     * @param string $table Table name
     * @param array<string, mixed>|array<array<string, mixed>> $data Single row or array of rows
     * @return int Number of affected rows
     */
    protected function insert(
        ConnectionInterface $connection,
        string $table,
        array $data,
    ): int {
        // Check if this is a single row or multiple rows
        $isMultiple = $this->isMultipleRows($data);

        if (!$isMultiple) {
            $data = [$data];
        }

        /** @var array<array<string, mixed>> $data */
        $columns = array_keys($data[0]);
        $columnList = implode(', ', $columns);

        $placeholders = [];
        $bindings = [];

        foreach ($data as $row) {
            $rowPlaceholders = array_fill(0, count($columns), '?');
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';

            foreach ($columns as $column) {
                $bindings[] = $row[$column];
            }
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $table,
            $columnList,
            implode(', ', $placeholders),
        );

        return $connection->execute($sql, $bindings);
    }

    /**
     * Update rows in a table.
     *
     * @param ConnectionInterface $connection
     * @param string $table Table name
     * @param array<string, mixed> $data Column-value pairs to update
     * @param array<string, mixed> $where Where conditions (ANDed together)
     * @return int Number of affected rows
     */
    protected function update(
        ConnectionInterface $connection,
        string $table,
        array $data,
        array $where,
    ): int {
        $setClauses = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $setClauses[] = $column . ' = ?';
            $bindings[] = $value;
        }

        $whereClauses = [];

        foreach ($where as $column => $value) {
            $whereClauses[] = $column . ' = ?';
            $bindings[] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $setClauses),
            implode(' AND ', $whereClauses),
        );

        return $connection->execute($sql, $bindings);
    }

    /**
     * Delete rows from a table.
     *
     * @param ConnectionInterface $connection
     * @param string $table Table name
     * @param array<string, mixed> $where Where conditions (ANDed together)
     * @return int Number of affected rows
     */
    protected function delete(
        ConnectionInterface $connection,
        string $table,
        array $where,
    ): int {
        $whereClauses = [];
        $bindings = [];

        foreach ($where as $column => $value) {
            $whereClauses[] = $column . ' = ?';
            $bindings[] = $value;
        }

        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $table,
            implode(' AND ', $whereClauses),
        );

        return $connection->execute($sql, $bindings);
    }

    /**
     * Check if the data represents multiple rows.
     *
     * @param array<mixed> $data
     */
    private function isMultipleRows(
        array $data,
    ): bool {
        if (empty($data)) {
            return false;
        }

        $firstValue = reset($data);

        return is_array($firstValue) && !empty($firstValue) && array_is_list($data);
    }
}
