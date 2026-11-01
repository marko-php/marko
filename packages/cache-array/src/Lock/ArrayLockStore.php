<?php

declare(strict_types=1);

namespace Marko\Cache\Memory\Lock;

use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Lock\LockScope;
use Psr\Clock\ClockInterface;

/**
 * In-memory lock store: excludes callers in the same PHP process only. Meant
 * for tests and development; use cache-redis for cross-host exclusion.
 */
class ArrayLockStore implements LockStoreInterface
{
    /**
     * @var array<string, array{owner: string, expires_at: float}>
     */
    private array $locks = [];

    public function __construct(
        private readonly ClockInterface $clock,
    ) {}

    public function acquire(
        string $name,
        string $owner,
        int $seconds,
    ): bool {
        if ($this->currentOwner($name) !== null) {
            return false;
        }

        $this->locks[$name] = ['owner' => $owner, 'expires_at' => $this->now() + $seconds];

        return true;
    }

    public function release(
        string $name,
        string $owner,
    ): bool {
        if ($this->currentOwner($name) !== $owner) {
            return false;
        }

        unset($this->locks[$name]);

        return true;
    }

    public function forceRelease(
        string $name,
    ): void {
        unset($this->locks[$name]);
    }

    public function currentOwner(
        string $name,
    ): ?string {
        if (!isset($this->locks[$name])) {
            return null;
        }

        if ($this->now() >= $this->locks[$name]['expires_at']) {
            unset($this->locks[$name]);

            return null;
        }

        return $this->locks[$name]['owner'];
    }

    public function scope(): LockScope
    {
        return LockScope::Process;
    }

    /**
     * The clock's time in seconds, with microseconds.
     */
    private function now(): float
    {
        return (float) $this->clock->now()->format('U.u');
    }
}
