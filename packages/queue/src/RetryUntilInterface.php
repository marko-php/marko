<?php

declare(strict_types=1);

namespace Marko\Queue;

use DateTimeImmutable;

/**
 * A job that is retried until a deadline instead of for a fixed number of attempts.
 *
 * retryUntil() is called once, when the job is pushed, and the result is stored on the job with
 * setRetryDeadline(). It is never called again, so a relative deadline such as "6 hours from now"
 * does not move on later attempts. While the deadline is ahead, the job's attempt limit is ignored.
 */
interface RetryUntilInterface
{
    /**
     * The time after which the job is no longer retried.
     */
    public function retryUntil(): DateTimeImmutable;
}
