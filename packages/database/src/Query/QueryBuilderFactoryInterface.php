<?php

declare(strict_types=1);

namespace Marko\Database\Query;

/**
 * Factory for creating fresh QueryBuilderInterface instances.
 *
 * Each call to create() returns a new, independent query builder
 * so that queries don't share mutable state.
 */
interface QueryBuilderFactoryInterface
{
    /**
     * Create a new QueryBuilderInterface instance.
     */
    public function create(): QueryBuilderInterface;
}
