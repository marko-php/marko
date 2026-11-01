<?php

declare(strict_types=1);

namespace Marko\Queue\Redis\Tests\Fixtures;

use DateTimeImmutable;
use Marko\Queue\Job;
use Marko\Queue\RetryUntilInterface;

/**
 * Releases itself on every attempt until its retry deadline, like a job polling an external API.
 */
class DeadlineJob extends Job implements RetryUntilInterface
{
    public static int $handled = 0;

    public function __construct(
        private readonly string $deadline,
    ) {}

    public function retryUntil(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->deadline);
    }

    public function handle(): void
    {
        self::$handled++;
        $this->release(60);
    }
}
