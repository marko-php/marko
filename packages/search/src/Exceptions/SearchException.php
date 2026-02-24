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
}
