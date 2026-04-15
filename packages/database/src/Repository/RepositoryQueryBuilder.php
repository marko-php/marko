<?php

declare(strict_types=1);

namespace Marko\Database\Repository;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\RelationshipLoader;
use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Query\QuerySpecification;

/**
 * A query builder wrapper that provides entity hydration for repository queries.
 *
 * This class wraps a QueryBuilderInterface and adds the ability to return
 * hydrated entities instead of raw arrays, with optional eager loading and
 * query specification support.
 */
class RepositoryQueryBuilder implements QueryBuilderInterface
{
    /**
     * Relationship names to eager-load on query execution.
     *
     * @var array<string>
     */
    private array $relationships = [];

    public function __construct(
        private readonly QueryBuilderInterface $queryBuilder,
        private readonly EntityHydrator $hydrator,
        private readonly EntityMetadata $metadata,
        private readonly string $entityClass,
        private readonly ?RelationshipLoader $relationshipLoader = null,
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
     * Specify relationships to eager-load when fetching entities.
     */
    public function with(string ...$relationships): static
    {
        $this->relationships = array_values($relationships);

        return $this;
    }

    /**
     * Apply query specifications to the underlying query builder and return an EntityCollection.
     *
     * @return EntityCollection<Entity>
     */
    public function matching(QuerySpecification ...$specifications): EntityCollection
    {
        foreach ($specifications as $specification) {
            $specification->apply($this->queryBuilder);
        }

        return $this->getEntities();
    }

    /**
     * Execute the query and return hydrated entities as an EntityCollection.
     *
     * @return EntityCollection<Entity>
     */
    public function getEntities(): EntityCollection
    {
        $rows = $this->queryBuilder->get();

        $entities = array_map(
            fn (array $row): Entity => $this->hydrator->hydrate(
                $this->entityClass,
                $row,
                $this->metadata,
            ),
            $rows,
        );

        $this->eagerLoadRelationships($entities);

        return new EntityCollection($entities);
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

        $entity = $this->hydrator->hydrate(
            $this->entityClass,
            $row,
            $this->metadata,
        );

        $this->eagerLoadRelationships([$entity]);

        return $entity;
    }

    /**
     * Eager-load any pending relationships on the given entities.
     *
     * @param Entity[] $entities
     */
    private function eagerLoadRelationships(array $entities): void
    {
        if ($this->relationships === [] || $this->relationshipLoader === null || $entities === []) {
            return;
        }

        foreach ($this->relationships as $name) {
            $relationship = $this->metadata->getRelationship($name);

            if ($relationship === null) {
                continue;
            }

            $this->relationshipLoader->load($entities, $relationship, $this->metadata);
        }
    }
}
