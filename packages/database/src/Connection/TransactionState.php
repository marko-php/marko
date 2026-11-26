<?php

declare(strict_types=1);

namespace Marko\Database\Connection;

/**
 * Tracks transaction nesting depth and the after-commit/after-rollback
 * callbacks registered at each level.
 *
 * Drivers own the SQL (BEGIN, SAVEPOINT, RELEASE, ROLLBACK TO) and call
 * this class after each statement succeeds, so depth and callbacks stay
 * consistent across PostgreSQL and MySQL.
 *
 * - Committing an inner level hands its callbacks to the parent level.
 * - Committing the outermost level runs the after-commit callbacks.
 * - Rolling back a level discards its after-commit callbacks and runs its
 *   after-rollback callbacks.
 *
 * Callbacks run after the level has been removed, so a callback that opens
 * a new transaction starts from a clean state. An exception thrown by a
 * callback propagates and the remaining callbacks for that level do not run.
 *
 * A lost marker records that the connection under the open transaction was
 * lost. It is only set while a level is open and clears itself once the last
 * level is closed (commit, rollback, discard or clear), so the caller must
 * close every level before the connection is used again.
 */
class TransactionState
{
    /**
     * @var list<array{commit: list<callable>, rollback: list<callable>}>
     */
    private array $levels = [];

    private bool $lost = false;

    public function level(): int
    {
        return count($this->levels);
    }

    /**
     * Record that the connection under the open transaction was lost.
     */
    public function markLost(): void
    {
        if ($this->levels === []) {
            return;
        }

        $this->lost = true;
    }

    public function isLost(): bool
    {
        return $this->lost;
    }

    public function begin(): void
    {
        $this->levels[] = ['commit' => [], 'rollback' => []];
    }

    /**
     * Run the callback after the outermost transaction commits, or now when
     * no transaction is open.
     */
    public function afterCommit(
        callable $callback,
    ): void {
        if ($this->levels === []) {
            $callback();

            return;
        }

        $this->levels[array_key_last($this->levels)]['commit'][] = $callback;
    }

    /**
     * Run the callback if the current level (or a level enclosing it) rolls
     * back. Outside a transaction there is nothing to roll back, so the
     * callback is not kept.
     */
    public function afterRollback(
        callable $callback,
    ): void {
        if ($this->levels === []) {
            return;
        }

        $this->levels[array_key_last($this->levels)]['rollback'][] = $callback;
    }

    /**
     * Close the current level after a successful COMMIT or RELEASE SAVEPOINT.
     */
    public function commit(): void
    {
        $closed = array_pop($this->levels);
        $this->clearLostWhenIdle();

        if ($closed === null) {
            return;
        }

        if ($this->levels !== []) {
            $parent = array_key_last($this->levels);
            $this->levels[$parent]['commit'] = [...$this->levels[$parent]['commit'], ...$closed['commit']];
            $this->levels[$parent]['rollback'] = [...$this->levels[$parent]['rollback'], ...$closed['rollback']];

            return;
        }

        foreach ($closed['commit'] as $callback) {
            $callback();
        }
    }

    /**
     * Close the current level after a successful ROLLBACK or ROLLBACK TO SAVEPOINT.
     */
    public function rollback(): void
    {
        $closed = array_pop($this->levels);
        $this->clearLostWhenIdle();

        if ($closed === null) {
            return;
        }

        foreach ($closed['rollback'] as $callback) {
            $callback();
        }
    }

    /**
     * Run the after-commit callbacks queued at every open level, outermost
     * level first, as though the outermost transaction had committed, and
     * forget them. No level is closed and nothing is committed; after-rollback
     * callbacks stay registered.
     *
     * Used by tests that wrap each case in a transaction that is always rolled
     * back, so the callbacks the code under test queued can still be asserted on.
     */
    public function runAfterCommitCallbacks(): void
    {
        // Only the callbacks queued when the call starts run; one registered by
        // a running callback stays queued. Each callback is removed before it
        // runs, so an exception leaves the rest queued and never re-runs one.
        $pending = array_map(static fn (array $level): int => count($level['commit']), $this->levels);

        foreach ($pending as $index => $count) {
            for ($i = 0; $i < $count; $i++) {
                $callback = array_shift($this->levels[$index]['commit']);

                if ($callback !== null) {
                    $callback();
                }
            }
        }
    }

    /**
     * Drop the current level without running any of its callbacks. Used when
     * the statement that would close the level failed.
     */
    public function discard(): void
    {
        array_pop($this->levels);
        $this->clearLostWhenIdle();
    }

    /**
     * Drop every level without running callbacks.
     */
    public function clear(): void
    {
        $this->levels = [];
        $this->lost = false;
    }

    private function clearLostWhenIdle(): void
    {
        if ($this->levels === []) {
            $this->lost = false;
        }
    }
}
