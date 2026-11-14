<?php

declare(strict_types=1);

namespace Marko\Database\Schema;

use Marko\Database\Exceptions\MigrationException;

/**
 * A column default that is SQL, emitted as written instead of quoted as a string.
 *
 * Use it in an attribute (`#[Column(default: new Expression('gen_random_uuid()'))]`) for any default that is
 * an expression. A plain string default is treated as one only when it is a shortcut (see isShortcut()).
 * Introspectors return every non-literal database default as an Expression.
 */
readonly class Expression
{
    /**
     * The plain string defaults read as expressions: the bare timestamp keywords, with or without a precision
     * (`CURRENT_TIMESTAMP(6)`), and a call to a function without arguments (`gen_random_uuid()`, `NOW()`).
     */
    private const string SHORTCUT_PATTERN =
        '/^(?:(?:CURRENT_TIMESTAMP|CURRENT_TIME|CURRENT_DATE|LOCALTIMESTAMP|LOCALTIME)(?:\(\d*\))?|[a-z_][a-z0-9_]*\(\))$/i';

    /**
     * @throws MigrationException When the SQL is empty
     */
    public function __construct(
        public string $sql,
    ) {
        if (trim($sql) === '') {
            throw MigrationException::emptyDefaultExpression();
        }
    }

    /**
     * Whether a plain string default is read as an expression rather than a string literal.
     */
    public static function isShortcut(
        string $value,
    ): bool {
        return preg_match(self::SHORTCUT_PATTERN, $value) === 1;
    }

    /**
     * Whether two expressions are the same default as far as the schema diff is concerned.
     *
     * Databases report an expression in their own spelling (`NOW()` comes back as `now()`, and MySQL reports
     * `(UUID())` as `uuid()`), so the comparison ignores case, runs of whitespace and parentheses that wrap the
     * whole expression. Anything a database rewrites beyond that (casts, operator spacing) still differs.
     */
    public function equals(
        self $other,
    ): bool {
        return self::normalize($this->sql) === self::normalize($other->sql);
    }

    /**
     * The SQL of an expression as equals() compares it: lowercased, whitespace collapsed, and without
     * parentheses that wrap the whole expression.
     */
    public static function normalize(
        string $sql,
    ): string {
        return self::unwrap(strtolower((string) preg_replace('/\s+/', ' ', $sql)));
    }

    /**
     * The SQL without the parentheses that wrap the whole expression, trimmed, and otherwise unchanged:
     * `((now() + interval 1 day))` becomes `now() + interval 1 day`, while `(a) + (b)` stays as it is.
     */
    public static function unwrap(
        string $sql,
    ): string {
        $sql = trim($sql);

        while (self::isWrappedInOneParenthesisPair($sql)) {
            $sql = trim(substr($sql, 1, -1));
        }

        return $sql;
    }

    /**
     * Whether the first character opens a parenthesis that the last character closes.
     */
    private static function isWrappedInOneParenthesisPair(
        string $sql,
    ): bool {
        if (
            !str_starts_with($sql, '(')
            || !str_ends_with($sql, ')')
        ) {
            return false;
        }

        $depth = 0;
        $lastIndex = strlen($sql) - 1;

        for ($index = 0; $index <= $lastIndex; $index++) {
            if ($sql[$index] === '(') {
                $depth++;
            } elseif ($sql[$index] === ')') {
                $depth--;
            }

            if (
                $depth === 0
                && $index < $lastIndex
            ) {
                return false;
            }
        }

        return $depth === 0;
    }
}
