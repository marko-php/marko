<?php

declare(strict_types=1);

namespace Marko\Docs\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class DocsException extends MarkoException
{
    public static function pageNotFound(
        string $id,
    ): self {
        return new self(
            message: "Documentation page '$id' not found",
            context: 'While retrieving docs page',
            suggestion: 'Check the page ID is correct and a docs driver is installed',
        );
    }

    public static function searchFailed(
        string $reason,
    ): self {
        return new self(
            message: "Documentation search failed: $reason",
            context: 'While performing docs search',
            suggestion: 'Ensure a docs driver is configured and the index has been built',
        );
    }
}
