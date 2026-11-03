<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Query;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Exceptions\InvalidJsonPathException;
use Marko\Database\Exceptions\QueryException;
use Marko\Database\MySql\Connection\MySqlServer;
use Marko\Database\MySql\Exceptions\ServerVersionException;
use Marko\Database\MySql\Sql\MySqlIdentifier;
use Marko\Database\Query\JsonPathParser;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\SqlQueryBuilder;

class MySqlQueryBuilder extends SqlQueryBuilder
{
    private readonly MySqlServer $server;

    /**
     * @param MySqlServer|null $server The shared server check; built from $connection when omitted
     * @param QueryBuilderFactoryInterface|null $queryBuilderFactory Creates the builders whereExists() callables
     *     receive; a MySqlQueryBuilderFactory on $connection and the same server check when omitted
     */
    public function __construct(
        ConnectionInterface $connection,
        ?MySqlServer $server = null,
        ?QueryBuilderFactoryInterface $queryBuilderFactory = null,
    ) {
        $this->server = $server ?? new MySqlServer($connection);

        parent::__construct(
            $connection,
            $queryBuilderFactory ?? new MySqlQueryBuilderFactory($connection, $this->server),
        );
    }

    /**
     * Quote a database identifier through the driver's one quoting rule, MySqlIdentifier.
     */
    protected function quoteIdentifier(
        string $identifier,
    ): string {
        return MySqlIdentifier::quote($identifier);
    }

    protected function hashStartsComment(): bool
    {
        return true;
    }

    protected function offsetOnlyLimitClause(): string
    {
        return ' LIMIT 18446744073709551615';
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

    /**
     * Compile a JSON path expression to MySQL SQL (JSON_EXTRACT or JSON_UNQUOTE).
     *
     * @throws InvalidJsonPathException
     */
    protected function compileJsonPath(string $expression): string
    {
        $path = JsonPathParser::parse($expression);
        $extract = sprintf(
            "JSON_EXTRACT(%s, '%s')",
            $this->quoteIdentifier($path->column),
            $this->buildMySqlJsonPath($path->segments),
        );

        if ($path->operator === '->>') {
            return sprintf('JSON_UNQUOTE(%s)', $extract);
        }

        return $extract;
    }

    /**
     * @throws InvalidJsonPathException
     */
    protected function compileJsonContains(string $path): string
    {
        if (JsonPathParser::isJsonPath($path)) {
            $parsed = JsonPathParser::parse($path);

            return sprintf(
                "JSON_CONTAINS(JSON_EXTRACT(%s, '%s'), ?)",
                $this->quoteIdentifier($parsed->column),
                $this->buildMySqlJsonPath($parsed->segments),
            );
        }

        return sprintf('JSON_CONTAINS(%s, ?)', $this->quoteIdentifier($path));
    }

    /**
     * @throws InvalidJsonPathException
     */
    protected function compileJsonPathExists(string $path): string
    {
        $parsed = JsonPathParser::parse($path);

        return sprintf(
            "JSON_CONTAINS_PATH(%s, 'one', '%s')",
            $this->quoteIdentifier($parsed->column),
            $this->buildMySqlJsonPath($parsed->segments),
        );
    }

    /**
     * MySQL resolves the conflict against whichever unique index or primary
     * key the row violates, so $uniqueBy only shapes the default update list.
     * VALUES(col) is used (not the 8.0.19 row alias) because MariaDB only
     * supports VALUES(). An empty update list compiles to a no-op assignment
     * on the first conflict column, never INSERT IGNORE, so unrelated errors
     * (bad data, other constraint violations) still surface.
     *
     * @param list<string> $uniqueBy
     * @param list<string> $updateColumns
     */
    protected function buildUpsertConflictClause(
        array $uniqueBy,
        array $updateColumns,
    ): string {
        if ($updateColumns === []) {
            $column = $this->quoteIdentifier($uniqueBy[0]);

            return "ON DUPLICATE KEY UPDATE $column = $column";
        }

        $assignments = array_map(
            fn (string $column): string => sprintf(
                '%s = VALUES(%s)',
                $this->quoteIdentifier($column),
                $this->quoteIdentifier($column),
            ),
            $updateColumns,
        );

        return 'ON DUPLICATE KEY UPDATE ' . implode(', ', $assignments);
    }

    /**
     * A shared lock compiles to LOCK IN SHARE MODE on both servers. With a
     * SKIP LOCKED / NOWAIT modifier the servers disagree: MySQL accepts the
     * modifier only after FOR SHARE (8.0+), MariaDB only after LOCK IN SHARE
     * MODE (NOWAIT 10.3+, SKIP LOCKED 10.6+). Only that case asks MySqlServer
     * which server this is, so other queries never read the server version.
     *
     * @throws QueryException|ServerVersionException
     */
    protected function compileLockClause(
        string $lockMode,
        string $modifier,
    ): string {
        if ($lockMode === 'update') {
            return ' FOR UPDATE' . $modifier;
        }

        if ($modifier === '') {
            return ' LOCK IN SHARE MODE';
        }

        return ($this->server->isMariaDb() ? ' LOCK IN SHARE MODE' : ' FOR SHARE') . $modifier;
    }

    /**
     * Build a MySQL JSON path string (e.g. "$.user.name") from segments.
     *
     * @param string[] $segments
     */
    private function buildMySqlJsonPath(array $segments): string
    {
        return '$.' . implode('.', $segments);
    }
}
