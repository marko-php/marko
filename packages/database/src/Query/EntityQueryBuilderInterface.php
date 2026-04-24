<?php

declare(strict_types=1);

namespace Marko\Database\Query;

/**
 * Interface for building entity-level queries with eager-loading support.
 *
 * Extends QueryBuilderInterface with the ability to declare relationship
 * eager loads via with(). This is the contract that QuerySpecification
 * implementations work against — specs operate on entity queries, never
 * raw query builders.
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
}
