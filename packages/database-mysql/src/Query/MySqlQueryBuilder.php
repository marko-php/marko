<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Query;

use Marko\Database\Connection\ConnectionInterface;
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

    public function count(): int
    {
        $this->bindings = [];
        $originalColumns = $this->columns;
        $this->columns = ['COUNT(*) as count'];

        $sql = $this->buildSelectSql();
        $result = $this->connection->query($sql, $this->bindings);

        $this->columns = $originalColumns;

        return (int) ($result[0]['count'] ?? 0);
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

    private function buildSelectSql(): string
    {
        $quotedColumns = array_map(function ($col) {
            if ($col === '*') {
                return '*';
            }
            // Handle aggregate functions and aliases
            if (str_contains(strtoupper($col), 'COUNT(') || str_contains($col, ' as ')) {
                return $col;
            }

            return $this->quoteIdentifier($col);
        }, $this->columns);

        $sql = sprintf(
            'SELECT %s FROM %s',
            implode(', ', $quotedColumns),
            $this->quoteIdentifier($this->table),
        );

        $sql .= $this->buildJoinClause();
        $sql .= $this->buildWhereClause();
        $sql .= $this->buildOrderByClause();
        $sql .= $this->buildLimitOffsetClause();

        return $sql;
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
            $condition = sprintf(
                '%s %s ?',
                $this->quoteIdentifier($where['column']),
                $where['operator'],
            );
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
