<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Queue\Exceptions\QueueException;

/**
 * What to do with a job after an attempt that threw or asked to be released: release it for another
 * attempt after $delay seconds, or fail it for good because of $failure.
 */
readonly class RetryDecision
{
    private function __construct(
        public ?int $delay,
        public ?QueueException $failure,
    ) {}

    /**
     * @throws QueueException When the delay is negative
     */
    public static function release(
        int $delay,
    ): self {
        if ($delay < 0) {
            throw QueueException::invalidRetryDelay($delay);
        }

        return new self($delay, null);
    }

    public static function fail(
        QueueException $failure,
    ): self {
        return new self(null, $failure);
    }

    public function shouldRelease(): bool
    {
        return $this->failure === null;
    }
}
