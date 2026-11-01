<?php

declare(strict_types=1);

namespace Marko\Cache\Contracts;

use Marko\Cache\Lock\LockScope;

/**
 * The backend part of an atomic lock: the only piece a driver implements.
 *
 * Owner tokens, expiry validation, the block() wait loop and restore live once
 * in marko/cache (Lock and LockFactory); a store only claims, frees and reads
 * one lock record atomically.
 */
interface LockStoreInterface
{
    /**
     * Claim the lock for $owner when nobody holds it (or the holder's lock has
     * expired), expiring after $seconds. Atomic: of any number of concurrent
     * callers exactly one gets true.
     */
    public function acquire(
        string $name,
        string $owner,
        int $seconds,
    ): bool;

    /**
     * Free the lock only when $owner holds it (atomic compare-and-delete).
     * Returns false when another owner holds it, or it has expired or was never
     * held.
     */
    public function release(
        string $name,
        string $owner,
    ): bool;

    /**
     * Free the lock whoever holds it.
     */
    public function forceRelease(
        string $name,
    ): void;

    /**
     * The owner token of the current, unexpired holder, or null when free.
     */
    public function currentOwner(
        string $name,
    ): ?string;

    /**
     * How far the exclusion reaches: one process, one host or every host.
     */
    public function scope(): LockScope;
}
