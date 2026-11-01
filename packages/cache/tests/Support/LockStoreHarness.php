<?php

declare(strict_types=1);

namespace Marko\Cache\Tests\Support;

use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Lock\LockScope;

/**
 * What a driver package provides to run the shared LockStoreContract suite
 * against its store.
 */
interface LockStoreHarness
{
    public function store(): LockStoreInterface;

    /**
     * A LockFactory over store(). Fake-clock harnesses pair it with a
     * ClockAdvancingSleeper so block() finishes at once.
     */
    public function factory(): LockFactoryInterface;

    /**
     * Make the named lock's current record expire now (move a fake clock past
     * it, or shorten the record's TTL on the server).
     */
    public function expire(
        string $name,
    ): void;

    public function expectedScope(): LockScope;

    /**
     * The time on the clock factory() uses, in seconds with microseconds.
     */
    public function clockSeconds(): float;

    /**
     * Remove everything the test stored.
     */
    public function cleanUp(): void;
}
