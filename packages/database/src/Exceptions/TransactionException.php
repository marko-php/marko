<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown for transaction-related errors.
 */
class TransactionException extends MarkoException
{
    public static function nestedTransactionNotSupported(): self
    {
        return new self(
            message: 'Nested transactions are not supported',
            context: 'A transaction is already in progress',
            suggestion: 'Commit or rollback the current transaction before starting a new one',
        );
    }

    public static function notInTransaction(): self
    {
        return new self(
            message: 'No active transaction',
            context: 'Attempted to commit or rollback when no transaction is active',
            suggestion: 'Call beginTransaction() before commit() or rollback()',
        );
    }
}
