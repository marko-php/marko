<?php

declare(strict_types=1);

namespace Marko\Database\Query;

use Marko\Database\Exceptions\InvalidColumnException;

/**
 * Validates and parses SQL identifiers and SELECT column expressions.
 *
 * Shared by QueryBuilder drivers, aggregate builders, and GROUP BY support.
 */
class IdentifierValidator
{
    /**
     * Known aggregate function names that are allowed in SELECT expressions.
     */
    public const array AGGREGATE_FUNCTIONS = [
        'COUNT',
        'SUM',
        'MIN',
        'MAX',
        'AVG',
    ];

    private const string IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    private const string QUALIFIED_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*$/';

    private const string AGGREGATE_PATTERN = '/^(COUNT|SUM|MIN|MAX|AVG)\(\s*(\*|[a-zA-Z_][a-zA-Z0-9_]*)\s*\)$/i';

    /**
     * Parse a SELECT column expression into its column and optional alias parts.
     *
     * Accepted forms:
     *  - "name"                     → plain identifier
     *  - "users.name"               → qualified identifier
     *  - "name as alias"            → identifier with alias (case-insensitive AS)
     *  - "COUNT(*) as total"        → aggregate with alias
     *
     * @param string $expression The raw SELECT expression
     * @return array{column: string, alias: ?string}
     *
     * @throws InvalidColumnException When the expression or alias is invalid
     */
    public static function parseSelectExpression(
        string $expression,
    ): array {
        self::rejectDangerousPatterns($expression);

        // Split on the AS keyword (case-insensitive), allowing surrounding whitespace
        $parts = preg_split('/\s+[Aa][Ss]\s+/', $expression, 2);

        if ($parts === false) {
            throw InvalidColumnException::invalidColumn($expression);
        }

        $column = trim($parts[0]);
        $alias = isset($parts[1]) ? trim($parts[1]) : null;

        self::validateColumnPart($column, $expression);

        if ($alias !== null) {
            self::validateAlias($alias);
        }

        return [
            'column' => $column,
            'alias' => $alias,
        ];
    }

    /**
     * Check whether the given string is a valid plain identifier.
     */
    public static function isValidIdentifier(
        string $identifier,
    ): bool {
        return (bool) preg_match(self::IDENTIFIER_PATTERN, $identifier);
    }

    /**
     * Reject expressions containing SQL injection patterns such as comments and semicolons.
     *
     * @throws InvalidColumnException
     */
    private static function rejectDangerousPatterns(
        string $expression,
    ): void {
        if (
            str_contains($expression, ';')
            || str_contains($expression, '--')
            || str_contains($expression, '/*')
            || str_contains($expression, '*/')
        ) {
            throw InvalidColumnException::invalidColumn($expression);
        }
    }

    /**
     * Validate the column part of a SELECT expression (before AS).
     *
     * @throws InvalidColumnException
     */
    private static function validateColumnPart(
        string $column,
        string $fullExpression,
    ): void {
        // Plain identifier
        if (preg_match(self::IDENTIFIER_PATTERN, $column)) {
            return;
        }

        // Qualified identifier (table.column)
        if (preg_match(self::QUALIFIED_PATTERN, $column)) {
            return;
        }

        // Known aggregate function
        if (preg_match(self::AGGREGATE_PATTERN, $column)) {
            return;
        }

        throw InvalidColumnException::invalidColumn($fullExpression);
    }

    /**
     * Validate an alias identifier.
     *
     * @throws InvalidColumnException
     */
    private static function validateAlias(
        string $alias,
    ): void {
        if (!preg_match(self::IDENTIFIER_PATTERN, $alias)) {
            throw InvalidColumnException::invalidAlias($alias);
        }
    }
}
