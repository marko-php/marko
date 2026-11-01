<?php

declare(strict_types=1);

namespace Marko\Database\Query;

use Marko\Database\Exceptions\RepositoryException;

/**
 * Interface for building entity-level queries with relationship support.
 *
 * Extends QueryBuilderInterface with relationship eager loads (with()) and
 * relationship existence filters (whereHas(), whereDoesntHave()). This is the
 * contract that QuerySpecification implementations work against — specs
 * operate on entity queries, never raw query builders.
 */
interface EntityQueryBuilderInterface extends QueryBuilderInterface
{
    /**
     * Specify relationships to eager-load when fetching entities.
     *
     * @param string ...$relations Relationship names (supports dot-notation for nested)
     * @return static For fluent chaining
     */
    public function with(string ...$relations): static;

    /**
     * Keep only rows that have at least one related row matching the optional constraint.
     *
     * Compiles to a correlated WHERE EXISTS subquery built from the relationship
     * metadata, so has-many relationships never duplicate rows. Dot-notation
     * ("card.iteration") nests the subqueries; the constraint applies to the
     * last relationship in the path. Every segment is validated.
     *
     * The constraint receives an EntityQueryBuilderInterface scoped to the
     * related entity (unqualified columns refer to the related table), so it can
     * call whereHas() again. Its return value is ignored.
     *
     * @param string $relation Relationship name, or a dotted path of relationship names
     * @param (callable(EntityQueryBuilderInterface): mixed)|null $constraint Conditions on the related rows
     * @return static For fluent chaining
     * @throws RepositoryException When a relationship in the path is not declared on its entity
     */
    public function whereHas(
        string $relation,
        ?callable $constraint = null,
    ): static;

    /**
     * Keep only rows that have no related row matching the optional constraint.
     *
     * The negation of whereHas() (WHERE NOT EXISTS), with the same arguments.
     *
     * @param string $relation Relationship name, or a dotted path of relationship names
     * @param (callable(EntityQueryBuilderInterface): mixed)|null $constraint Conditions on the related rows
     * @return static For fluent chaining
     * @throws RepositoryException When a relationship in the path is not declared on its entity
     */
    public function whereDoesntHave(
        string $relation,
        ?callable $constraint = null,
    ): static;
}
