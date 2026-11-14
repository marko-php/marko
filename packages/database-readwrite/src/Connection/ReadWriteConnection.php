<?php

declare(strict_types=1);

namespace Marko\Database\ReadWrite\Connection;

use Closure;
use Marko\Core\Contracts\ResettableInterface;
use Marko\Core\Exceptions\MarkoException;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\PendingAfterCommitInterface;
use Marko\Database\Connection\PrimaryReadInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\TransactionConflictException;
use Marko\Database\Exceptions\TransactionException;
use Marko\Database\ReadWrite\Exceptions\ReadException;
use Marko\Database\ReadWrite\Replica\ReplicaSelectorInterface;
use Override;
use PDOException;

class ReadWriteConnection implements
    ConnectionInterface,
    TransactionInterface,
    PendingAfterCommitInterface,
    PrimaryReadInterface,
    ResettableInterface
{
    /**
     * Keywords that start a statement which may write. WITH is included
     * because a CTE can end in INSERT/UPDATE/DELETE/MERGE, and CALL because a
     * stored procedure can write; neither can be told apart from a read
     * without parsing the statement.
     */
    private const string WRITE_KEYWORDS = 'INSERT|UPDATE|DELETE|WITH|MERGE|REPLACE|CALL';

    private bool $stickyWrite = false;

    /**
     * Set by every write routed through this connection. transaction() and
     * onPrimary() read it to decide whether reads stay on the write
     * connection after their callback returns.
     */
    private bool $wrote = false;

    /**
     * @param ConnectionInterface[] $replicas
     */
    public function __construct(
        private ConnectionInterface&TransactionInterface $write,
        private array $replicas,
        private ReplicaSelectorInterface $replicaSelector,
    ) {}

    /**
     * @throws ReadException
     */
    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        if ($this->isWriteStatement($sql)) {
            // A write that returns rows (INSERT ... RETURNING) sticks to the
            // write connection like execute() does, so reads see it.
            $this->markWritten();
        }

        if ($this->stickyWrite) {
            return $this->write->query($sql, $bindings);
        }

        // Try each replica in turn, falling back on PDOException or MarkoException.
        // NOTE: WeightedReplicaSelector weights are based on original indices; they
        // do not rebalance when replicas are removed during fallback (known v1 limitation).
        $remaining = $this->replicas;
        $failures = [];

        while ($remaining !== []) {
            $replica = $this->replicaSelector->select($remaining);

            try {
                return $replica->query($sql, $bindings);
            } catch (PDOException $e) {
                $failures[] = $e->getMessage();
                $remaining = array_values(array_filter($remaining, fn ($r) => $r !== $replica));
            } catch (MarkoException $e) {
                $failures[] = $e->getMessage();
                $remaining = array_values(array_filter($remaining, fn ($r) => $r !== $replica));
            }
        }

        throw ReadException::allReplicasFailed($failures);
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->markWritten();

        return $this->write->execute($sql, $bindings);
    }

    public function prepare(
        string $sql,
    ): StatementInterface {
        if ($this->isWriteStatement($sql)) {
            // The statement runs on the write connection once executed, so
            // reads after it stick there too.
            $this->markWritten();
        }

        return $this->write->prepare($sql);
    }

    public function lastInsertId(): int
    {
        return $this->write->lastInsertId();
    }

    public function driverName(): string
    {
        return $this->write->driverName();
    }

    public function supportsReturning(): bool
    {
        return $this->write->supportsReturning();
    }

    public function quoteIdentifier(
        string $identifier,
    ): string {
        return $this->write->quoteIdentifier($identifier);
    }

    public function connect(): void
    {
        $this->write->connect();
    }

    public function disconnect(): void
    {
        $this->write->disconnect();
    }

    public function isConnected(): bool
    {
        return $this->write->isConnected();
    }

    public function beginTransaction(): void
    {
        $this->stickyWrite = true;
        $this->write->beginTransaction();
    }

    public function commit(): void
    {
        $this->write->commit();
    }

    public function rollback(): void
    {
        $this->write->rollback();
    }

    public function inTransaction(): bool
    {
        return $this->write->inTransaction();
    }

    public function transactionLevel(): int
    {
        return $this->write->transactionLevel();
    }

    /**
     * Routes every read inside the callback to the write connection. When the
     * callback wrote nothing, the sticky flag it had before the call is
     * restored afterwards; when it wrote anything, later reads stay on the
     * write connection so they see the write instead of a lagging replica. A
     * nested transaction() leaves the outer transaction's reads on the write
     * connection either way.
     *
     * $attempts and $backoff are passed to the write connection, which owns
     * the retry and the wait between attempts.
     *
     * @throws TransactionException|TransactionConflictException When $attempts is below 1, $backoff is
     *     negative, or the last attempt still conflicts
     */
    public function transaction(
        callable $callback,
        int $attempts = 1,
        int|Closure|null $backoff = null,
    ): mixed {
        return $this->stickWhile(
            fn (): mixed => $this->write->transaction($callback, $attempts, $backoff),
        );
    }

    /**
     * Routes every read inside the callback to the write connection, for reads
     * that must not see a lagging replica (session lookups, "token used"
     * checks). Routing goes back to what it was afterwards, unless the
     * callback wrote something.
     */
    public function onPrimary(
        callable $callback,
    ): mixed {
        return $this->stickWhile($callback);
    }

    public function afterCommit(
        callable $callback,
    ): void {
        $this->write->afterCommit($callback);
    }

    public function afterRollback(
        callable $callback,
    ): void {
        $this->write->afterRollback($callback);
    }

    /**
     * @throws TransactionException When the write connection cannot run pending callbacks
     */
    public function runPendingAfterCommitCallbacks(): void
    {
        if (!$this->write instanceof PendingAfterCommitInterface) {
            throw TransactionException::cannotRunPendingAfterCommitCallbacks($this->write::class);
        }

        $this->write->runPendingAfterCommitCallbacks();
    }

    public function resetStickyState(): void
    {
        $this->stickyWrite = false;
        $this->wrote = false;
    }

    private function markWritten(): void
    {
        $this->stickyWrite = true;
        $this->wrote = true;
    }

    /**
     * Run the callback with reads on the write connection, then restore the
     * previous sticky flag unless the callback wrote anything.
     */
    private function stickWhile(
        callable $callback,
    ): mixed {
        $wasSticky = $this->stickyWrite;
        $wroteBefore = $this->wrote;
        $this->stickyWrite = true;
        $this->wrote = false;

        try {
            return $callback();
        } finally {
            $wroteInside = $this->wrote;
            $this->wrote = $wroteBefore
                || $wroteInside;
            $this->stickyWrite = $wasSticky
                || $wroteInside;
        }
    }

    /**
     * Rolls back a transaction abandoned by a request that threw before
     * commit()/rollback(), then clears the sticky-write flag.
     *
     * A resettable write connection resets itself (rolling back every level
     * and dropping pending callbacks). Otherwise every open level is rolled
     * back, innermost first, so no savepoint leaves the outer transaction open.
     *
     * The rollback runs first (inside try) and the sticky-state reset
     * runs in finally so it always happens, even if the rollback itself
     * throws. The exception is intentionally not swallowed here: a
     * failed rollback means the pooled connection may still be in an
     * unknown transactional state, and the caller needs to know.
     */
    #[Override]
    public function reset(): void
    {
        try {
            if ($this->write instanceof ResettableInterface) {
                $this->write->reset();
            } else {
                for ($level = $this->write->transactionLevel(); $level > 0; $level--) {
                    $this->write->rollback();
                }
            }
        } finally {
            $this->resetStickyState();
        }
    }

    /**
     * Detects whether a SQL statement may write: one whose first keyword is
     * INSERT, UPDATE, DELETE, WITH, MERGE, REPLACE or CALL.
     *
     * Leading whitespace and any number of leading comments (-- ..., # ... and
     * /* ... *\/) are stripped before sniffing the first keyword,
     * case-insensitively, so a comment cannot hide a write from the router.
     * A WITH (CTE) read is treated as a write and served by the primary, which
     * is the safe side: a write sent to a replica could be applied there, or
     * lost.
     */
    private function isWriteStatement(
        string $sql,
    ): bool {
        $trimmed = $this->stripLeadingComments($sql);

        return (bool) preg_match('/^(' . self::WRITE_KEYWORDS . ')\b/i', $trimmed);
    }

    private function stripLeadingComments(
        string $sql,
    ): string {
        $trimmed = ltrim($sql);

        while (true) {
            if (
                str_starts_with($trimmed, '--')
                || str_starts_with($trimmed, '#')
            ) {
                $newline = strpos($trimmed, "\n");

                if ($newline === false) {
                    return '';
                }

                $trimmed = ltrim(substr($trimmed, $newline + 1));

                continue;
            }

            if (str_starts_with($trimmed, '/*')) {
                $end = strpos($trimmed, '*/', 2);

                if ($end === false) {
                    return '';
                }

                $trimmed = ltrim(substr($trimmed, $end + 2));

                continue;
            }

            return $trimmed;
        }
    }
}
