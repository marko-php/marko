<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use PDOException;

/**
 * A statement could not get a row or table lock: it waited longer than the
 * lock timeout, or used noWait() on a row another transaction holds
 * (PostgreSQL SQLSTATE 55P03; MySQL errors 1205 and 3572; SQLite result
 * codes 5 SQLITE_BUSY and 6 SQLITE_LOCKED once busy_timeout expires).
 *
 * Not a TransactionConflictException, and never retried by
 * TransactionInterface::transaction(): whether to wait and try again, skip
 * the row, or report "busy" is the caller's decision. On PostgreSQL the
 * error aborts the transaction; on MySQL only the statement fails and the
 * transaction stays open.
 *
 * Not mapped to an HTTP status.
 */
class LockTimeoutException extends QueryException
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
            message: "Could not acquire a lock: $driverMessage",
            sql: $sql,
            bindings: $bindings,
            sqlState: self::sqlStateOf($previous),
            context: self::contextFor($sql, $bindings),
            suggestion: 'Another transaction holds the lock. Catch LockTimeoutException to retry later or '
                . 'report the row as busy, use skipLocked() to skip locked rows, or keep the transactions '
                . 'that hold the lock shorter',
            previous: $previous,
        );
    }
}
