<?php

declare(strict_types=1);

namespace Marko\Database\Query;

/**
 * Interface for query specifications that encapsulate query logic.
 *
 * Specifications are composable, named query objects that replace
 * Eloquent scopes with explicit, testable query logic.
 *
 * The builder parameter is typed as EntityQueryBuilderInterface so that
 * specs can call $builder->with('relation') to declare eager loading
 * alongside any other query modifiers (where, orderBy, etc.).
 */
interface QuerySpecification
{
    /**
     * Apply this specification to the entity query builder.
     *
     * @param EntityQueryBuilderInterface $builder The entity query builder to modify
     */
    public function apply(EntityQueryBuilderInterface $builder): void;
}
