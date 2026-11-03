<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Query;

use Marko\Database\Query\JsonPathParser;
use Marko\Database\Query\SqlQueryBuilder;

/**
 * Test dialect for the shared SqlQueryBuilder base: [bracket] quoting, JSON_AT(...) paths and a CONFLICT clause.
 */
class BracketDialectQueryBuilder extends SqlQueryBuilder
{
    protected function quoteIdentifier(
        string $identifier,
    ): string {
        return '[' . $identifier . ']';
    }

    protected function insertAndReturnKey(
        string $sql,
        array $bindings,
        string $table,
        string $primaryKey,
    ): int {
        $this->connection->execute($sql, $bindings);

        return $this->connection->lastInsertId();
    }

    protected function compileJsonPath(string $expression): string
    {
        $path = JsonPathParser::parse($expression);

        return sprintf('JSON_AT(%s, %s)', $this->quoteIdentifier($path->column), implode('.', $path->segments));
    }

    protected function compileJsonContains(string $path): string
    {
        return 'CONTAINS(' . (JsonPathParser::isJsonPath($path) ? $this->compileJsonPath(
            $path,
        ) : $this->quoteIdentifier(
            $path,
        )) . ', ?)';
    }

    protected function compileJsonPathExists(string $path): string
    {
        return 'HAS_PATH(' . $this->compileJsonPath($path) . ')';
    }

    protected function buildUpsertConflictClause(
        array $uniqueBy,
        array $updateColumns,
    ): string {
        return 'CONFLICT(' . implode(',', array_map($this->quoteIdentifier(...), $uniqueBy)) . ') SET '
            . implode(',', array_map($this->quoteIdentifier(...), $updateColumns));
    }

    protected function compileLockClause(
        string $lockMode,
        string $modifier,
    ): string {
        return ' LOCK_' . strtoupper($lockMode) . $modifier;
    }
}
