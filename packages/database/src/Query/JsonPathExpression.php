<?php

declare(strict_types=1);

namespace Marko\Database\Query;

/**
 * Represents a parsed JSON path expression (e.g. "data->user->name" or "data->>name").
 */
readonly class JsonPathExpression
{
    /**
     * @param string   $column   The base column name (e.g. "data")
     * @param string[] $segments The path segments after the base column (e.g. ["user", "name"])
     * @param string   $operator "->" for JSON-typed result, "->>" for text result
     */
    public function __construct(
        public string $column,
        public array $segments,
        public string $operator,
    ) {}
}
