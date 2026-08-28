<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Worker;

use Closure;
use Marko\Core\Contracts\ResettableInterface;

/**
 * A {@see ResettableInterface} spy: counts reset() calls and, when given a
 * callback, records the moment reset() ran relative to other observed
 * events (e.g. route matching) so ordering can be asserted directly.
 */
class RecordingResettable implements ResettableInterface
{
    public private(set) int $resetCount = 0;

    public function __construct(
        private readonly ?Closure $onReset = null,
    ) {}

    public function reset(): void
    {
        $this->resetCount++;

        if ($this->onReset !== null) {
            ($this->onReset)();
        }
    }
}
