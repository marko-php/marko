<?php

declare(strict_types=1);

namespace Marko\Database\Query;

/**
 * Interface for query specifications that encapsulate query logic.
 *
 * Specifications are composable, named query objects that replace
 * Eloquent scopes with explicit, testable query logic.
 */
interface QuerySpecification
{
    /**
     * Apply this specification to the query builder.
     *
     * @param QueryBuilderInterface $builder The query builder to modify
     */
    public function apply(QueryBuilderInterface $builder): void;
}
