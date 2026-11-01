<?php

declare(strict_types=1);

namespace Marko\Core\Sleep;

/**
 * Pauses the current process. Code that waits (lock blocking, transaction
 * retry backoff) sleeps through this contract, so tests can record the waits
 * instead of sleeping (FakeSleeper in marko/testing).
 */
interface SleeperInterface
{
    /**
     * Pause for the given number of milliseconds (0 or less returns at once).
     */
    public function sleep(
        int $milliseconds,
    ): void;
}
