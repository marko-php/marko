<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Fixtures\Variant;

use Marko\Database\Query\QueryBuilderInterface;

class VariantQueryBuilder implements QueryBuilderInterface
{
    public function table(string $table): static
    {
        return $this;
    }

    public function select(string ...$columns): static
    {
        return $this;
    }

    public function selectRaw(
        string $expression,
        array $bindings = [],
    ): static
    {
        return $this;
    }

    public function distinct(): static
    {
        return $this;
    }

    public function where(
        string $column,
        string $operator,
        mixed $value,
    ): static
    {
        return $this;
    }

    public function whereIn(
        string $column,
        array $values,
    ): static
    {
        return $this;
    }

    public function whereNull(string $column): static
    {
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        return $this;
    }

    public function whereJsonContains(
        string $path,
        mixed $value,
    ): static
    {
        return $this;
    }

    public function whereJsonExists(string $path): static
    {
        return $this;
    }

    public function whereJsonMissing(string $path): static
    {
        return $this;
    }

    public function orWhere(
        string $column,
        string $operator,
        mixed $value,
    ): static
    {
        return $this;
    }

    public function whereRaw(
        string $expression,
        array $bindings = [],
    ): static
    {
        return $this;
    }

    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static
    {
        return $this;
    }

    public function leftJoin(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static
    {
        return $this;
    }

    public function rightJoin(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static
    {
        return $this;
    }

    public function groupBy(string ...$columns): static
    {
        return $this;
    }

    public function having(
        string $expression,
        array $bindings = [],
    ): static
    {
        return $this;
    }

    public function orderBy(
        string $column,
        string $direction = 'ASC',
    ): static
    {
        return $this;
    }

    public function orderByRaw(
        string $expression,
        string $direction = 'ASC',
    ): static
    {
        return $this;
    }

    public function limit(int $limit): static
    {
        return $this;
    }

    public function offset(int $offset): static
    {
        return $this;
    }

    public function union(QueryBuilderInterface $other): static
    {
        return $this;
    }

    public function unionAll(QueryBuilderInterface $other): static
    {
        return $this;
    }

    public function getColumnCount(): int
    {
        return 0;
    }

    public function compileSubquery(array &$bindings): string
    {
        return '';
    }

    public function get(): array
    {
        return [];
    }

    public function first(): ?array
    {
        return null;
    }

    public function insert(array $data): int
    {
        return 0;
    }

    public function update(array $data): int
    {
        return 0;
    }

    public function delete(): int
    {
        return 0;
    }

    public function count(?string $column = null): int
    {
        return 0;
    }

    public function min(string $column): int|float|null
    {
        return null;
    }

    public function max(string $column): int|float|null
    {
        return null;
    }

    public function sum(string $column): int|float|null
    {
        return null;
    }

    public function avg(string $column): int|float|null
    {
        return null;
    }

    public function raw(
        string $sql,
        array $bindings = [],
    ): array
    {
        return [];
    }
}
