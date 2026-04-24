<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Query;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\Exceptions\UnionShapeMismatchException;
use Marko\Database\Query\IdentifierValidator;
use Marko\Database\Query\JsonPathParser;
use Marko\Database\Query\QueryBuilderInterface;

class MySqlQueryBuilder implements QueryBuilderInterface
{
    private string $table = '';

    /**
     * @var array<string>
     */
    private array $columns = ['*'];

    /**
     * @var array<array{column: string, operator: string, value: mixed, boolean: string}>
     */
    private array $wheres = [];

    /**
     * @var array<array{column: string, values: array}>
     */
    private array $whereIns = [];

    /**
     * @var array<array{path: string, value: mixed}>
     */
    private array $whereJsonContains = [];

    /**
     * @var array<array{path: string, negate: bool}>
     */
    private array $whereJsonPaths = [];

    /**
     * @var array<string>
     */
    private array $whereNulls = [];

    /**
     * @var array<string>
     */
    private array $whereNotNulls = [];

    /**
     * @var array<array{type: string, table: string, first: string, operator: string, second: string}>
     */
    private array $joins = [];

    /**
     * @var array<string>
     */
    private array $groups = [];

    /**
     * @var array{expression: string, bindings: array}|null
     */
    private ?array $havingClause = null;

    /**
     * @var array<array{column: string, direction: string}>
     */
    private array $orders = [];

    private bool $distinct = false;

    /**
     * @var array<array{type: string, builder: QueryBuilderInterface}>
     */
    private array $unions = [];

    private ?int $limitValue = null;

    private ?int $offsetValue = null;

    /**
     * @var array<int, mixed>
     */
    private array $bindings = [];

    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {}

    public function table(
        string $table,
    ): static {
        $this->table = $table;

        return $this;
    }

    public function select(
        string ...$columns,
    ): static {
        $this->columns = $columns;

        return $this;
    }

    public function distinct(): static
    {
        $this->distinct = true;

        return $this;
    }

    public function union(
        QueryBuilderInterface $other,
    ): static {
        $leftCount = $this->getColumnCount();
        $rightCount = $other->getColumnCount();

        if ($leftCount !== $rightCount) {
            throw UnionShapeMismatchException::columnCountMismatch($leftCount, $rightCount);
        }

        $this->unions[] = ['type' => 'UNION', 'builder' => $other];

        return $this;
    }

    public function unionAll(
        QueryBuilderInterface $other,
    ): static {
        $leftCount = $this->getColumnCount();
        $rightCount = $other->getColumnCount();

        if ($leftCount !== $rightCount) {
            throw UnionShapeMismatchException::columnCountMismatch($leftCount, $rightCount);
        }

        $this->unions[] = ['type' => 'UNION ALL', 'builder' => $other];

        return $this;
    }

    public function getColumnCount(): int
    {
        return count($this->columns);
    }

    public function compileSubquery(
        array &$bindings,
    ): string {
        $savedBindings = $this->bindings;
        $this->bindings = [];
        $sql = $this->buildSelectSql();
        $bindings = array_merge($bindings, $this->bindings);
        $this->bindings = $savedBindings;

        return $sql;
    }

    public function where(
        string $column,
        string $operator,
        mixed $value,
    ): static {
        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function whereIn(
        string $column,
        array $values,
    ): static {
        $this->whereIns[] = [
            'column' => $column,
            'values' => $values,
        ];

        return $this;
    }

    public function whereNull(
        string $column,
    ): static {
        $this->whereNulls[] = $column;

        return $this;
    }

    public function whereNotNull(
        string $column,
    ): static {
        $this->whereNotNulls[] = $column;

        return $this;
    }

    public function whereJsonContains(
        string $path,
        mixed $value,
    ): static {
        $this->whereJsonContains[] = [
            'path' => $path,
            'value' => $value,
        ];

        return $this;
    }

    public function whereJsonExists(
        string $path,
    ): static {
        $this->whereJsonPaths[] = [
            'path' => $path,
            'negate' => false,
        ];

        return $this;
    }

    public function whereJsonMissing(
        string $path,
    ): static {
        $this->whereJsonPaths[] = [
            'path' => $path,
            'negate' => true,
        ];

        return $this;
    }

    public function orWhere(
        string $column,
        string $operator,
        mixed $value,
    ): static {
        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR',
        ];

        return $this;
    }

    public function groupBy(
        string ...$columns,
    ): static {
        foreach ($columns as $column) {
            if (!IdentifierValidator::isValidIdentifier($column) && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
                throw InvalidColumnException::invalidColumn($column);
            }
        }

        $this->groups = array_merge($this->groups, $columns);

        return $this;
    }

    public function having(
        string $expression,
        array $bindings = [],
    ): static {
        if (
            str_contains($expression, ';')
            || str_contains($expression, '--')
            || str_contains($expression, '/*')
            || str_contains($expression, '*/')
        ) {
            throw InvalidColumnException::invalidColumn($expression);
        }

        $this->havingClause = [
            'expression' => $expression,
            'bindings' => $bindings,
        ];

        return $this;
    }

    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static {
        $this->joins[] = [
            'type' => 'INNER',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    public function leftJoin(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static {
        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    public function rightJoin(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static {
        $this->joins[] = [
            'type' => 'RIGHT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    public function orderBy(
        string $column,
        string $direction = 'ASC',
    ): static {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }

        $this->orders[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    public function limit(
        int $limit,
    ): static {
        $this->limitValue = $limit;

        return $this;
    }

    public function offset(
        int $offset,
    ): static {
        $this->offsetValue = $offset;

        return $this;
    }

    public function get(): array
    {
        if (!empty($this->unions)) {
            return $this->executeUnion();
        }

        $this->bindings = [];
        $sql = $this->buildSelectSql();

        return $this->connection->query($sql, $this->bindings);
    }

    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();

        return $results[0] ?? null;
    }

    public function insert(
        array $data,
    ): int {
        $this->bindings = [];

        $columns = array_keys($data);
        $quotedColumns = array_map(fn ($col) => $this->quoteIdentifier($col), $columns);
        $placeholders = array_fill(0, count($data), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($this->table),
            implode(', ', $quotedColumns),
            implode(', ', $placeholders),
        );

        $this->bindings = array_values($data);
        $this->connection->execute($sql, $this->bindings);

        return $this->connection->lastInsertId();
    }

    public function update(
        array $data,
    ): int {
        $this->bindings = [];

        $sets = [];
        foreach ($data as $column => $value) {
            $sets[] = sprintf('%s = ?', $this->quoteIdentifier($column));
            $this->bindings[] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s',
            $this->quoteIdentifier($this->table),
            implode(', ', $sets),
        );

        $sql .= $this->buildWhereClause();

        return $this->connection->execute($sql, $this->bindings);
    }

    public function delete(): int
    {
        $this->bindings = [];

        $sql = sprintf(
            'DELETE FROM %s',
            $this->quoteIdentifier($this->table),
        );

        $sql .= $this->buildWhereClause();

        return $this->connection->execute($sql, $this->bindings);
    }

    public function count(?string $column = null): int
    {
        $expr = $column !== null
            ? 'COUNT(' . $this->quoteIdentifier($column) . ') as aggregate'
            : 'COUNT(*) as aggregate';

        return (int) $this->runAggregate($expr);
    }

    public function min(string $column): int|float|null
    {
        if (!IdentifierValidator::isValidIdentifier($column)) {
            throw InvalidColumnException::invalidColumn($column);
        }

        return $this->runAggregate('MIN(' . $this->quoteIdentifier($column) . ') as aggregate');
    }

    public function max(string $column): int|float|null
    {
        if (!IdentifierValidator::isValidIdentifier($column)) {
            throw InvalidColumnException::invalidColumn($column);
        }

        return $this->runAggregate('MAX(' . $this->quoteIdentifier($column) . ') as aggregate');
    }

    public function sum(string $column): int|float|null
    {
        if (!IdentifierValidator::isValidIdentifier($column)) {
            throw InvalidColumnException::invalidColumn($column);
        }

        return $this->runAggregate('SUM(' . $this->quoteIdentifier($column) . ') as aggregate');
    }

    public function avg(string $column): int|float|null
    {
        if (!IdentifierValidator::isValidIdentifier($column)) {
            throw InvalidColumnException::invalidColumn($column);
        }

        return $this->runAggregate('AVG(' . $this->quoteIdentifier($column) . ') as aggregate');
    }

    public function raw(
        string $sql,
        array $bindings = [],
    ): array {
        return $this->connection->query($sql, $bindings);
    }

    /**
     * Quote a database identifier with backticks (MySQL style).
     *
     * @param string $identifier The identifier to quote
     * @return string The quoted identifier
     */
    protected function quoteIdentifier(
        string $identifier,
    ): string {
        // Handle table.column format
        if (str_contains($identifier, '.')) {
            $parts = explode('.', $identifier);

            return implode('.', array_map(
                fn ($part) => '`' . $part . '`',
                $parts,
            ));
        }

        return '`' . $identifier . '`';
    }

    /**
     * Execute an aggregate query and return the raw value or null.
     *
     * Reuses buildWhereClause() so any WHERE conditions are respected.
     *
     * @return int|float|null
     */
    private function runAggregate(string $aggregateExpr): int|float|null
    {
        $this->bindings = [];

        $sql = sprintf(
            'SELECT %s FROM %s',
            $aggregateExpr,
            $this->quoteIdentifier($this->table),
        );

        $sql .= $this->buildWhereClause();

        $result = $this->connection->query($sql, $this->bindings);
        $value = $result[0]['aggregate'] ?? null;

        if ($value === null) {
            return null;
        }

        return is_int($value + 0) ? (int) $value : (float) $value;
    }

    private function executeUnion(): array
    {
        $bindings = [];

        // Build left subquery without outer ORDER BY / LIMIT
        $savedOrders = $this->orders;
        $savedLimit = $this->limitValue;
        $savedOffset = $this->offsetValue;
        $this->orders = [];
        $this->limitValue = null;
        $this->offsetValue = null;
        $this->bindings = [];

        $leftSql = $this->buildSelectSql();
        $bindings = array_merge($bindings, $this->bindings);

        $this->orders = $savedOrders;
        $this->limitValue = $savedLimit;
        $this->offsetValue = $savedOffset;

        $parts = ['(' . $leftSql . ')'];

        foreach ($this->unions as $union) {
            $rightBindings = [];
            $rightSql = $union['builder']->compileSubquery($rightBindings);
            $bindings = array_merge($bindings, $rightBindings);
            $parts[] = $union['type'];
            $parts[] = '(' . $rightSql . ')';
        }

        $sql = implode(' ', $parts);
        $sql .= $this->buildOrderByClause();
        $sql .= $this->buildLimitOffsetClause();

        return $this->connection->query($sql, $bindings);
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

    /**
     * Compile a JSON path expression to MySQL SQL (JSON_EXTRACT or JSON_UNQUOTE).
     */
    private function compileJsonExtract(string $expression): string
    {
        $path = JsonPathParser::parse($expression);
        $jsonPath = $this->buildMySqlJsonPath($path->segments);
        $extract = sprintf(
            "JSON_EXTRACT(%s, '%s')",
            $this->quoteIdentifier($path->column),
            $jsonPath,
        );

        if ($path->operator === '->>') {
            return sprintf('JSON_UNQUOTE(%s)', $extract);
        }

        return $extract;
    }

    /**
     * Compile a single SELECT column expression into quoted SQL.
     *
     * @throws InvalidColumnException When the expression is invalid
     */
    private function compileColumnExpression(
        string $expression,
    ): string {
        // Split off alias first (AS keyword)
        $aliasParts = preg_split('/\s+[Aa][Ss]\s+/', $expression, 2);
        $colPart = trim($aliasParts[0] ?? $expression);
        $alias = isset($aliasParts[1]) ? trim($aliasParts[1]) : null;

        // JSON path in SELECT
        if (JsonPathParser::isJsonPath($colPart)) {
            $compiledColumn = $this->compileJsonExtract($colPart);

            if ($alias !== null) {
                if (!IdentifierValidator::isValidIdentifier($alias)) {
                    throw InvalidColumnException::invalidAlias($alias);
                }

                return $compiledColumn . ' AS ' . $this->quoteIdentifier($alias);
            }

            return $compiledColumn;
        }

        $parsed = IdentifierValidator::parseSelectExpression($expression);
        $column = $parsed['column'];
        $alias = $parsed['alias'];

        $compiledColumn = preg_match('/^(COUNT|SUM|MIN|MAX|AVG)\(/i', $column)
            ? $column
            : $this->quoteIdentifier($column);

        if ($alias !== null) {
            return $compiledColumn . ' AS ' . $this->quoteIdentifier($alias);
        }

        return $compiledColumn;
    }

    private function buildSelectSql(): string
    {
        $quotedColumns = array_map(function (string $col): string {
            if ($col === '*') {
                return '*';
            }

            return $this->compileColumnExpression($col);
        }, $this->columns);

        $keyword = $this->distinct ? 'SELECT DISTINCT' : 'SELECT';

        $sql = sprintf(
            '%s %s FROM %s',
            $keyword,
            implode(', ', $quotedColumns),
            $this->quoteIdentifier($this->table),
        );

        $sql .= $this->buildJoinClause();
        $sql .= $this->buildWhereClause();
        $sql .= $this->buildGroupByClause();
        $sql .= $this->buildHavingClause();
        $sql .= $this->buildOrderByClause();
        $sql .= $this->buildLimitOffsetClause();

        return $sql;
    }

    private function buildGroupByClause(): string
    {
        if (empty($this->groups)) {
            return '';
        }

        $columns = array_map(
            fn (string $col) => $this->quoteIdentifier($col),
            $this->groups,
        );

        return ' GROUP BY ' . implode(', ', $columns);
    }

    private function buildHavingClause(): string
    {
        if ($this->havingClause === null) {
            return '';
        }

        $this->bindings = array_merge($this->bindings, $this->havingClause['bindings']);

        return ' HAVING ' . $this->havingClause['expression'];
    }

    private function buildJoinClause(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $sql = '';
        foreach ($this->joins as $join) {
            $sql .= sprintf(
                ' %s JOIN %s ON %s %s %s',
                $join['type'],
                $this->quoteIdentifier($join['table']),
                $this->quoteIdentifier($join['first']),
                $join['operator'],
                $this->quoteIdentifier($join['second']),
            );
        }

        return $sql;
    }

    private function buildWhereClause(): string
    {
        $conditions = [];

        foreach ($this->wheres as $where) {
            $columnExpr = JsonPathParser::isJsonPath($where['column'])
                ? $this->compileJsonExtract($where['column'])
                : $this->quoteIdentifier($where['column']);

            $condition = sprintf('%s %s ?', $columnExpr, $where['operator']);
            $this->bindings[] = $where['value'];

            if (!empty($conditions)) {
                $condition = $where['boolean'] . ' ' . $condition;
            }

            $conditions[] = $condition;
        }

        foreach ($this->whereIns as $whereIn) {
            $placeholders = array_fill(0, count($whereIn['values']), '?');
            $condition = sprintf(
                '%s IN (%s)',
                $this->quoteIdentifier($whereIn['column']),
                implode(', ', $placeholders),
            );
            $this->bindings = array_merge($this->bindings, $whereIn['values']);

            if (!empty($conditions)) {
                $condition = 'AND ' . $condition;
            }

            $conditions[] = $condition;
        }

        foreach ($this->whereNulls as $column) {
            $condition = sprintf('%s IS NULL', $this->quoteIdentifier($column));

            if (!empty($conditions)) {
                $condition = 'AND ' . $condition;
            }

            $conditions[] = $condition;
        }

        foreach ($this->whereNotNulls as $column) {
            $condition = sprintf('%s IS NOT NULL', $this->quoteIdentifier($column));

            if (!empty($conditions)) {
                $condition = 'AND ' . $condition;
            }

            $conditions[] = $condition;
        }

        foreach ($this->whereJsonContains as $item) {
            $path = $item['path'];
            $jsonValue = json_encode($item['value']);

            if (JsonPathParser::isJsonPath($path)) {
                $parsed = JsonPathParser::parse($path);
                $jsonPath = $this->buildMySqlJsonPath($parsed->segments);
                $columnSql = sprintf(
                    "JSON_CONTAINS(JSON_EXTRACT(%s, '%s'), ?)",
                    $this->quoteIdentifier($parsed->column),
                    $jsonPath,
                );
            } else {
                $columnSql = sprintf('JSON_CONTAINS(%s, ?)', $this->quoteIdentifier($path));
            }

            $this->bindings[] = $jsonValue;

            if (!empty($conditions)) {
                $columnSql = 'AND ' . $columnSql;
            }

            $conditions[] = $columnSql;
        }

        foreach ($this->whereJsonPaths as $item) {
            $path = $item['path'];
            $parsed = JsonPathParser::parse($path);
            $jsonPath = $this->buildMySqlJsonPath($parsed->segments);

            $expr = sprintf(
                "JSON_CONTAINS_PATH(%s, 'one', '%s')",
                $this->quoteIdentifier($parsed->column),
                $jsonPath,
            );

            if ($item['negate']) {
                $expr = 'NOT ' . $expr;
            }

            if (!empty($conditions)) {
                $expr = 'AND ' . $expr;
            }

            $conditions[] = $expr;
        }

        if (empty($conditions)) {
            return '';
        }

        return ' WHERE ' . implode(' ', $conditions);
    }

    private function buildOrderByClause(): string
    {
        if (empty($this->orders)) {
            return '';
        }

        $orders = array_map(
            fn ($order) => sprintf(
                '%s %s',
                $this->quoteIdentifier($order['column']),
                $order['direction'],
            ),
            $this->orders,
        );

        return ' ORDER BY ' . implode(', ', $orders);
    }

    private function buildLimitOffsetClause(): string
    {
        $sql = '';

        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . $this->limitValue;
        }

        if ($this->offsetValue !== null) {
            $sql .= ' OFFSET ' . $this->offsetValue;
        }

        return $sql;
    }
}
