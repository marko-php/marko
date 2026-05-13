<?php

declare(strict_types=1);

namespace Marko\Scope\MySql\Query;

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\Query\IdentifierValidator;
use Marko\Scope\Query\ScopeSortExpression;
use Marko\Scope\Query\ScopeSortRendererInterface;

class MySqlScopeSortRenderer implements ScopeSortRendererInterface
{
    /**
     * @throws InvalidColumnException
     */
    public function render(ScopeSortExpression $expression): string
    {
        $this->validate($expression);

        if ($expression->paths === []) {
            return sprintf(
                '`%s` %s',
                $expression->column,
                strtoupper($expression->direction),
            );
        }

        $jsonParts = [];
        foreach ($expression->paths as $pathEntry) {
            $jsonKey = $pathEntry['axis'] . ':' . $pathEntry['path'];
            $jsonParts[] = sprintf(
                'JSON_UNQUOTE(JSON_EXTRACT(`%s`, \'$."%s".%s\'))',
                $expression->jsonColumn,
                $jsonKey,
                $expression->property,
            );
        }

        $jsonParts[] = sprintf('`%s`', $expression->column);

        return sprintf(
            'COALESCE(%s) %s',
            implode(', ', $jsonParts),
            strtoupper($expression->direction),
        );
    }

    /**
     * @throws InvalidColumnException
     */
    private function validate(ScopeSortExpression $expression): void
    {
        if (!IdentifierValidator::isValidIdentifier($expression->column)) {
            throw InvalidColumnException::invalidColumn($expression->column);
        }

        if (!IdentifierValidator::isValidIdentifier($expression->property)) {
            throw InvalidColumnException::invalidColumn($expression->property);
        }

        if (!IdentifierValidator::isValidIdentifier($expression->jsonColumn)) {
            throw InvalidColumnException::invalidColumn($expression->jsonColumn);
        }

        foreach ($expression->paths as $pathEntry) {
            if (!IdentifierValidator::isValidIdentifier($pathEntry['axis'])) {
                throw InvalidColumnException::invalidColumn($pathEntry['axis']);
            }

            foreach (explode('.', $pathEntry['path']) as $segment) {
                if ($segment !== '' && !IdentifierValidator::isValidIdentifier($segment)) {
                    throw InvalidColumnException::invalidColumn($segment);
                }
            }
        }
    }
}
