<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Connection;

use Marko\Database\Exceptions\CheckConstraintViolationException;
use Marko\Database\Exceptions\DeadlockException;
use Marko\Database\Exceptions\ForeignKeyConstraintViolationException;
use Marko\Database\Exceptions\LockTimeoutException;
use Marko\Database\Exceptions\LostConnectionException;
use Marko\Database\Exceptions\NotNullConstraintViolationException;
use Marko\Database\Exceptions\QueryException;
use Marko\Database\Exceptions\SerializationFailureException;
use Marko\Database\Exceptions\UniqueConstraintViolationException;
use PDOException;

/**
 * Turns a PDOException raised by PostgreSQL into Marko's typed query
 * exceptions, keyed on the SQLSTATE:
 *
 * - 23505 unique_violation      → UniqueConstraintViolationException
 * - 23503 foreign_key_violation → ForeignKeyConstraintViolationException
 * - 23502 not_null_violation    → NotNullConstraintViolationException
 * - 23514 check_violation       → CheckConstraintViolationException
 * - 40P01 deadlock_detected     → DeadlockException
 * - 40001 serialization_failure → SerializationFailureException
 * - 55P03 lock_not_available    → LockTimeoutException (lock_timeout, NOWAIT)
 * - 08xxx connection_exception, 57P01/57P02/57P03 shutdown and startup,
 *   57P05 and 25P03 idle timeouts, and HY000 with a libpq "connection is
 *   gone" message                → LostConnectionException
 * - anything else               → QueryException (57014 query_canceled
 *   included: the session is still alive)
 *
 * The constraint, table and column are parsed from the server message.
 * table() is the table the constraint is defined on. Values in the DETAIL
 * line are never copied into the exception.
 */
class PgSqlExceptionTranslator
{
    /**
     * SQLSTATEs outside class 08 where the server has ended the session.
     */
    private const array LOST_CONNECTION_SQLSTATES = ['57P01', '57P02', '57P03', '57P05', '25P03'];

    /**
     * Server messages (matched case-insensitively) that mark an HY000 error
     * as a lost connection.
     */
    private const array LOST_CONNECTION_MESSAGES = [
        'no connection to the server',
        'server closed the connection unexpectedly',
        'terminating connection due to',
        'SSL connection has been closed unexpectedly',
        'SSL SYSCALL error',
        'could not receive data from server',
        'could not send data to server',
        'connection to server',
    ];

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function translate(
        PDOException $exception,
        string $sql,
        array $bindings,
    ): QueryException {
        if ($this->isLostConnection($exception)) {
            return LostConnectionException::fromDriverError($exception, $sql, $bindings);
        }

        $serverMessage = $this->serverMessage($exception);
        $constraintName = $this->match('/constraint "([^"]+)"/', $serverMessage);
        $table = $this->constraintTable($serverMessage) ?? $this->tableFromSql($sql);

        return match (QueryException::sqlStateOf($exception)) {
            '23505' => UniqueConstraintViolationException::fromDriverError(
                previous: $exception,
                sql: $sql,
                bindings: $bindings,
                constraintName: $constraintName,
                table: $table,
                column: $this->match('/Key \(([^)]+)\)=/', $serverMessage),
            ),
            '23503' => ForeignKeyConstraintViolationException::fromDriverError(
                previous: $exception,
                sql: $sql,
                bindings: $bindings,
                constraintName: $constraintName,
                table: $table,
            ),
            '23502' => NotNullConstraintViolationException::fromDriverError(
                previous: $exception,
                sql: $sql,
                bindings: $bindings,
                table: $table,
                column: $this->match('/null value in column "([^"]+)"/', $serverMessage),
            ),
            '23514' => CheckConstraintViolationException::fromDriverError(
                previous: $exception,
                sql: $sql,
                bindings: $bindings,
                constraintName: $constraintName,
                table: $table,
            ),
            '40P01' => DeadlockException::fromDriverError($exception, $sql, $bindings),
            '40001' => SerializationFailureException::fromDriverError($exception, $sql, $bindings),
            '55P03' => LockTimeoutException::fromDriverError($exception, $sql, $bindings),
            default => QueryException::fromDriverError($exception, $sql, $bindings),
        };
    }

    private function isLostConnection(
        PDOException $exception,
    ): bool {
        $sqlState = QueryException::sqlStateOf($exception) ?? '';

        return str_starts_with($sqlState, '08')
            || in_array($sqlState, self::LOST_CONNECTION_SQLSTATES, true)
            || (
                $sqlState === 'HY000'
                && $this->mentionsLostConnection($this->serverMessage($exception))
            );
    }

    /**
     * pdo_pgsql reports a dead socket as HY000 with libpq's own wording,
     * because libpq has no result to read a SQLSTATE from.
     */
    private function mentionsLostConnection(
        string $serverMessage,
    ): bool {
        return array_any(
            self::LOST_CONNECTION_MESSAGES,
            fn (string $fragment): bool => stripos($serverMessage, $fragment) !== false,
        );
    }

    private function serverMessage(
        PDOException $exception,
    ): string {
        $serverMessage = $exception->errorInfo[2] ?? null;

        return (is_string($serverMessage) && $serverMessage !== '')
            ? $serverMessage
            : $exception->getMessage();
    }

    /**
     * The table that owns the constraint. A foreign key violation raised by
     * a DELETE names the parent table first and the constraint's own table
     * last ("... on table "users" violates foreign key constraint "x" on
     * table "posts""), so the last mention wins.
     */
    private function constraintTable(
        string $serverMessage,
    ): ?string {
        $firstLine = strtok($serverMessage, "\n") ?: $serverMessage;

        if (preg_match_all('/(?:on table|of relation|for relation) "([^"]+)"/', $firstLine, $matches) > 0) {
            return array_last($matches[1]);
        }

        return null;
    }

    /**
     * The target table of an INSERT, UPDATE or DELETE, for messages that do
     * not name it (unique violations, and not-null violations on servers
     * older than PostgreSQL 13).
     */
    private function tableFromSql(
        string $sql,
    ): ?string {
        return $this->match('/^\s*(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+"?([A-Za-z_][\w.]*)"?/i', $sql);
    }

    private function match(
        string $pattern,
        string $subject,
    ): ?string {
        return preg_match($pattern, $subject, $matches) === 1 ? $matches[1] : null;
    }
}
