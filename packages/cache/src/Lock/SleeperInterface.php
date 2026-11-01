<?php

declare(strict_types=1);

namespace Marko\Cache\Lock;

/**
 * Pauses the current process between Lock::block() attempts, so tests can
 * record the waits (and move a fake clock) instead of sleeping.
 */
interface SleeperInterface
{
    /**
     * Pause for the given number of milliseconds (0 returns at once).
     */
    public function sleep(
        int $milliseconds,
    ): void;
}
