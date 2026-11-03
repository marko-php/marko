<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Thrown when a query builder feature cannot be expressed on SQLite.
 */
class UnsupportedFeatureException extends MarkoException
{
    public static function jsonObjectContainment(): self
    {
        return new self(
            message: 'whereJsonContains() on the SQLite driver supports a scalar or a list of scalars, not an object or nested array',
            context: 'Compiling whereJsonContains() for SQLite, which has no JSON object containment operator',
            suggestion: 'Compare a single path with where(\'column->>key\', \'=\', $value), or use whereRaw() with json_extract()',
        );
    }
}
