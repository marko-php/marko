<?php

declare(strict_types=1);

namespace Marko\Cache\Lock;

use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Contracts\LockInterface;
use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Exceptions\LockException;
use Marko\Core\Sleep\SleeperInterface;
use Psr\Clock\ClockInterface;
use Random\RandomException;

/**
 * Builds Lock instances on the bound LockStoreInterface. A cache driver
 * package (cache-redis, cache-file, cache-array) binds the store; without one,
 * resolving this factory throws NoDriverException.
 */
class LockFactory implements LockFactoryInterface
{
    public function __construct(
        private readonly LockStoreInterface $store,
        private readonly ClockInterface $clock,
        private readonly SleeperInterface $sleeper,
    ) {}

    /**
     * @throws LockException|RandomException
     */
    public function create(
        string $name,
        int $seconds,
    ): LockInterface {
        return new Lock($this->store, $this->clock, $this->sleeper, $name, bin2hex(random_bytes(16)), $seconds);
    }

    /**
     * @throws LockException
     */
    public function restore(
        string $name,
        string $owner,
    ): LockInterface {
        return new Lock($this->store, $this->clock, $this->sleeper, $name, $owner, null);
    }

    public function scope(): LockScope
    {
        return $this->store->scope();
    }
}
