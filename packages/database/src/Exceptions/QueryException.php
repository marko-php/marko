<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use PDOException;
use Throwable;

/**
 * A SQL statement failed in the database driver.
 *
 * Driver packages translate the raw PDOException into this class, or into a
 * ConstraintViolationException subclass for integrity violations, so
 * application code never has to inspect driver-specific SQLSTATEs. The
 * original PDOException is kept as the previous exception.
 *
 * Binding values are kept for debugging via bindings() but never written
 * into the message or context: string bindings are redacted from the driver
 * text, and driver DETAIL lines (which echo row data) are dropped.
 */
class QueryException extends DatabaseException
{
    private const string REDACTED = '[redacted]';

    private const int MIN_BARE_REDACTION_LENGTH = 4;

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function __construct(
        string $message,
        private readonly string $sql = '',
        private readonly array $bindings = [],
        private readonly ?string $sqlState = null,
        string $context = '',
        string $suggestion = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            context: $context,
            suggestion: $suggestion,
            previous: $previous,
        );
    }

    /**
     * Wrap a driver error that is not a recognised constraint violation.
     *
     * @param array<int|string, mixed> $bindings
     */
    public static function fromDriverError(
        PDOException $previous,
        string $sql,
        array $bindings,
    ): self {
        $sqlState = self::sqlStateOf($previous);
        $driverMessage = self::redact(self::firstLine($previous->getMessage()), $bindings);

        return new self(
            message: "Query failed: $driverMessage",
            sql: $sql,
            bindings: $bindings,
            sqlState: $sqlState,
            context: self::contextFor($sql, $bindings),
            suggestion: 'Check the SQL statement and its bindings; the original PDOException is available '
                . 'via getPrevious()',
            previous: $previous,
        );
    }

    /**
     * The SQL statement that failed, with its placeholders.
     */
    public function sql(): string
    {
        return $this->sql;
    }

    /**
     * The values bound to the statement. Never included in the message.
     *
     * @return array<int|string, mixed>
     */
    public function bindings(): array
    {
        return $this->bindings;
    }

    /**
     * The five-character SQLSTATE reported by the driver, when known.
     */
    public function sqlState(): ?string
    {
        return $this->sqlState;
    }

    /**
     * The SQLSTATE of a PDOException: errorInfo[0] when the driver filled it,
     * otherwise the string exception code.
     */
    public static function sqlStateOf(
        PDOException $exception,
    ): ?string {
        $sqlState = $exception->errorInfo[0] ?? $exception->getCode();

        return (is_string($sqlState) && $sqlState !== '')
            ? $sqlState
            : null;
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    protected static function contextFor(
        string $sql,
        array $bindings,
    ): string {
        $count = count($bindings);

        return "While running SQL: $sql (with $count binding(s), values redacted)";
    }

    /**
     * Replace string bindings (and JSON-encoded array bindings) found in the
     * text with a placeholder. A value the driver quotes ('v' or "v") is
     * always replaced; a bare occurrence only when the value is at least
     * MIN_BARE_REDACTION_LENGTH characters, so a short ID bound as a string
     * cannot mangle SQLSTATE codes or ordinary words. Numbers, booleans and
     * nulls are left alone.
     *
     * @param array<int|string, mixed> $bindings
     */
    protected static function redact(
        string $text,
        array $bindings,
    ): string {
        $replacements = [];

        foreach ($bindings as $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            if (
                !is_string($value)
                || $value === ''
            ) {
                continue;
            }

            $replacements["'$value'"] = "'" . self::REDACTED . "'";
            $replacements["\"$value\""] = '"' . self::REDACTED . '"';

            if (strlen($value) >= self::MIN_BARE_REDACTION_LENGTH) {
                $replacements[$value] = self::REDACTED;
            }
        }

        // strtr() tries the longest keys first and never rescans replaced text.
        return strtr($text, $replacements);
    }

    /**
     * The first line of a driver message. Later lines (PostgreSQL DETAIL,
     * LINE and HINT) echo row data and literal SQL fragments.
     */
    protected static function firstLine(
        string $message,
    ): string {
        return trim(strtok($message, "\n") ?: $message);
    }
}
