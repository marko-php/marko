<?php

declare(strict_types=1);

namespace Marko\Queue\Plugin;

use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Queue\JobInterface;
use Marko\Queue\QueueInterface;
use Marko\Queue\RetryUntilInterface;

/**
 * Captures the retry deadline of a RetryUntilInterface job when it is pushed, for every queue driver.
 *
 * retryUntil() is called once and its result stored with setRetryDeadline(). A job that already
 * carries a deadline keeps it, so the deadline never moves on later attempts. queue:retry clears
 * the deadline before pushing a failed job back, so a retried job gets a fresh one.
 */
#[Plugin(target: QueueInterface::class)]
class RetryDeadlinePlugin
{
    #[Before(method: 'push')]
    public function beforePush(
        JobInterface $job,
        ?string $queue = null,
    ): null {
        $this->captureDeadline($job);

        return null;
    }

    #[Before(method: 'later')]
    public function beforeLater(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): null {
        $this->captureDeadline($job);

        return null;
    }

    private function captureDeadline(
        JobInterface $job,
    ): void {
        if ($job instanceof RetryUntilInterface && $job->retryDeadline === null) {
            $job->setRetryDeadline($job->retryUntil());
        }
    }
}
