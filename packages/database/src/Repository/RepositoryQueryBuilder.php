<?php

declare(strict_types=1);

namespace Marko\Database\Repository;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Query\QueryBuilderInterface;

/**
 * A query builder wrapper that provides entity hydration for repository queries.
 *
 * This class wraps a QueryBuilderInterface and adds the ability to return
 * hydrated entities instead of raw arrays.
 */
readonly class RepositoryQueryBuilder implements QueryBuilderInterface
{
    public function __construct(
        private QueryBuilderInterface $queryBuilder,
        private EntityHydrator $hydrator,
        private EntityMetadata $metadata,
        private string $entityClass,
    ) {}

    public function table(
        string $table,
    ): static {
        $this->queryBuilder->table($table);

        return $this;
    }

    public function select(
        string ...$columns,
    ): static {
        $this->queryBuilder->select(...$columns);

        return $this;
    }

    public function where(
        string $column,
        string $operator,
        mixed $value,
    ): static {
        $this->queryBuilder->where($column, $operator, $value);

        return $this;
    }

    public function whereIn(
        string $column,
        array $values,
    ): static {
        $this->queryBuilder->whereIn($column, $values);

        return $this;
    }

    public function whereNull(
        string $column,
    ): static {
        $this->queryBuilder->whereNull($column);

        return $this;
    }

    public function whereNotNull(
        string $column,
    ): static {
        $this->queryBuilder->whereNotNull($column);

        return $this;
    }

    public function orWhere(
        string $column,
        string $operator,
        mixed $value,
    ): static {
        $this->queryBuilder->orWhere($column, $operator, $value);

        return $this;
    }

    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static {
        $this->queryBuilder->join($table, $first, $operator, $second);

        return $this;
    }

    public function leftJoin(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static {
        $this->queryBuilder->leftJoin($table, $first, $operator, $second);

        return $this;
    }

    public function rightJoin(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static {
        $this->queryBuilder->rightJoin($table, $first, $operator, $second);

        return $this;
    }

    public function orderBy(
        string $column,
        string $direction = 'ASC',
    ): static {
        $this->queryBuilder->orderBy($column, $direction);

        return $this;
    }

    public function limit(
        int $limit,
    ): static {
        $this->queryBuilder->limit($limit);

        return $this;
    }

    public function offset(
        int $offset,
    ): static {
        $this->queryBuilder->offset($offset);

        return $this;
    }

    public function get(): array
    {
        return $this->queryBuilder->get();
    }

    public function first(): ?array
    {
        return $this->queryBuilder->first();
    }

    public function insert(
        array $data,
    ): int {
        return $this->queryBuilder->insert($data);
    }

    public function update(
        array $data,
    ): int {
        return $this->queryBuilder->update($data);
    }

    public function delete(): int
    {
        return $this->queryBuilder->delete();
    }

    public function count(): int
    {
        return $this->queryBuilder->count();
    }

    public function raw(
        string $sql,
        array $bindings = [],
    ): array {
        return $this->queryBuilder->raw($sql, $bindings);
    }

    /**
     * Execute the query and return hydrated entities.
     *
     * @return array<Entity>
     */
    public function getEntities(): array
    {
        $rows = $this->queryBuilder->get();

        return array_map(
            fn (array $row): Entity => $this->hydrator->hydrate(
                $this->entityClass,
                $row,
                $this->metadata,
            ),
            $rows,
        );
    }

    /**
     * Execute the query and return the first hydrated entity or null.
     */
    public function firstEntity(): ?Entity
    {
        $row = $this->queryBuilder->first();

        if ($row === null) {
            return null;
        }

        return $this->hydrator->hydrate(
            $this->entityClass,
            $row,
            $this->metadata,
        );
    }
}
