<?php

declare(strict_types=1);

namespace Marko\Integration\Fixture\Job;

use DateTimeImmutable;
use Marko\Queue\Job;
use Marko\Queue\RetryUntilInterface;
use RuntimeException;

/**
 * Polls a remote engine that is always busy: it releases itself on every attempt until the
 * attempt listed in $throwOn, which throws. It retries until a deadline 6 hours after it is pushed,
 * and its first exception fails it.
 */
class PollingJob extends Job implements RetryUntilInterface
{
    public protected(set) ?int $maxExceptions = 1;

    public function __construct(
        private readonly ?int $throwOn = null,
    ) {}

    public function retryUntil(): DateTimeImmutable
    {
        return new DateTimeImmutable('+6 hours');
    }

    public function handle(): void
    {
        if ($this->attempts === $this->throwOn) {
            throw new RuntimeException("PollingJob failed on attempt $this->attempts");
        }

        $this->release(0);
    }
}
