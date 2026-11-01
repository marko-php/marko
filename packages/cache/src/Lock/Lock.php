<?php

declare(strict_types=1);

namespace Marko\Cache\Lock;

use Marko\Cache\Contracts\LockInterface;
use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Exceptions\LockException;
use Marko\Cache\Exceptions\LockTimeoutException;
use Marko\Core\Sleep\SleeperInterface;
use Psr\Clock\ClockInterface;

/**
 * The lock behaviour every store shares: owner token, expiry validation and
 * the block() wait loop. The store only claims and frees the record.
 */
class Lock implements LockInterface
{
    /**
     * How long block() waits between acquire() attempts.
     */
    public const int RETRY_INTERVAL_MILLISECONDS = 250;

    /**
     * @param int|null $seconds The expiry acquire() claims with; null for a lock rebuilt by restore()
     *
     * @throws LockException when the name or owner is empty, or the expiry is below 1 second
     */
    public function __construct(
        private readonly LockStoreInterface $store,
        private readonly ClockInterface $clock,
        private readonly SleeperInterface $sleeper,
        private readonly string $name,
        private readonly string $owner,
        private readonly ?int $seconds,
    ) {
        if ($name === '') {
            throw LockException::emptyName();
        }

        if ($owner === '') {
            throw LockException::emptyOwner($name);
        }

        if ($seconds !== null && $seconds < 1) {
            throw LockException::invalidExpiry($name, $seconds);
        }
    }

    /**
     * @throws LockException when the lock was restored
     */
    public function acquire(): bool
    {
        if ($this->seconds === null) {
            throw LockException::restoredLockCannotAcquire($this->name);
        }

        return $this->store->acquire($this->name, $this->owner, $this->seconds);
    }

    /**
     * @throws LockTimeoutException|LockException
     */
    public function block(
        int $waitSeconds,
    ): void {
        if ($waitSeconds < 0) {
            throw LockException::negativeWait($this->name, $waitSeconds);
        }

        $deadline = $this->now() + $waitSeconds;

        while (!$this->acquire()) {
            $remainingMilliseconds = (int) ceil(($deadline - $this->now()) * 1_000);

            if ($remainingMilliseconds <= 0) {
                throw LockTimeoutException::forLock($this->name, $waitSeconds);
            }

            $this->sleeper->sleep(min(self::RETRY_INTERVAL_MILLISECONDS, $remainingMilliseconds));
        }
    }

    public function release(): bool
    {
        return $this->store->release($this->name, $this->owner);
    }

    public function forceRelease(): void
    {
        $this->store->forceRelease($this->name);
    }

    public function owner(): string
    {
        return $this->owner;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * The clock's time in seconds, with microseconds.
     */
    private function now(): float
    {
        return (float) $this->clock->now()->format('U.u');
    }
}
