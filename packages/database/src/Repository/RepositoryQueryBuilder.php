<?php

declare(strict_types=1);

namespace Marko\Database\Repository;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\RelationshipLoader;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Query\EntityQueryBuilderInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Query\QuerySpecification;

/**
 * A query builder wrapper that provides entity hydration for repository queries.
 *
 * This class wraps a QueryBuilderInterface and adds the ability to return
 * hydrated entities instead of raw arrays, with optional eager loading and
 * query specification support.
 */
class RepositoryQueryBuilder implements EntityQueryBuilderInterface
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

    public function whereJsonContains(string $path, mixed $value): static
    {
        $this->queryBuilder->whereJsonContains($path, $value);

        return $this;
    }

    public function whereJsonExists(string $path): static
    {
        $this->queryBuilder->whereJsonExists($path);

        return $this;
    }

    public function whereJsonMissing(string $path): static
    {
        $this->queryBuilder->whereJsonMissing($path);

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

    public function distinct(): static
    {
        $this->queryBuilder->distinct();

        return $this;
    }

    public function groupBy(string ...$columns): static
    {
        $this->queryBuilder->groupBy(...$columns);

        return $this;
    }

    public function having(string $expression, array $bindings = []): static
    {
        $this->queryBuilder->having($expression, $bindings);

        return $this;
    }

    public function union(
        QueryBuilderInterface $other,
    ): static {
        $this->queryBuilder->union($other);

        return $this;
    }

    public function unionAll(
        QueryBuilderInterface $other,
    ): static {
        $this->queryBuilder->unionAll($other);

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

    public function count(?string $column = null): int
    {
        return $this->queryBuilder->count($column);
    }

    public function min(string $column): int|float|null
    {
        return $this->queryBuilder->min($column);
    }

    public function max(string $column): int|float|null
    {
        return $this->queryBuilder->max($column);
    }

    public function sum(string $column): int|float|null
    {
        return $this->queryBuilder->sum($column);
    }

    public function avg(string $column): int|float|null
    {
        return $this->queryBuilder->avg($column);
    }

    public function getColumnCount(): int
    {
        return $this->queryBuilder->getColumnCount();
    }

    public function compileSubquery(array &$bindings): string
    {
        return $this->queryBuilder->compileSubquery($bindings);
    }

    public function raw(
        string $sql,
        array $bindings = [],
    ): array {
        return $this->queryBuilder->raw($sql, $bindings);
    }

    /**
     * Specify relationships to eager-load when fetching entities.
     *
     * Validates each relationship name against entity metadata, then merges
     * with any previously declared relationships, deduplicating names.
     *
     * @throws RepositoryException When the relationship name is unknown
     */
    public function with(string ...$relationships): static
    {
        foreach ($relationships as $name) {
            $topLevel = explode('.', $name, 2)[0];

            if ($this->metadata->getRelationship($topLevel) === null) {
                throw RepositoryException::unknownRelationship(
                    $this->entityClass,
                    $this->entityClass,
                    $name,
                );
            }
        }

        $this->relationships = array_values(
            array_unique(array_merge($this->relationships, $relationships)),
        );

        return $this;
    }

    /**
     * Apply query specifications to the entity query builder and return an EntityCollection.
     *
     * Specs receive $this (the RepositoryQueryBuilder) so they can call with()
     * to declare eager loading alongside other query modifiers.
     *
     * @return EntityCollection<Entity>
     */
    public function matching(QuerySpecification ...$specifications): EntityCollection
    {
        foreach ($specifications as $specification) {
            $specification->apply($this);
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
     * Supports dot-notation for nested eager loading (e.g. 'comments.author').
     *
     * @param Entity[] $entities
     */
    private function eagerLoadRelationships(array $entities): void
    {
        if ($this->relationships === [] || $this->relationshipLoader === null || $entities === []) {
            return;
        }

        $tree = RelationshipLoader::parseRelationshipTree($this->relationships);
        $this->relationshipLoader->loadNested($entities, $tree, $this->metadata);
    }
}
