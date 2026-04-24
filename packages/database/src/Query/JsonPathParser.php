<?php

declare(strict_types=1);

namespace Marko\Database\Query;

use Marko\Database\Exceptions\InvalidJsonPathException;

/**
 * Parses and validates JSON path expressions used in query builder calls.
 *
 * Supports two operators:
 *   - "->"  returns a JSON-typed value (preserves type)
 *   - "->>" returns a text value (MySQL wraps with JSON_UNQUOTE; PostgreSQL uses ->>)
 *
 * Each segment between operators is validated as a plain SQL identifier via IdentifierValidator.
 */
class JsonPathParser
{
    /**
     * Determine whether the given string contains a JSON path operator.
     */
    public static function isJsonPath(
        string $expression,
    ): bool {
        return str_contains($expression, '->');
    }

    /**
     * Parse a JSON path expression into a JsonPathExpression value object.
     *
     * @throws InvalidJsonPathException When the path contains injection patterns or invalid segments
     */
    public static function parse(
        string $expression,
    ): JsonPathExpression {
        self::rejectDangerousPatterns($expression);

        // Detect whether the terminal operator is ->> or ->
        // We tokenise by splitting on ->> first (longer match wins), then ->
        // Strategy: replace ->> with a sentinel, split on ->, restore
        $operator = str_contains($expression, '->>') ? '->>' : '->';

        // Split on either ->> or -> (longest match first)
        $parts = preg_split('/->>/u', $expression, -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false || count($parts) === 1) {
            // No ->> found; split on ->
            $parts = preg_split('/(?<![>])->(?![>])/u', $expression, -1, PREG_SPLIT_NO_EMPTY);
            $operator = '->';
        }

        if ($parts === false || count($parts) < 2) {
            throw InvalidJsonPathException::invalidPath($expression);
        }

        $column = trim($parts[0]);
        $segments = array_map('trim', array_slice($parts, 1));

        self::validateIdentifier($column, $expression);

        foreach ($segments as $segment) {
            self::validateIdentifier($segment, $expression);
        }

        return new JsonPathExpression(
            column: $column,
            segments: $segments,
            operator: $operator,
        );
    }

    /**
     * @throws InvalidJsonPathException When the expression contains SQL injection patterns
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
            throw InvalidJsonPathException::invalidPath($expression);
        }
    }

    /**
     * @throws InvalidJsonPathException When the identifier fails the whitelist
     */
    private static function validateIdentifier(
        string $identifier,
        string $fullPath,
    ): void {
        if (!IdentifierValidator::isValidIdentifier($identifier)) {
            throw InvalidJsonPathException::invalidSegment($identifier, $fullPath);
        }
    }
}
