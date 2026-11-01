<?php

declare(strict_types=1);

namespace Marko\Queue\Redis\Tests\Fixtures;

use Marko\Queue\Job;
use RuntimeException;

class AlwaysFailingJob extends Job
{
    public static int $handled = 0;

    public function __construct(
        ?int $maxAttempts = null,
        ?int $maxExceptions = null,
        int $backoff = 0,
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->maxExceptions = $maxExceptions;
        $this->backoff = $backoff;
    }

    public function handle(): void
    {
        self::$handled++;

        throw new RuntimeException('This job always fails');
    }
}
