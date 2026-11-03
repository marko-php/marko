<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Query;

use Marko\Core\Exceptions\UnsupportedFeature;
use Marko\Core\Exceptions\UnsupportedFeatureException;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Exceptions\InvalidJsonPathException;
use Marko\Database\Query\JsonPathParser;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\SqlQueryBuilder;
use Marko\Database\Sqlite\Exceptions\InsertReturningException;
use Marko\Database\Sqlite\Sql\SqliteIdentifier;

/**
 * The SQLite dialect of the shared query builder.
 *
 * Row locks (lockForUpdate, sharedLock, skipLocked, noWait) compile to no SQL: SQLite has no row locks, and a
 * transaction opened with BEGIN IMMEDIATE already holds the database's single writer lock, which keeps the
 * promise those methods make. The shared validation still runs, so a conflicting modifier, a modifier without
 * a lock, or a lock outside a transaction still fails loudly.
 */
class SqliteQueryBuilder extends SqlQueryBuilder
{
    /**
     * @param QueryBuilderFactoryInterface|null $queryBuilderFactory Creates the builders whereExists() callables
     *     receive; a SqliteQueryBuilderFactory on $connection when omitted
     */
    public function __construct(
        ConnectionInterface $connection,
        ?QueryBuilderFactoryInterface $queryBuilderFactory = null,
    ) {
        parent::__construct($connection, $queryBuilderFactory ?? new SqliteQueryBuilderFactory($connection));
    }

    /**
     * Quote a database identifier through the driver's one quoting rule, SqliteIdentifier.
     */
    protected function quoteIdentifier(
        string $identifier,
    ): string {
        return SqliteIdentifier::quote($identifier);
    }

    /**
     * @throws InsertReturningException
     */
    protected function insertAndReturnKey(
        string $sql,
        array $bindings,
        string $table,
        string $primaryKey,
    ): int {
        $result = $this->connection->query(
            $sql . ' RETURNING ' . $this->quoteIdentifier($primaryKey),
            $bindings,
        );

        if (!isset($result[0][$primaryKey])) {
            throw InsertReturningException::missingPrimaryKeyColumn($table, $primaryKey);
        }

        return (int) $result[0][$primaryKey];
    }

    /**
     * Reject object and nested-array needles, which json_each() cannot match.
     *
     * @throws UnsupportedFeatureException
     */
    public function whereJsonContains(
        string $path,
        mixed $value,
    ): static {
        $elements = is_array($value) ? $value : [$value];

        if (
            (is_array($value) && !array_is_list($value))
            || array_any($elements, fn (mixed $element): bool => is_array($element) || is_object($element))
        ) {
            throw UnsupportedFeatureException::fromUnsupportedFeature(new UnsupportedFeature(
                feature: 'whereJsonContains() with an object or nested-array needle',
                driver: 'SQLite',
                suggestion: 'Compare a single path with where(\'column->>key\', \'=\', $value), or use whereRaw() '
                    . 'with json_extract()',
                context: 'Compiling whereJsonContains() for SQLite, which has no JSON object containment operator',
                message: 'whereJsonContains() on the SQLite driver supports a scalar or a list of scalars, not an '
                    . 'object or nested array',
            ));
        }

        return parent::whereJsonContains($path, $value);
    }

    /**
     * Compile a SQLite JSON path traversal ("data"->'user'->>'name'), chaining -> per segment.
     *
     * @throws InvalidJsonPathException
     */
    protected function compileJsonPath(string $expression): string
    {
        $path = JsonPathParser::parse($expression);
        $sql = $this->quoteIdentifier($path->column);
        $lastIndex = count($path->segments) - 1;

        foreach ($path->segments as $index => $segment) {
            $operator = $index === $lastIndex ? $path->operator : '->';
            $sql .= $operator . "'" . $segment . "'";
        }

        return $sql;
    }

    /**
     * The bound needle is JSON text holding a scalar or a list of scalars; every needle element must be an
     * element of the column's array at the path (or the path's own scalar value, which json_each yields as
     * one row).
     *
     * @throws InvalidJsonPathException
     */
    protected function compileJsonContains(string $path): string
    {
        return sprintf(
            'NOT EXISTS (SELECT 1 FROM json_each(?) AS needle WHERE NOT EXISTS (SELECT 1 FROM json_each(%s) AS haystack WHERE haystack.value IS needle.value))',
            $this->jsonEachSource($path),
        );
    }

    /**
     * @throws InvalidJsonPathException
     */
    protected function compileJsonPathExists(string $path): string
    {
        $parsed = JsonPathParser::parse($path);

        return sprintf(
            "(json_type(%s, '%s') IS NOT NULL)",
            $this->quoteIdentifier($parsed->column),
            '$.' . implode('.', $parsed->segments),
        );
    }

    /**
     * @throws InvalidJsonPathException
     */
    private function jsonEachSource(string $path): string
    {
        if (!JsonPathParser::isJsonPath($path)) {
            return $this->quoteIdentifier($path);
        }

        $parsed = JsonPathParser::parse($path);

        return sprintf(
            "%s, '%s'",
            $this->quoteIdentifier($parsed->column),
            '$.' . implode('.', $parsed->segments),
        );
    }

    protected function buildUpsertConflictClause(
        array $uniqueBy,
        array $updateColumns,
    ): string {
        $conflict = sprintf(
            'ON CONFLICT (%s)',
            implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column), $uniqueBy)),
        );

        if ($updateColumns === []) {
            return "$conflict DO NOTHING";
        }

        $assignments = array_map(
            fn (string $column): string => sprintf(
                '%s = excluded.%s',
                $this->quoteIdentifier($column),
                $this->quoteIdentifier($column),
            ),
            $updateColumns,
        );

        return "$conflict DO UPDATE SET " . implode(', ', $assignments);
    }

    /**
     * SQLite has no row locks; see the class docblock.
     */
    protected function compileLockClause(
        string $lockMode,
        string $modifier,
    ): string {
        return '';
    }

    /**
     * SQLite rejects parenthesised compound SELECT operands, so each operand becomes a derived table.
     */
    protected function wrapUnionPart(string $sql): string
    {
        return "SELECT * FROM ($sql)";
    }

    /**
     * SQLite accepts OFFSET only after a LIMIT, and LIMIT -1 means no limit.
     */
    protected function offsetOnlyLimitClause(): string
    {
        return ' LIMIT -1';
    }
}
