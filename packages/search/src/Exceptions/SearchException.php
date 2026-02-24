<?php

declare(strict_types=1);

namespace Marko\Search\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class SearchException extends MarkoException
{
    public static function queryFailed(
        string $query,
        string $reason,
    ): self {
        return new self(
            message: "Search query failed: '$query'",
            context: $reason,
            suggestion: 'Check that the search query is valid and the search engine is available',
        );
    }

    public static function invalidIdentifier(
        string $identifier,
        string $type,
    ): self {
        return new self(
            message: "Invalid $type identifier: '$identifier'",
            context: "The $type '$identifier' contains characters that are not allowed in SQL identifiers",
            suggestion: "Ensure $type names contain only alphanumeric characters and underscores",
        );
    }

    public static function invalidSortDirection(
        string $direction,
    ): self {
        return new self(
            message: "Invalid sort direction: '$direction'",
            context: "Sort direction must be 'asc' or 'desc', got '$direction'",
            suggestion: "Use 'asc' or 'desc' for sort direction",
        );
    }
}
