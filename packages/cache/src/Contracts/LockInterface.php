<?php

declare(strict_types=1);

namespace Marko\Cache\Contracts;

use Marko\Cache\Exceptions\LockException;
use Marko\Cache\Exceptions\LockTimeoutException;

/**
 * One named lock with one owner token.
 */
interface LockInterface
{
    /**
     * Try to claim the lock once, without waiting.
     *
     * @throws LockException when the lock was restored (it has no expiry to claim with)
     */
    public function acquire(): bool;

    /**
     * Retry acquire() until it succeeds or $waitSeconds pass.
     *
     * @throws LockTimeoutException|LockException
     */
    public function block(
        int $waitSeconds,
    ): void;

    /**
     * Free the lock when this owner holds it. Returns false when another owner
     * holds it, or it has expired.
     */
    public function release(): bool;

    /**
     * Free the lock whoever holds it.
     */
    public function forceRelease(): void;

    /**
     * The owner token. Pass it with name() to LockFactoryInterface::restore()
     * to release the lock from another process.
     */
    public function owner(): string;

    public function name(): string;
}
