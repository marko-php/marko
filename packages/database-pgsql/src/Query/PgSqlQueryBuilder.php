<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Query;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Query\QueryBuilderInterface;

class PgSqlQueryBuilder implements QueryBuilderInterface
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
     * @var array<array{column: string, direction: string}>
     */
    private array $orders = [];

    private ?int $limitValue = null;

    private ?int $offsetValue = null;

    /**
     * @var array
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
        $values = array_values($data);

        $quotedColumns = array_map(
            fn (string $col): string => $this->quoteIdentifier($col),
            $columns,
        );

        $placeholders = [];
        foreach ($values as $index => $value) {
            $placeholders[] = '$' . ($index + 1);
            $this->bindings[] = $value;
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) RETURNING %s',
            $this->quoteIdentifier($this->table),
            implode(', ', $quotedColumns),
            implode(', ', $placeholders),
            $this->quoteIdentifier('id'),
        );

        $result = $this->connection->query($sql, $this->bindings);

        return (int) ($result[0]['id'] ?? 0);
    }

    public function update(
        array $data,
    ): int {
        $this->bindings = [];
        $setParts = [];
        $paramIndex = 1;

        foreach ($data as $column => $value) {
            $setParts[] = sprintf(
                '%s = $%d',
                $this->quoteIdentifier($column),
                $paramIndex,
            );
            $this->bindings[] = $value;
            $paramIndex++;
        }

        $sql = sprintf(
            'UPDATE %s SET %s',
            $this->quoteIdentifier($this->table),
            implode(', ', $setParts),
        );

        $sql .= $this->buildWhereClause($paramIndex);

        return $this->connection->execute($sql, $this->bindings);
    }

    public function delete(): int
    {
        $this->bindings = [];

        $sql = sprintf(
            'DELETE FROM %s',
            $this->quoteIdentifier($this->table),
        );

        $sql .= $this->buildWhereClause(1);

        return $this->connection->execute($sql, $this->bindings);
    }

    public function count(): int
    {
        $sql = sprintf(
            'SELECT COUNT(*) as count FROM %s',
            $this->quoteIdentifier($this->table),
        );

        $sql .= $this->buildWhereClause(1);

        $result = $this->connection->query($sql, $this->bindings);

        return (int) ($result[0]['count'] ?? 0);
    }

    public function raw(
        string $sql,
        array $bindings = [],
    ): array {
        return $this->connection->query($sql, $bindings);
    }

    protected function quoteIdentifier(
        string $identifier,
    ): string {
        // Handle table.column format
        if (str_contains($identifier, '.')) {
            $parts = explode('.', $identifier);

            return implode(
                '.',
                array_map(
                    fn (string $part): string => '"' . $part . '"',
                    $parts,
                ),
            );
        }

        return '"' . $identifier . '"';
    }

    private function buildSelectSql(): string
    {
        $this->bindings = [];

        $columns = $this->columns[0] === '*'
            ? '*'
            : implode(
                ', ',
                array_map(
                    fn (string $col): string => $this->quoteIdentifier($col),
                    $this->columns,
                ),
            );

        $sql = sprintf(
            'SELECT %s FROM %s',
            $columns,
            $this->quoteIdentifier($this->table),
        );

        $sql .= $this->buildJoinClause();
        $sql .= $this->buildWhereClause(1);
        $sql .= $this->buildOrderByClause();
        $sql .= $this->buildLimitOffsetClause();

        return $sql;
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

    private function buildWhereClause(
        int $startIndex,
    ): string {
        $conditions = [];
        $paramIndex = $startIndex;

        // Regular WHERE conditions
        foreach ($this->wheres as $index => $where) {
            $condition = sprintf(
                '%s %s $%d',
                $this->quoteIdentifier($where['column']),
                $where['operator'],
                $paramIndex,
            );

            if ($index === 0 && empty($conditions)) {
                $conditions[] = $condition;
            } else {
                $conditions[] = $where['boolean'] . ' ' . $condition;
            }

            $this->bindings[] = $where['value'];
            $paramIndex++;
        }

        // WHERE IN conditions
        foreach ($this->whereIns as $whereIn) {
            $placeholders = [];
            foreach ($whereIn['values'] as $value) {
                $placeholders[] = '$' . $paramIndex;
                $this->bindings[] = $value;
                $paramIndex++;
            }

            $condition = sprintf(
                '%s IN (%s)',
                $this->quoteIdentifier($whereIn['column']),
                implode(', ', $placeholders),
            );

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
                $this->quoteIdentifier($order['column']),
                $order['direction'],
            ),
            $this->orders,
        );

        return ' ORDER BY ' . implode(', ', $clauses);
    }

    private function buildLimitOffsetClause(): string
    {
        $clause = '';

        if ($this->limitValue !== null) {
            $clause .= ' LIMIT ' . $this->limitValue;
        }

        if ($this->offsetValue !== null) {
            $clause .= ' OFFSET ' . $this->offsetValue;
        }

        return $clause;
    }
}
