<?php

declare(strict_types=1);

namespace Marko\Cache\Tests\Support;

use Closure;
use Marko\Core\Sleep\SleeperInterface;
use Marko\Testing\Fake\FakeClock;

/**
 * Records each Lock::block() wait and moves a FakeClock forward by it instead
 * of sleeping, so a block() test finishes at once. An optional callback runs
 * after each wait, to change the lock's state mid-wait.
 */
class ClockAdvancingSleeper implements SleeperInterface
{
    /** @var list<int> Milliseconds, in the order requested */
    public array $sleeps = [];

    public function __construct(
        private readonly FakeClock $clock,
        private readonly ?Closure $afterSleep = null,
    ) {}

    public function sleep(
        int $milliseconds,
    ): void {
        $this->sleeps[] = $milliseconds;
        $this->clock->travel("+$milliseconds milliseconds");

        if ($this->afterSleep !== null) {
            ($this->afterSleep)(count($this->sleeps));
        }
    }
}
