<?php

declare(strict_types=1);

namespace Marko\Queue\Redis\Exceptions;

use Marko\Queue\Exceptions\QueueException;

class RedisQueueException extends QueueException
{
    public static function invalidBlockFor(
        int $blockFor,
    ): self {
        return new self(
            message: "Invalid queue-redis.block_for value: $blockFor.",
            context: 'block_for is the number of seconds a single-queue worker waits on Redis for a job to arrive.',
            suggestion: 'Set queue-redis.block_for to 0 to turn blocking pops off, or to a whole number of seconds'
                . ' below the connection read timeout, e.g. 5.',
        );
    }
}
