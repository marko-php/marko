<?php

declare(strict_types=1);

namespace Marko\Database\Query;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\Exceptions\LockException;
use Marko\Database\Exceptions\UnionShapeMismatchException;
use Marko\Database\Exceptions\UpsertException;

abstract class SqlQueryBuilder implements QueryBuilderInterface
{
    private string $table = '';

    private ?string $alias = null;

    /**
     * @var array<string>
     */
    private array $columns = ['*'];

    /**
     * @var array<array{column: string, operator: string, value: mixed, boolean: string}>
     */
    private array $wheres = [];

    /**
     * @var array<array{column: string, values: array, not: bool}>
     */
    private array $whereIns = [];

    /**
     * @var list<array{first: string, operator: string, second: string}>
     */
    private array $whereColumns = [];

    /**
     * @var list<array{subquery: QueryBuilderInterface, negate: bool}>
     */
    private array $whereExists = [];

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
     * @var array<array{column: string, direction: string, raw: bool}>
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

    /**
     * @var list<string>
     */
    private array $rawSelects = [];

    /**
     * @var list<mixed>
     */
    private array $rawSelectBindings = [];

    /**
     * @var list<array{expression: string, bindings: array<int, mixed>}>
     */
    private array $rawWheres = [];

    /**
     * Row lock requested with lockForUpdate() ('update') or sharedLock() ('share').
     */
    private ?string $lockMode = null;

    private bool $skipLocked = false;

    private bool $noWait = false;

    /**
     * @param QueryBuilderFactoryInterface $queryBuilderFactory Creates the builders whereExists() callables receive
     */
    public function __construct(
        protected readonly ConnectionInterface $connection,
        protected readonly QueryBuilderFactoryInterface $queryBuilderFactory,
    ) {}

    /**
     * @throws InvalidColumnException
     */
    public function table(
        string $table,
        ?string $alias = null,
    ): static {
        IdentifierValidator::assertValidIdentifier($table);

        if ($alias !== null) {
            IdentifierValidator::assertValidAlias($alias);
        }

        $this->table = $table;
        $this->alias = $alias;

        return $this;
    }

    public function select(
        string ...$columns,
    ): static {
        $this->columns = $columns;

        return $this;
    }

    /**
     * @throws InvalidColumnException
     */
    public function selectRaw(
        string $expression,
        array $bindings = [],
    ): static {
        IdentifierValidator::assertNoDangerousPatterns($expression, $this->hashStartsComment());
        $this->rawSelects[] = $expression;
        array_push($this->rawSelectBindings, ...$bindings);

        return $this;
    }

    public function distinct(): static
    {
        $this->distinct = true;

        return $this;
    }

    /**
     * @throws UnionShapeMismatchException
     */
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

    /**
     * @throws UnionShapeMismatchException
     */
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

    /**
     * @throws InvalidColumnException|LockException
     */
    public function compileSubquery(
        array &$bindings,
    ): string {
        $this->assertNotLocked('union()');

        $savedBindings = $this->bindings;
        $sql = $this->buildSelectSql();
        $bindings = array_merge($bindings, $this->bindings);
        $this->bindings = $savedBindings;

        return $sql;
    }

    /**
     * @throws InvalidColumnException
     */
    public function where(
        string $column,
        string $operator,
        mixed $value,
    ): static {
        if (!JsonPathParser::isJsonPath($column)) {
            IdentifierValidator::assertValidIdentifier($column);
        }

        IdentifierValidator::assertValidOperator($operator);

        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND',
        ];

        return $this;
    }

    /**
     * @throws InvalidColumnException
     */
    public function whereIn(
        string $column,
        array $values,
    ): static {
        IdentifierValidator::assertValidIdentifier($column);

        $this->whereIns[] = [
            'column' => $column,
            'values' => $values,
            'not' => false,
        ];

        return $this;
    }

    /**
     * @throws InvalidColumnException
     */
    public function whereNotIn(
        string $column,
        array $values,
    ): static {
        IdentifierValidator::assertValidIdentifier($column);

        $this->whereIns[] = [
            'column' => $column,
            'values' => $values,
            'not' => true,
        ];

        return $this;
    }

    /**
     * @throws InvalidColumnException
     */
    public function whereColumn(
        string $first,
        string $operator,
        string $second,
    ): static {
        IdentifierValidator::assertValidIdentifier($first);
        IdentifierValidator::assertValidColumnOperator($operator);
        IdentifierValidator::assertValidIdentifier($second);

        $this->whereColumns[] = [
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    public function whereExists(
        callable|QueryBuilderInterface $subquery,
    ): static {
        $this->whereExists[] = [
            'subquery' => $this->resolveSubquery($subquery),
            'negate' => false,
        ];

        return $this;
    }

    public function whereNotExists(
        callable|QueryBuilderInterface $subquery,
    ): static {
        $this->whereExists[] = [
            'subquery' => $this->resolveSubquery($subquery),
            'negate' => true,
        ];

        return $this;
    }

    /**
     * @throws InvalidColumnException
     */
    public function whereNull(
        string $column,
    ): static {
        IdentifierValidator::assertValidIdentifier($column);
        $this->whereNulls[] = $column;

        return $this;
    }

    /**
     * @throws InvalidColumnException
     */
    public function whereNotNull(
        string $column,
    ): static {
        IdentifierValidator::assertValidIdentifier($column);
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

    /**
     * @throws InvalidColumnException
     */
    public function orWhere(
        string $column,
        string $operator,
        mixed $value,
    ): static {
        if (!JsonPathParser::isJsonPath($column)) {
            IdentifierValidator::assertValidIdentifier($column);
        }

        IdentifierValidator::assertValidOperator($operator);

        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR',
        ];

        return $this;
    }

    /**
     * @throws InvalidColumnException
     */
    public function whereRaw(
        string $expression,
        array $bindings = [],
    ): static {
        IdentifierValidator::assertNoDangerousPatterns($expression, $this->hashStartsComment());
        $this->rawWheres[] = ['expression' => $expression, 'bindings' => $bindings];

        return $this;
    }

    /**
     * @throws InvalidColumnException
     */
    public function groupBy(
        string ...$columns,
    ): static {
        foreach ($columns as $column) {
            if (!IdentifierValidator::isValidIdentifier($column) && !preg_match(
                '/^[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*$/',
                $column,
            )) {
                throw InvalidColumnException::invalidColumn($column);
            }
        }

        $this->groups = array_merge($this->groups, $columns);

        return $this;
    }

    /**
     * @throws InvalidColumnException
     */
    public function having(
        string $expression,
        array $bindings = [],
    ): static {
        IdentifierValidator::assertNoDangerousPatterns($expression, $this->hashStartsComment());

        $this->havingClause = [
            'expression' => $expression,
            'bindings' => $bindings,
        ];

        return $this;
    }

    /**
     * @throws InvalidColumnException
     */
    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static {
        IdentifierValidator::assertValidIdentifier($table);
        IdentifierValidator::assertValidIdentifier($first);
        IdentifierValidator::assertValidOperator($operator);
        IdentifierValidator::assertValidIdentifier($second);

        $this->joins[] = [
            'type' => 'INNER',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * @throws InvalidColumnException
     */
    public function leftJoin(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static {
        IdentifierValidator::assertValidIdentifier($table);
        IdentifierValidator::assertValidIdentifier($first);
        IdentifierValidator::assertValidOperator($operator);
        IdentifierValidator::assertValidIdentifier($second);

        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * @throws InvalidColumnException
     */
    public function rightJoin(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static {
        IdentifierValidator::assertValidIdentifier($table);
        IdentifierValidator::assertValidIdentifier($first);
        IdentifierValidator::assertValidOperator($operator);
        IdentifierValidator::assertValidIdentifier($second);

        $this->joins[] = [
            'type' => 'RIGHT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * @throws InvalidColumnException
     */
    public function orderBy(
        string $column,
        string $direction = 'ASC',
    ): static {
        if (!JsonPathParser::isJsonPath($column)) {
            IdentifierValidator::assertValidIdentifier($column);
        }

        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }

        $this->orders[] = [
            'column' => $column,
            'direction' => $direction,
            'raw' => false,
        ];

        return $this;
    }

    /**
     * @throws InvalidColumnException
     */
    public function orderByRaw(
        string $expression,
        string $direction = 'ASC',
    ): static {
        IdentifierValidator::assertNoDangerousPatterns($expression, $this->hashStartsComment());

        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }

        $this->orders[] = [
            'column' => $expression,
            'direction' => $direction,
            'raw' => true,
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

    /**
     * @throws InvalidColumnException|LockException
     */
    public function get(): array
    {
        if (!empty($this->unions)) {
            $this->assertNotLocked('union()');

            return $this->executeUnion();
        }

        $sql = $this->buildSelectSql();

        return $this->connection->query($sql, $this->bindings);
    }

    /**
     * @throws InvalidColumnException|LockException
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();

        return $results[0] ?? null;
    }

    /**
     * @throws InvalidColumnException
     */
    public function insert(
        array $data,
        ?string $primaryKey = null,
    ): int {
        $this->bindings = [];
        $columns = array_keys($data);
        $values = array_values($data);

        foreach ($columns as $col) {
            IdentifierValidator::assertValidIdentifier($col);
        }

        $quotedColumns = array_map(
            fn (string $col): string => $this->quoteIdentifier($col),
            $columns,
        );

        $placeholders = [];
        foreach ($values as $value) {
            $placeholders[] = '?';
            $this->bindings[] = $value;
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($this->table),
            implode(', ', $quotedColumns),
            implode(', ', $placeholders),
        );

        return $this->insertAndReturnKey($sql, $this->bindings, $this->table, $primaryKey ?? 'id');
    }

    /**
     * @throws InvalidColumnException
     */
    public function update(
        array $data,
    ): int {
        $this->bindings = [];
        $setParts = [];

        foreach ($data as $column => $value) {
            IdentifierValidator::assertValidIdentifier($column);
            $setParts[] = sprintf(
                '%s = ?',
                $this->quoteIdentifier($column),
            );
            $this->bindings[] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s',
            $this->compileTableReference(),
            implode(', ', $setParts),
        );

        $sql .= $this->buildWhereClause();

        return $this->connection->execute($sql, $this->bindings);
    }

    public function delete(): int
    {
        $this->bindings = [];

        $sql = sprintf(
            'DELETE FROM %s',
            $this->compileTableReference(),
        );

        $sql .= $this->buildWhereClause();

        return $this->connection->execute($sql, $this->bindings);
    }

    /**
     * @throws InvalidColumnException|LockException
     */
    public function count(?string $column = null): int
    {
        $this->assertNotLocked('count()');

        if ($column !== null) {
            IdentifierValidator::assertValidIdentifier($column);
        }

        $expr = $column !== null
            ? 'COUNT(' . $this->quoteIdentifier($column) . ') as aggregate'
            : 'COUNT(*) as aggregate';

        return (int) $this->runAggregate($expr);
    }

    /**
     * @throws InvalidColumnException|LockException
     */
    public function min(string $column): int|float|null
    {
        $this->assertNotLocked('min()');

        if (!IdentifierValidator::isValidIdentifier($column)) {
            throw InvalidColumnException::invalidColumn($column);
        }

        return $this->runAggregate('MIN(' . $this->quoteIdentifier($column) . ') as aggregate');
    }

    /**
     * @throws InvalidColumnException|LockException
     */
    public function max(string $column): int|float|null
    {
        $this->assertNotLocked('max()');

        if (!IdentifierValidator::isValidIdentifier($column)) {
            throw InvalidColumnException::invalidColumn($column);
        }

        return $this->runAggregate('MAX(' . $this->quoteIdentifier($column) . ') as aggregate');
    }

    /**
     * @throws InvalidColumnException|LockException
     */
    public function sum(string $column): int|float|null
    {
        $this->assertNotLocked('sum()');

        if (!IdentifierValidator::isValidIdentifier($column)) {
            throw InvalidColumnException::invalidColumn($column);
        }

        return $this->runAggregate('SUM(' . $this->quoteIdentifier($column) . ') as aggregate');
    }

    /**
     * @throws InvalidColumnException|LockException
     */
    public function avg(string $column): int|float|null
    {
        $this->assertNotLocked('avg()');

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

    public function lockForUpdate(): static
    {
        $this->lockMode = 'update';

        return $this;
    }

    public function sharedLock(): static
    {
        $this->lockMode = 'share';

        return $this;
    }

    public function skipLocked(): static
    {
        $this->skipLocked = true;

        return $this;
    }

    public function noWait(): static
    {
        $this->noWait = true;

        return $this;
    }

    /**
     * @throws InvalidColumnException|UpsertException
     */
    public function upsert(
        array $rows,
        array $uniqueBy,
        ?array $update = null,
    ): int {
        $updateColumns = UpsertValidator::validate($rows, $uniqueBy, $update);
        $rows = array_values($rows);
        $columns = array_map('strval', array_keys($rows[0]));

        $bindings = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                $bindings[] = $value;
            }
        }

        $placeholderRow = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s %s',
            $this->quoteIdentifier($this->table),
            implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns)),
            implode(', ', array_fill(0, count($rows), $placeholderRow)),
            $this->buildUpsertConflictClause(array_values($uniqueBy), $updateColumns),
        );

        return $this->connection->execute($sql, $bindings);
    }

    /**
     * Quote a database identifier with the dialect's one quoting rule.
     *
     * @param string $identifier The identifier to quote
     * @return string The quoted identifier
     */
    abstract protected function quoteIdentifier(
        string $identifier,
    ): string;

    /**
     * Run a compiled INSERT and return the new row's primary key.
     *
     * @param string $sql The INSERT statement, without any dialect-specific key retrieval
     * @param array<int, mixed> $bindings
     * @param string $table The table inserted into
     * @param string $primaryKey The primary key column
     */
    abstract protected function insertAndReturnKey(
        string $sql,
        array $bindings,
        string $table,
        string $primaryKey,
    ): int;

    /**
     * Compile a JSON path expression (e.g. "data->user->name" or "data->>name") into a value expression.
     */
    abstract protected function compileJsonPath(string $expression): string;

    /**
     * Compile a JSON containment test with one `?` placeholder for the JSON-encoded value.
     *
     * @param string $path A plain column or a JSON path expression
     */
    abstract protected function compileJsonContains(string $path): string;

    /**
     * Compile a test that a JSON path exists, without any negation.
     *
     * @param string $path A JSON path expression
     */
    abstract protected function compileJsonPathExists(string $path): string;

    /**
     * Compile the dialect's upsert conflict clause.
     *
     * @param list<string> $uniqueBy
     * @param list<string> $updateColumns
     */
    abstract protected function buildUpsertConflictClause(
        array $uniqueBy,
        array $updateColumns,
    ): string;

    /**
     * Whether `#` starts a SQL comment in this dialect, so raw expressions containing it are rejected.
     */
    protected function hashStartsComment(): bool
    {
        return false;
    }

    /**
     * The LIMIT clause emitted when only an OFFSET is set, for dialects that cannot have OFFSET alone.
     */
    protected function offsetOnlyLimitClause(): string
    {
        return '';
    }

    /**
     * Compile the row-lock clause text, once the base has validated the lock.
     *
     * @param string $lockMode 'update' or 'share'
     * @param string $modifier ' SKIP LOCKED', ' NOWAIT' or ''
     */
    abstract protected function compileLockClause(
        string $lockMode,
        string $modifier,
    ): string;

    /**
     * The FROM / UPDATE / DELETE target: the quoted table, plus `AS alias` when table() was given one.
     */
    private function compileTableReference(): string
    {
        $reference = $this->quoteIdentifier($this->table);

        if ($this->alias !== null) {
            $reference .= ' AS ' . $this->quoteIdentifier($this->alias);
        }

        return $reference;
    }

    /**
     * A builder passed to whereExists() is used as is; a callable configures a fresh builder on this connection.
     */
    private function resolveSubquery(
        callable|QueryBuilderInterface $subquery,
    ): QueryBuilderInterface {
        if ($subquery instanceof QueryBuilderInterface) {
            return $subquery;
        }

        $builder = $this->queryBuilderFactory->create();
        $subquery($builder);

        return $builder;
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
            $this->compileTableReference(),
        );

        $sql .= $this->buildWhereClause();

        $result = $this->connection->query($sql, $this->bindings);
        $value = $result[0]['aggregate'] ?? null;

        if ($value === null) {
            return null;
        }

        return is_int($value + 0) ? (int) $value : (float) $value;
    }

    /**
     * Wrap one SELECT of a UNION so its own ORDER BY / LIMIT stay scoped to it. A dialect whose compound
     * SELECT rejects parenthesised operands overrides this.
     */
    protected function wrapUnionPart(string $sql): string
    {
        return '(' . $sql . ')';
    }

    /**
     * @throws InvalidColumnException|LockException
     */
    private function executeUnion(): array
    {
        $bindings = [];

        // Build left subquery (without outer ORDER BY / LIMIT — those wrap the union)
        $savedOrders = $this->orders;
        $savedLimit = $this->limitValue;
        $savedOffset = $this->offsetValue;
        $this->orders = [];
        $this->limitValue = null;
        $this->offsetValue = null;

        $leftSql = $this->buildSelectSql();
        $bindings = array_merge($bindings, $this->bindings);

        $this->orders = $savedOrders;
        $this->limitValue = $savedLimit;
        $this->offsetValue = $savedOffset;

        $parts = [$this->wrapUnionPart($leftSql)];

        foreach ($this->unions as $union) {
            $rightBindings = [];
            $rightSql = $union['builder']->compileSubquery($rightBindings);
            $bindings = array_merge($bindings, $rightBindings);
            $parts[] = $union['type'];
            $parts[] = $this->wrapUnionPart($rightSql);
        }

        $sql = implode(' ', $parts);
        $sql .= $this->buildOrderByClause();
        $sql .= $this->buildLimitOffsetClause();

        return $this->connection->query($sql, $bindings);
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
            $compiledColumn = $this->compileJsonPath($colPart);

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

    /**
     * @throws InvalidColumnException|LockException
     */
    private function buildSelectSql(): string
    {
        $this->bindings = [];

        foreach ($this->rawSelectBindings as $binding) {
            $this->bindings[] = $binding;
        }

        $regularColumns = $this->columns[0] === '*'
            ? ['*']
            : array_map(
                fn (string $col): string => $this->compileColumnExpression($col),
                $this->columns,
            );

        $selectParts = array_merge($regularColumns, $this->rawSelects);
        $columns = implode(', ', $selectParts);

        $keyword = $this->distinct ? 'SELECT DISTINCT' : 'SELECT';

        $sql = sprintf(
            '%s %s FROM %s',
            $keyword,
            $columns,
            $this->compileTableReference(),
        );

        $sql .= $this->buildJoinClause();
        $sql .= $this->buildWhereClause();
        $sql .= $this->buildGroupByClause();
        $sql .= $this->buildHavingClause();
        $sql .= $this->buildOrderByClause();
        $sql .= $this->buildLimitOffsetClause();
        $sql .= $this->buildLockClause();

        return $sql;
    }

    private function buildGroupByClause(): string
    {
        if (empty($this->groups)) {
            return '';
        }

        $columns = array_map(
            fn (string $col): string => $this->quoteIdentifier($col),
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

        $clauses = [];

        foreach ($this->joins as $join) {
            $clauses[] = sprintf(
                ' %s JOIN %s ON %s %s %s',
                $join['type'],
                $this->quoteIdentifier($join['table']),
                $this->quoteIdentifier($join['first']),
                $join['operator'],
                $this->quoteIdentifier($join['second']),
            );
        }

        return implode('', $clauses);
    }

    private function buildWhereClause(): string
    {
        $conditions = [];

        // Regular WHERE conditions (with JSON path support)
        foreach ($this->wheres as $index => $where) {
            $columnExpr = JsonPathParser::isJsonPath($where['column'])
                ? $this->compileJsonPath($where['column'])
                : $this->quoteIdentifier($where['column']);

            $condition = sprintf('%s %s ?', $columnExpr, $where['operator']);

            if ($index === 0 && empty($conditions)) {
                $conditions[] = $condition;
            } else {
                $conditions[] = $where['boolean'] . ' ' . $condition;
            }

            $this->bindings[] = $where['value'];
        }

        // WHERE IN conditions
        foreach ($this->whereIns as $whereIn) {
            if ($whereIn['values'] === []) {
                $condition = $whereIn['not'] ? '1 = 1' : '1 = 0';
            } else {
                $placeholders = [];
                foreach ($whereIn['values'] as $value) {
                    $placeholders[] = '?';
                    $this->bindings[] = $value;
                }

                $condition = sprintf(
                    '%s %s (%s)',
                    $this->quoteIdentifier($whereIn['column']),
                    $whereIn['not'] ? 'NOT IN' : 'IN',
                    implode(', ', $placeholders),
                );
            }

            if (empty($conditions)) {
                $conditions[] = $condition;
            } else {
                $conditions[] = 'AND ' . $condition;
            }
        }

        // WHERE NULL conditions
        foreach ($this->whereNulls as $column) {
            $condition = sprintf('%s IS NULL', $this->quoteIdentifier($column));

            if (empty($conditions)) {
                $conditions[] = $condition;
            } else {
                $conditions[] = 'AND ' . $condition;
            }
        }

        // WHERE NOT NULL conditions
        foreach ($this->whereNotNulls as $column) {
            $condition = sprintf('%s IS NOT NULL', $this->quoteIdentifier($column));

            if (empty($conditions)) {
                $conditions[] = $condition;
            } else {
                $conditions[] = 'AND ' . $condition;
            }
        }

        // Column-to-column conditions
        foreach ($this->whereColumns as $whereColumn) {
            $condition = sprintf(
                '%s %s %s',
                $this->quoteIdentifier($whereColumn['first']),
                $whereColumn['operator'],
                $this->quoteIdentifier($whereColumn['second']),
            );

            if (empty($conditions)) {
                $conditions[] = $condition;
            } else {
                $conditions[] = 'AND ' . $condition;
            }
        }

        // WHERE JSON CONTAINS conditions
        foreach ($this->whereJsonContains as $item) {
            $path = $item['path'];
            $jsonValue = json_encode($item['value']);

            $columnSql = $this->compileJsonContains($path);

            $this->bindings[] = $jsonValue;

            if (empty($conditions)) {
                $conditions[] = $columnSql;
            } else {
                $conditions[] = 'AND ' . $columnSql;
            }
        }

        // WHERE JSON PATH EXISTS / MISSING conditions
        foreach ($this->whereJsonPaths as $item) {
            $path = $item['path'];
            $expr = $this->compileJsonPathExists($path);

            if ($item['negate']) {
                $expr = 'NOT ' . $expr;
            }

            if (empty($conditions)) {
                $conditions[] = $expr;
            } else {
                $conditions[] = 'AND ' . $expr;
            }
        }

        // EXISTS / NOT EXISTS subqueries, with their bindings at this position
        foreach ($this->whereExists as $exists) {
            $subqueryBindings = [];
            $condition = sprintf(
                '%s (%s)',
                $exists['negate'] ? 'NOT EXISTS' : 'EXISTS',
                $exists['subquery']->compileSubquery($subqueryBindings),
            );

            foreach ($subqueryBindings as $binding) {
                $this->bindings[] = $binding;
            }

            if (empty($conditions)) {
                $conditions[] = $condition;
            } else {
                $conditions[] = 'AND ' . $condition;
            }
        }

        // Raw WHERE conditions
        foreach ($this->rawWheres as $rawWhere) {
            if (empty($conditions)) {
                $conditions[] = $rawWhere['expression'];
            } else {
                $conditions[] = 'AND ' . $rawWhere['expression'];
            }

            foreach ($rawWhere['bindings'] as $binding) {
                $this->bindings[] = $binding;
            }
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

        $clauses = array_map(
            fn (array $order): string => sprintf(
                '%s %s',
                $order['raw'] ?? false ? $order['column'] : $this->quoteIdentifier($order['column']),
                $order['direction'],
            ),
            $this->orders,
        );

        return ' ORDER BY ' . implode(', ', $clauses);
    }

    protected function buildLimitOffsetClause(): string
    {
        $clause = '';

        if ($this->limitValue !== null) {
            $clause .= ' LIMIT ' . $this->limitValue;
        } elseif ($this->offsetValue !== null) {
            $clause .= $this->offsetOnlyLimitClause();
        }

        if ($this->offsetValue !== null) {
            $clause .= ' OFFSET ' . $this->offsetValue;
        }

        return $clause;
    }

    /**
     * Compile the row-lock clause, appended after LIMIT/OFFSET.
     *
     * @throws LockException
     */
    private function buildLockClause(): string
    {
        $modifier = $this->buildLockModifier();

        if ($this->lockMode === null) {
            return '';
        }

        $this->assertLockInTransaction();

        return $this->compileLockClause($this->lockMode, $modifier);
    }

    /**
     * @throws LockException
     */
    private function buildLockModifier(): string
    {
        if ($this->skipLocked && $this->noWait) {
            throw LockException::conflictingModifiers();
        }

        if ($this->lockMode === null && $this->skipLocked) {
            throw LockException::modifierWithoutLock('skipLocked');
        }

        if ($this->lockMode === null && $this->noWait) {
            throw LockException::modifierWithoutLock('noWait');
        }

        return match (true) {
            $this->skipLocked => ' SKIP LOCKED',
            $this->noWait => ' NOWAIT',
            default => '',
        };
    }

    /**
     * A lock taken outside a transaction is released as soon as the SELECT
     * finishes, which is almost always a bug.
     *
     * @throws LockException
     */
    private function assertLockInTransaction(): void
    {
        if (!$this->connection instanceof TransactionInterface || !$this->connection->inTransaction()) {
            throw LockException::outsideTransaction($this->table);
        }
    }

    /**
     * @throws LockException
     */
    private function assertNotLocked(
        string $operation,
    ): void {
        if ($this->lockMode !== null) {
            throw LockException::unsupportedOperation($operation);
        }
    }
}
