<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Worker;

use Marko\Core\Contracts\ResettableInterface;
use RuntimeException;

/**
 * A {@see ResettableInterface} whose reset() always throws, exercising the
 * requirement that a reset failure must fail the request loudly rather than
 * be silently swallowed.
 */
class FaultyResettable implements ResettableInterface
{
    /**
     * @throws RuntimeException
     */
    public function reset(): void
    {
        throw new RuntimeException('reset failed for FaultyResettable');
    }
}
