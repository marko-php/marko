<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Connection;

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
 * Turns a PDOException raised by MySQL or MariaDB into Marko's typed query
 * exceptions, keyed on the server error number (errorInfo[1]), since MySQL
 * reports every integrity violation as SQLSTATE 23000:
 *
 * - 1062 duplicate entry                       → UniqueConstraintViolationException
 * - 1451, 1452 (and legacy 1216, 1217) FK fail → ForeignKeyConstraintViolationException
 * - 1048 column cannot be null,
 *   1364 field has no default value            → NotNullConstraintViolationException
 * - 3819 (MySQL), 4025 (MariaDB) check failed  → CheckConstraintViolationException
 * - 1213 deadlock found (SQLSTATE 40001, also
 *   how InnoDB reports serialization conflicts) → DeadlockException
 * - 1020 record has changed since last read
 *   (ER_CHECKREAD: MariaDB raises it under
 *   innodb_snapshot_isolation=ON)              → SerializationFailureException
 * - 1205 lock wait timeout exceeded,
 *   3572 lock not acquired with NOWAIT         → LockTimeoutException
 * - 2006 server has gone away, 2013 lost connection
 *   during query, 2055 lost connection at a named
 *   stage, 4031 disconnected for inactivity (MySQL
 *   8.0.24+), 1927 connection killed (MariaDB),
 *   1053 server shutdown in progress; with no error
 *   number, a message saying the server has gone
 *   away or the connection was lost              → LostConnectionException
 * - anything else                              → QueryException
 *
 * The constraint, table and column are parsed from the server message.
 * table() is the table the constraint is defined on. The duplicate value in
 * a 1062 message is never copied into the exception.
 */
class MySqlExceptionTranslator
{
    /**
     * @param array<int|string, mixed> $bindings
     */
    public function translate(
        PDOException $exception,
        string $sql,
        array $bindings,
    ): QueryException {
        $serverMessage = $this->serverMessage($exception);

        return match ($this->driverCode($exception)) {
            1062 => $this->uniqueViolation($exception, $sql, $bindings, $serverMessage),
            1216, 1217, 1451, 1452 => ForeignKeyConstraintViolationException::fromDriverError(
                previous: $exception,
                sql: $sql,
                bindings: $bindings,
                constraintName: $this->match('/CONSTRAINT `([^`]+)`/', $serverMessage),
                table: $this->match('/\(`[^`]+`\.`([^`]+)`, CONSTRAINT/', $serverMessage)
                    ?? $this->tableFromSql($sql),
                column: $this->match('/FOREIGN KEY \(`([^`]+)`\)/', $serverMessage),
            ),
            1048, 1364 => NotNullConstraintViolationException::fromDriverError(
                previous: $exception,
                sql: $sql,
                bindings: $bindings,
                table: $this->tableFromSql($sql),
                column: $this->match("/(?:Column|Field) '([^']+)'/", $serverMessage),
            ),
            3819 => CheckConstraintViolationException::fromDriverError(
                previous: $exception,
                sql: $sql,
                bindings: $bindings,
                constraintName: $this->match("/Check constraint '([^']+)'/", $serverMessage),
                table: $this->tableFromSql($sql),
            ),
            4025 => CheckConstraintViolationException::fromDriverError(
                previous: $exception,
                sql: $sql,
                bindings: $bindings,
                constraintName: $this->match('/CONSTRAINT `([^`]+)` failed/', $serverMessage),
                table: $this->match('/failed for `[^`]+`\.`([^`]+)`/', $serverMessage) ?? $this->tableFromSql($sql),
            ),
            1213 => DeadlockException::fromDriverError($exception, $sql, $bindings),
            1020 => SerializationFailureException::fromDriverError($exception, $sql, $bindings),
            1205, 3572 => LockTimeoutException::fromDriverError($exception, $sql, $bindings),
            1053, 1927, 2006, 2013, 2055, 4031 => LostConnectionException::fromDriverError(
                $exception,
                $sql,
                $bindings,
            ),
            null => $this->unclassified($exception, $sql, $bindings, $serverMessage),
            default => QueryException::fromDriverError($exception, $sql, $bindings),
        };
    }

    /**
     * A PDOException with no usable error number: only the message can tell
     * whether the server connection is gone.
     *
     * @param array<int|string, mixed> $bindings
     */
    private function unclassified(
        PDOException $exception,
        string $sql,
        array $bindings,
        string $serverMessage,
    ): QueryException {
        if (
            stripos($serverMessage, 'server has gone away') !== false
            || stripos($serverMessage, 'Lost connection to') !== false
        ) {
            return LostConnectionException::fromDriverError($exception, $sql, $bindings);
        }

        return QueryException::fromDriverError($exception, $sql, $bindings);
    }

    /**
     * MySQL 8.0.19+ prefixes the key with its table ("users.users_email_unique");
     * older servers and MariaDB report the bare key name. The key is read
     * from the end of the message so the duplicate value before it is never
     * mistaken for it.
     *
     * @param array<int|string, mixed> $bindings
     */
    private function uniqueViolation(
        PDOException $exception,
        string $sql,
        array $bindings,
        string $serverMessage,
    ): UniqueConstraintViolationException {
        $key = $this->match("/for key '([^']+)'\\s*$/", $serverMessage);
        $table = null;

        if (
            $key !== null
            && str_contains($key, '.')
        ) {
            [$table, $key] = explode('.', $key, 2);
        }

        return UniqueConstraintViolationException::fromDriverError(
            previous: $exception,
            sql: $sql,
            bindings: $bindings,
            constraintName: $key,
            table: $table ?? $this->tableFromSql($sql),
        );
    }

    /**
     * The MySQL error number: errorInfo[1] when filled, otherwise parsed from
     * "SQLSTATE[23000]: Integrity constraint violation: 1062 ...".
     */
    private function driverCode(
        PDOException $exception,
    ): ?int {
        $driverCode = $exception->errorInfo[1] ?? null;

        if (is_int($driverCode)) {
            return $driverCode;
        }

        $parsed = $this->match('/^SQLSTATE\[\w+]: [^:]+: (\d+) /', $exception->getMessage());

        return $parsed !== null ? (int) $parsed : null;
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
     * The target table of an INSERT, UPDATE or DELETE, for messages that do
     * not name it.
     */
    private function tableFromSql(
        string $sql,
    ): ?string {
        return $this->match('/^\s*(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?([A-Za-z_][\w.]*)`?/i', $sql);
    }

    private function match(
        string $pattern,
        string $subject,
    ): ?string {
        return preg_match($pattern, $subject, $matches) === 1 ? $matches[1] : null;
    }
}
