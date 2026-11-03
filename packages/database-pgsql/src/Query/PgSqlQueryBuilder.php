<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Query;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Exceptions\InvalidJsonPathException;
use Marko\Database\PgSql\Exceptions\InsertReturningException;
use Marko\Database\PgSql\Sql\PgSqlIdentifier;
use Marko\Database\Query\JsonPathParser;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\SqlQueryBuilder;

class PgSqlQueryBuilder extends SqlQueryBuilder
{
    /**
     * @param QueryBuilderFactoryInterface|null $queryBuilderFactory Creates the builders whereExists() callables
     *     receive; a PgSqlQueryBuilderFactory on $connection when omitted
     */
    public function __construct(
        ConnectionInterface $connection,
        ?QueryBuilderFactoryInterface $queryBuilderFactory = null,
    ) {
        parent::__construct($connection, $queryBuilderFactory ?? new PgSqlQueryBuilderFactory($connection));
    }

    /**
     * Quote a database identifier through the driver's one quoting rule, PgSqlIdentifier.
     */
    protected function quoteIdentifier(
        string $identifier,
    ): string {
        return PgSqlIdentifier::quote($identifier);
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
     * Compile a PostgreSQL JSON path traversal expression (e.g. "data->user->name").
     *
     * PostgreSQL uses -> / ->> operators natively, chained per segment.
     *
     * @throws InvalidJsonPathException
     */
    protected function compileJsonPath(string $expression): string
    {
        $path = JsonPathParser::parse($expression);
        $sql = $this->quoteIdentifier($path->column);
        $lastIndex = count($path->segments) - 1;

        foreach ($path->segments as $i => $segment) {
            $op = $i === $lastIndex ? $path->operator : '->';
            $sql .= $op . "'" . $segment . "'";
        }

        return $sql;
    }

    /**
     * @throws InvalidJsonPathException
     */
    protected function compileJsonContains(string $path): string
    {
        $columnSql = JsonPathParser::isJsonPath($path)
            ? $this->compileJsonPath($path)
            : $this->quoteIdentifier($path);

        return $columnSql . ' @> ?';
    }

    /**
     * @throws InvalidJsonPathException
     */
    protected function compileJsonPathExists(string $path): string
    {
        $parsed = JsonPathParser::parse($path);

        return sprintf(
            "jsonb_path_exists(%s, '%s')",
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
                '%s = EXCLUDED.%s',
                $this->quoteIdentifier($column),
                $this->quoteIdentifier($column),
            ),
            $updateColumns,
        );

        return "$conflict DO UPDATE SET " . implode(', ', $assignments);
    }

    protected function compileLockClause(
        string $lockMode,
        string $modifier,
    ): string {
        return ($lockMode === 'update' ? ' FOR UPDATE' : ' FOR SHARE') . $modifier;
    }
}
