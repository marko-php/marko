<?php

declare(strict_types=1);

namespace Marko\Scope\Query;

use Marko\Database\Exceptions\InvalidColumnException;

/**
 * Implemented by database drivers to emit DB-specific SQL for scope-aware sorting.
 *
 * Renderers must produce a COALESCE expression that mirrors the PHP walker
 * resolution order: iterate through $expression->paths in the order they are
 * declared (declared-axis-priority + deepest-first walk), wrapping each
 * JSON-path lookup in the outermost COALESCE, with the plain column as the
 * final fallback.
 *
 * Example output:
 *   COALESCE(
 *     JSON_UNQUOTE(JSON_EXTRACT(`scopes`, '$.store.en.price')),
 *     JSON_UNQUOTE(JSON_EXTRACT(`scopes`, '$.store.price')),
 *     `price`
 *   )
 */
interface ScopeSortRendererInterface
{
    /**
     * @throws InvalidColumnException When an identifier in the expression is invalid
     */
    public function render(ScopeSortExpression $expression): string;
}
