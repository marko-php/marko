<?php

declare(strict_types=1);

namespace Marko\Pagination\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class PaginationException extends MarkoException
{
    public static function invalidPage(
        int $page,
    ): self {
        return new self(
            message: "Invalid page number: $page",
            context: 'Page number must be a positive integer (1 or greater)',
            suggestion: 'Provide a page number >= 1',
        );
    }

    public static function invalidPerPage(
        int $perPage,
    ): self {
        return new self(
            message: "Invalid per-page value: $perPage",
            context: 'Per-page must be a positive integer (1 or greater)',
            suggestion: 'Provide a per-page value >= 1',
        );
    }

    public static function invalidCursor(
        string $encoded,
    ): self {
        return new self(
            message: 'Invalid cursor string provided',
            context: "The cursor string could not be decoded: '$encoded'",
            suggestion: 'Cursor strings must be valid base64-encoded JSON objects. Do not modify cursor values returned by the API.',
        );
    }
}
