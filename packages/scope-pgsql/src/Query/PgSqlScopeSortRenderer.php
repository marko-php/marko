<?php

declare(strict_types=1);

namespace Marko\Scope\PgSql\Query;

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\Query\IdentifierValidator;
use Marko\Scope\Query\ScopeSortExpression;
use Marko\Scope\Query\ScopeSortRendererInterface;

class PgSqlScopeSortRenderer implements ScopeSortRendererInterface
{
    /**
     * @throws InvalidColumnException
     */
    public function render(ScopeSortExpression $expression): string
    {
        $this->validate($expression);

        $direction = strtoupper($expression->direction);

        if ($expression->paths === []) {
            return "\"$expression->column\" $direction";
        }

        $parts = [];

        foreach ($expression->paths as $path) {
            $jsonKey = $path['axis'] . ':' . $path['path'];
            $parts[] = "\"$expression->jsonColumn\"->'$jsonKey'->>'$expression->property'";
        }

        $parts[] = "\"$expression->column\"";
        $coalesce = 'COALESCE(' . implode(', ', $parts) . ')';

        return "$coalesce $direction";
    }

    /**
     * @throws InvalidColumnException
     */
    private function validate(ScopeSortExpression $expression): void
    {
        foreach ([$expression->column, $expression->property, $expression->jsonColumn] as $identifier) {
            if (!IdentifierValidator::isValidIdentifier($identifier)) {
                throw InvalidColumnException::invalidColumn($identifier);
            }
        }

        foreach ($expression->paths as $path) {
            if (!IdentifierValidator::isValidIdentifier($path['axis'])) {
                throw InvalidColumnException::invalidColumn($path['axis']);
            }

            foreach (explode('.', $path['path']) as $segment) {
                if (!IdentifierValidator::isValidIdentifier($segment)) {
                    throw InvalidColumnException::invalidColumn($segment);
                }
            }
        }
    }
}
