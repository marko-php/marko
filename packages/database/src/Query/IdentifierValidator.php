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
     * Allowed SQL comparison operators.
     */
    public const array OPERATORS = [
        '=',
        '!=',
        '<>',
        '<',
        '>',
        '<=',
        '>=',
        'LIKE',
        'NOT LIKE',
        'IN',
        'NOT IN',
        'IS',
        'IS NOT',
    ];

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
        self::assertNoDangerousPatterns($expression);

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
     * Escape an embedded delimiter character by doubling it.
     *
     * Used to safely embed identifiers inside backtick or double-quote delimiters
     * by doubling any occurrence of the delimiter character within the string.
     */
    public static function escapeDelimiter(
        string $identifier,
        string $delimiter,
    ): string {
        return str_replace($delimiter, $delimiter . $delimiter, $identifier);
    }

    /**
     * Assert that the given string is a valid plain or qualified identifier.
     *
     * Accepts:
     *  - Plain identifiers: /^[a-zA-Z_][a-zA-Z0-9_]*$/
     *  - Qualified identifiers: /^[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*$/
     *
     * @throws InvalidColumnException When the identifier is invalid
     */
    public static function assertValidIdentifier(
        string $identifier,
    ): void {
        if (
            !preg_match(self::IDENTIFIER_PATTERN, $identifier)
            && !preg_match(self::QUALIFIED_PATTERN, $identifier)
        ) {
            throw InvalidColumnException::invalidColumn($identifier);
        }
    }

    /**
     * Assert that the given operator is in the allowlist.
     *
     * @throws InvalidColumnException When the operator is not in the allowlist
     */
    public static function assertValidOperator(
        string $operator,
    ): void {
        if (!in_array($operator, self::OPERATORS, true)) {
            throw InvalidColumnException::invalidOperator($operator);
        }
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
     * Reject raw SQL expressions containing dangerous patterns (statement terminators,
     * comment markers, and backticks).
     *
     * Shared by every method on the QueryBuilder that accepts a raw expression
     * (selectRaw, whereRaw, orderByRaw, having) so the denylist is defined in one
     * place. Bindings for ? placeholders are passed separately and are not
     * subject to this check.
     *
     * @throws InvalidColumnException When the expression contains a dangerous pattern
     */
    public static function assertNoDangerousPatterns(
        string $expression,
    ): void {
        if (
            str_contains($expression, ';')
            || str_contains($expression, '--')
            || str_contains($expression, '/*')
            || str_contains($expression, '*/')
            || str_contains($expression, '`')
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
