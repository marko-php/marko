<?php

declare(strict_types=1);

namespace Marko\Scheduler\Tests\Fixtures;

use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Lock\LockScope;
use Psr\Clock\ClockInterface;

/**
 * An in-memory lock store that reports any scope, so tests can stand in for a store
 * shared by several servers (LockScope::Distributed) or for a host- or process-scoped
 * store. Expiry follows the injected clock.
 */
class SharedLockStore implements LockStoreInterface
{
    /** @var array<string, array{owner: string, expires_at: int}> */
    private array $locks = [];

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly LockScope $scope = LockScope::Distributed,
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
        $lock = $this->locks[$name] ?? null;

        if ($lock === null || $lock['expires_at'] <= $this->now()) {
            return null;
        }

        return $lock['owner'];
    }

    public function scope(): LockScope
    {
        return $this->scope;
    }

    /**
     * @return list<string> The names of the unexpired locks
     */
    public function heldNames(): array
    {
        return array_values(array_filter(
            array_keys($this->locks),
            fn (string $name): bool => $this->currentOwner($name) !== null,
        ));
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
