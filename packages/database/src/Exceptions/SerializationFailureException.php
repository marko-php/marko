<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use PDOException;

/**
 * The database could not serialize this transaction against a concurrent one
 * under REPEATABLE READ or SERIALIZABLE isolation (PostgreSQL SQLSTATE
 * 40001, or MariaDB error 1020 "Record has changed since last read" under
 * innodb_snapshot_isolation=ON, or SQLite result code 517
 * SQLITE_BUSY_SNAPSHOT when a WAL read transaction cannot upgrade to a
 * write). MySQL InnoDB reports most of these
 * conflicts as deadlocks (DeadlockException).
 *
 * Retryable: see TransactionConflictException.
 */
class SerializationFailureException extends TransactionConflictException
{
    /**
     * @param array<int|string, mixed> $bindings
     */
    public static function fromDriverError(
        PDOException $previous,
        string $sql,
        array $bindings,
    ): self {
        $driverMessage = self::redact(self::firstLine($previous->getMessage()), $bindings);

        return new self(
            message: "Serialization failure, the transaction was rolled back: $driverMessage",
            sql: $sql,
            bindings: $bindings,
            sqlState: self::sqlStateOf($previous),
            context: self::contextFor($sql, $bindings),
            suggestion: self::RETRY_SUGGESTION,
            previous: $previous,
        );
    }
}
