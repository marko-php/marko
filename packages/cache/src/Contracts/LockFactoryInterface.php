<?php

declare(strict_types=1);

namespace Marko\Cache\Contracts;

use Marko\Cache\Exceptions\LockException;
use Marko\Cache\Lock\LockScope;

/**
 * Creates and restores atomic locks on the bound LockStoreInterface.
 */
interface LockFactoryInterface
{
    /**
     * A new lock with a fresh random owner token, expiring $seconds after it
     * is acquired.
     *
     * @throws LockException when $seconds is below 1 or $name is empty
     */
    public function create(
        string $name,
        int $seconds,
    ): LockInterface;

    /**
     * Rebuild a lock from its name and owner token, so a different process
     * (for example a job's failure path) can release it. A restored lock can
     * release(), forceRelease() and report its name and owner; it cannot
     * acquire(), because it carries no expiry.
     *
     * @throws LockException when $name or $owner is empty
     */
    public function restore(
        string $name,
        string $owner,
    ): LockInterface;

    /**
     * The bound store's scope: check for LockScope::Distributed before relying
     * on cross-host exclusion.
     */
    public function scope(): LockScope;
}
