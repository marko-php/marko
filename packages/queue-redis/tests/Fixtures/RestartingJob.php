<?php

declare(strict_types=1);

namespace Marko\Queue\Redis\Tests\Fixtures;

use Closure;
use Marko\Queue\Job;

/**
 * Runs a callback while it is handled, standing in for a deploy that issues queue:restart mid-job.
 */
class RestartingJob extends Job
{
    public static ?Closure $onHandle = null;

    public static int $handled = 0;

    public function handle(): void
    {
        self::$handled++;

        if (self::$onHandle !== null) {
            (self::$onHandle)();
        }
    }
}
