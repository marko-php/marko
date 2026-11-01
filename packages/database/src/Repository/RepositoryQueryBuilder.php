<?php

declare(strict_types=1);

namespace Marko\Database\Repository;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\RelationshipLoader;
use Marko\Database\Exceptions\InvalidColumnException;
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

    /**
     * The table name or alias that qualifies this query's columns in a whereHas() subquery.
     */
    private string $tableReference;

    /**
     * @param RelationshipExistsCompiler|null $relationshipExistsCompiler Builds whereHas()/whereDoesntHave()
     *     subqueries; Repository::query() and matching() pass one, and those methods throw without it
     */
    public function __construct(
        private readonly QueryBuilderInterface $queryBuilder,
        private readonly EntityHydrator $hydrator,
        private readonly EntityMetadata $metadata,
        private readonly string $entityClass,
        private readonly ?RelationshipLoader $relationshipLoader = null,
        private readonly ?RelationshipExistsCompiler $relationshipExistsCompiler = null,
    ) {
        $this->tableReference = $this->metadata->tableName;
    }

    public function table(
        string $table,
        ?string $alias = null,
    ): static {
        $this->queryBuilder->table($table, $alias);
        $this->tableReference = $alias ?? $table;

        return $this;
    }

    public function select(
        string ...$columns,
    ): static {
        $this->queryBuilder->select(...$columns);

        return $this;
    }

    /**
     * @throws InvalidColumnException
     */
    public function selectRaw(
        string $expression,
        array $bindings = [],
    ): static {
        $this->queryBuilder->selectRaw($expression, $bindings);

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

    public function whereNotIn(
        string $column,
        array $values,
    ): static {
        $this->queryBuilder->whereNotIn($column, $values);

        return $this;
    }

    public function whereColumn(
        string $first,
        string $operator,
        string $second,
    ): static {
        $this->queryBuilder->whereColumn($first, $operator, $second);

        return $this;
    }

    public function whereExists(
        callable|QueryBuilderInterface $subquery,
    ): static {
        $this->queryBuilder->whereExists($subquery);

        return $this;
    }

    public function whereNotExists(
        callable|QueryBuilderInterface $subquery,
    ): static {
        $this->queryBuilder->whereNotExists($subquery);

        return $this;
    }

    /**
     * @throws RepositoryException When a relationship in the path is not declared on its entity
     */
    public function whereHas(
        string $relation,
        ?callable $constraint = null,
    ): static {
        $this->queryBuilder->whereExists(
            $this->relationshipSubquery($relation, $constraint, "whereHas('$relation')"),
        );

        return $this;
    }

    /**
     * @throws RepositoryException When a relationship in the path is not declared on its entity
     */
    public function whereDoesntHave(
        string $relation,
        ?callable $constraint = null,
    ): static {
        $this->queryBuilder->whereNotExists(
            $this->relationshipSubquery($relation, $constraint, "whereDoesntHave('$relation')"),
        );

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

    public function whereJsonContains(
        string $path,
        mixed $value,
    ): static {
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

    /**
     * @throws InvalidColumnException
     */
    public function whereRaw(
        string $expression,
        array $bindings = [],
    ): static {
        $this->queryBuilder->whereRaw($expression, $bindings);

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

    public function having(
        string $expression,
        array $bindings = [],
    ): static {
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

    public function orderByRaw(
        string $expression,
        string $direction = 'ASC',
    ): static {
        $this->queryBuilder->orderByRaw($expression, $direction);

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
        ?string $primaryKey = null,
    ): int {
        return $this->queryBuilder->insert($data, $primaryKey);
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

    public function lockForUpdate(): static
    {
        $this->queryBuilder->lockForUpdate();

        return $this;
    }

    public function sharedLock(): static
    {
        $this->queryBuilder->sharedLock();

        return $this;
    }

    public function skipLocked(): static
    {
        $this->queryBuilder->skipLocked();

        return $this;
    }

    public function noWait(): static
    {
        $this->queryBuilder->noWait();

        return $this;
    }

    public function upsert(
        array $rows,
        array $uniqueBy,
        ?array $update = null,
    ): int {
        return $this->queryBuilder->upsert($rows, $uniqueBy, $update);
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
                    $topLevel,
                    array_keys($this->metadata->getRelationships()),
                    "with('$name')",
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
     * Build the correlated subquery for a (possibly dotted) relationship path.
     *
     * The whole path is validated first, so an unknown later segment fails before
     * anything is built. A dotted path nests: the first relationship's subquery
     * gets whereHas() for the rest of the path, through a builder scoped to the
     * related entity and qualified by its generated alias.
     *
     * @throws RepositoryException
     */
    private function relationshipSubquery(
        string $path,
        ?callable $constraint,
        string $call,
    ): QueryBuilderInterface {
        $compiler = $this->relationshipExistsCompiler
            ?? throw RepositoryException::relationshipFiltersNotConfigured($this->entityClass);

        $compiler->assertRelationshipPath($this->metadata, $path, $call);

        $segments = explode('.', $path, 2);
        $remainingPath = $segments[1] ?? null;

        return $compiler->compile(
            $this->metadata,
            $this->tableReference,
            $segments[0],
            $call,
            function (
                QueryBuilderInterface $subquery,
                EntityMetadata $relatedMetadata,
                string $relatedAlias,
            ) use (
                $compiler,
                $remainingPath,
                $constraint,
            ): void {
                if ($remainingPath === null && $constraint === null) {
                    return;
                }

                $scoped = new self(
                    $subquery,
                    $this->hydrator,
                    $relatedMetadata,
                    $relatedMetadata->entityClass,
                    null,
                    $compiler,
                );
                $scoped->tableReference = $relatedAlias;

                if ($remainingPath !== null) {
                    $scoped->whereHas($remainingPath, $constraint);

                    return;
                }

                $constraint($scoped);
            },
        );
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
