<?php

declare(strict_types=1);

namespace Marko\Queue\Tests\Fixtures;

use Closure;
use Marko\Queue\Job;
use Marko\Queue\UniqueJobInterface;
use Marko\Queue\UniqueUntil;
use RuntimeException;

/**
 * A unique job keyed by $key. $during runs inside handle(), so a test can push a duplicate
 * or fire the timer while the job runs; $fail makes every attempt throw.
 */
class UniqueTestJob extends Job implements UniqueJobInterface
{
    public function __construct(
        private readonly string $key = '42',
        private readonly UniqueUntil $until = UniqueUntil::Processed,
        private readonly int $for = 60,
        private readonly ?Closure $during = null,
        private readonly bool $fail = false,
        ?int $maxAttempts = 1,
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->backoff = 0;
    }

    public function handle(): void
    {
        if ($this->during !== null) {
            ($this->during)();
        }

        if ($this->fail) {
            throw new RuntimeException("Unique job $this->key failed");
        }
    }

    public function uniqueId(): string
    {
        return $this->key;
    }

    public function uniqueFor(): int
    {
        return $this->for;
    }

    public function uniqueUntil(): UniqueUntil
    {
        return $this->until;
    }
}
