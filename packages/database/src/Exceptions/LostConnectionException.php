<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use PDOException;

/**
 * The connection to the database server is gone (server restart, killed
 * session, network drop).
 */
class LostConnectionException extends QueryException
{
    private const string MESSAGE_PREFIX = 'Database connection lost: ';

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
            message: self::MESSAGE_PREFIX . $driverMessage,
            sql: $sql,
            bindings: $bindings,
            sqlState: self::sqlStateOf($previous),
            context: self::contextFor($sql, $bindings),
            suggestion: 'The connection is re-established by the next statement outside a transaction; '
                . 'if this keeps happening, check that the database server is reachable and that '
                . 'nothing is killing idle sessions',
            previous: $previous,
        );
    }

    /**
     * The same loss, reported for a connection that had a transaction open.
     */
    public static function duringTransaction(
        self $lost,
    ): self {
        $driverMessage = substr($lost->getMessage(), strlen(self::MESSAGE_PREFIX));

        return new self(
            message: 'Database connection lost inside a transaction: ' . $driverMessage,
            sql: $lost->sql(),
            bindings: $lost->bindings(),
            sqlState: $lost->sqlState(),
            context: $lost->getContext(),
            suggestion: 'The server has ended the transaction. Roll it back (rollback(), or let '
                . 'transaction() do it) before running more statements, then retry the whole unit of '
                . 'work from the start. If the lost statement was COMMIT, the commit may or may not '
                . 'have completed: check before retrying',
            previous: $lost->getPrevious(),
        );
    }

    /**
     * A statement was attempted on a transaction whose connection was lost
     * earlier. Nothing is sent to the server.
     */
    public static function transactionAlreadyLost(): self
    {
        return new self(
            message: 'Database connection was lost earlier in this transaction; no statement can run '
                . 'until the transaction is rolled back',
            context: 'While running a statement on a transaction whose connection was lost',
            suggestion: 'Call rollback() on every open transaction level (or let transaction() do it); '
                . 'the next statement after the outermost rollback reconnects',
        );
    }
}
