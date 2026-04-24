<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when two sides of a UNION select different numbers of columns.
 */
class UnionShapeMismatchException extends MarkoException
{
    public static function columnCountMismatch(
        int $leftCount,
        int $rightCount,
    ): self {
        return new self(
            message: "UNION requires both queries to select the same number of columns, but got $leftCount and $rightCount",
            context: 'Compiling a UNION query',
            suggestion: 'Ensure both sides of the UNION select the same number of columns',
        );
    }
}
