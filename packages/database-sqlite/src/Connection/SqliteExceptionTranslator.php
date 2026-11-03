<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Connection;

use Marko\Database\Exceptions\CheckConstraintViolationException;
use Marko\Database\Exceptions\ForeignKeyConstraintViolationException;
use Marko\Database\Exceptions\LockTimeoutException;
use Marko\Database\Exceptions\NotNullConstraintViolationException;
use Marko\Database\Exceptions\QueryException;
use Marko\Database\Exceptions\SerializationFailureException;
use Marko\Database\Exceptions\UniqueConstraintViolationException;
use PDOException;

/**
 * Turns a PDOException raised by SQLite into Marko's typed query exceptions,
 * keyed on the SQLite result code (errorInfo[1]) and the message
 * (errorInfo[2]):
 *
 * - 2067 CONSTRAINT_UNIQUE, 1555 CONSTRAINT_PRIMARYKEY → UniqueConstraintViolationException
 * - 787 CONSTRAINT_FOREIGNKEY → ForeignKeyConstraintViolationException
 * - 1299 CONSTRAINT_NOTNULL   → NotNullConstraintViolationException
 * - 275 CONSTRAINT_CHECK      → CheckConstraintViolationException
 * - 5 BUSY, 6 LOCKED          → LockTimeoutException (busy_timeout expired)
 * - 517 BUSY_SNAPSHOT         → SerializationFailureException
 * - anything else             → QueryException
 *
 * pdo_sqlite reports extended result codes only when
 * Pdo\Sqlite::ATTR_EXTENDED_RESULT_CODES is on. With just the primary code 19
 * (SQLITE_CONSTRAINT) the constraint kind is read from the message prefix.
 * Values are never copied into the exception.
 */
class SqliteExceptionTranslator
{
    private const int CONSTRAINT = 19;

    private const int CONSTRAINT_CHECK = 275;

    private const int CONSTRAINT_FOREIGNKEY = 787;

    private const int CONSTRAINT_NOTNULL = 1299;

    private const int CONSTRAINT_PRIMARYKEY = 1555;

    private const int CONSTRAINT_UNIQUE = 2067;

    private const int BUSY = 5;

    private const int LOCKED = 6;

    private const int BUSY_SNAPSHOT = 517;

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function translate(
        PDOException $exception,
        string $sql,
        array $bindings,
    ): QueryException {
        $message = $this->driverMessage($exception);
        $resultCode = $this->resultCode($exception);

        if ($resultCode === self::CONSTRAINT) {
            $resultCode = $this->constraintCodeFromMessage($message);
        }

        return match ($resultCode) {
            self::CONSTRAINT_UNIQUE, self::CONSTRAINT_PRIMARYKEY => $this->unique(
                $exception,
                $sql,
                $bindings,
                $message,
            ),
            self::CONSTRAINT_FOREIGNKEY => ForeignKeyConstraintViolationException::fromDriverError(
                previous: $exception,
                sql: $sql,
                bindings: $bindings,
                table: $this->tableFromSql($sql),
            ),
            self::CONSTRAINT_NOTNULL => $this->notNull($exception, $sql, $bindings, $message),
            self::CONSTRAINT_CHECK => CheckConstraintViolationException::fromDriverError(
                previous: $exception,
                sql: $sql,
                bindings: $bindings,
                constraintName: $this->match('/^CHECK constraint failed: (.+)$/', $message),
                table: $this->tableFromSql($sql),
            ),
            self::BUSY, self::LOCKED => LockTimeoutException::fromDriverError($exception, $sql, $bindings),
            self::BUSY_SNAPSHOT => SerializationFailureException::fromDriverError($exception, $sql, $bindings),
            default => QueryException::fromDriverError($exception, $sql, $bindings),
        };
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    private function unique(
        PDOException $exception,
        string $sql,
        array $bindings,
        string $message,
    ): UniqueConstraintViolationException {
        [$table, $column] = $this->tableAndColumns($message);

        return UniqueConstraintViolationException::fromDriverError(
            previous: $exception,
            sql: $sql,
            bindings: $bindings,
            table: $table ?? $this->tableFromSql($sql),
            column: $column,
        );
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    private function notNull(
        PDOException $exception,
        string $sql,
        array $bindings,
        string $message,
    ): NotNullConstraintViolationException {
        [$table, $column] = $this->tableAndColumns($message);

        return NotNullConstraintViolationException::fromDriverError(
            previous: $exception,
            sql: $sql,
            bindings: $bindings,
            table: $table ?? $this->tableFromSql($sql),
            column: $column,
        );
    }

    private function resultCode(
        PDOException $exception,
    ): ?int {
        $resultCode = $exception->errorInfo[1] ?? null;

        return is_int($resultCode) ? $resultCode : null;
    }

    private function driverMessage(
        PDOException $exception,
    ): string {
        $message = $exception->errorInfo[2] ?? null;

        return is_string($message) && $message !== '' ? $message : $exception->getMessage();
    }

    private function constraintCodeFromMessage(
        string $message,
    ): int {
        return match (true) {
            str_starts_with($message, 'UNIQUE constraint failed'),
            str_contains($message, 'PRIMARY KEY') => self::CONSTRAINT_UNIQUE,
            str_starts_with($message, 'FOREIGN KEY constraint failed') => self::CONSTRAINT_FOREIGNKEY,
            str_starts_with($message, 'NOT NULL constraint failed') => self::CONSTRAINT_NOTNULL,
            str_starts_with($message, 'CHECK constraint failed') => self::CONSTRAINT_CHECK,
            default => self::CONSTRAINT,
        };
    }

    /**
     * Parses "... constraint failed: table.col[, table.col]".
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function tableAndColumns(
        string $message,
    ): array {
        $list = $this->match('/constraint failed: (.+)$/', $message);

        if ($list === null) {
            return [null, null];
        }

        $table = null;
        $columns = [];

        foreach (explode(', ', $list) as $qualified) {
            $dot = strrpos($qualified, '.');
            $table ??= $dot === false ? null : substr($qualified, 0, $dot);
            $columns[] = $dot === false ? $qualified : substr($qualified, $dot + 1);
        }

        return [$table, implode(', ', $columns)];
    }

    /**
     * The target table of an INSERT, UPDATE or DELETE, for messages that do
     * not name it.
     */
    private function tableFromSql(
        string $sql,
    ): ?string {
        return $this->match(
            '/^\s*(?:INSERT\s+(?:OR\s+\w+\s+)?INTO|UPDATE|DELETE\s+FROM)\s+["`\[]?([A-Za-z_][\w.]*)/i',
            $sql,
        );
    }

    private function match(
        string $pattern,
        string $subject,
    ): ?string {
        return preg_match($pattern, $subject, $matches) === 1 ? $matches[1] : null;
    }
}
