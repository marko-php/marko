<?php

declare(strict_types=1);

namespace Marko\Queue;

use Random\RandomException;

/**
 * Generates the id a queue driver gives a job when it is pushed: a random RFC 4122 version 4 UUID.
 *
 * Every driver that assigns its own ids (DatabaseQueue, RabbitmqQueue, RedisQueue) uses this one generator.
 * Replace it with a Preference to change the id format for all of them.
 */
class JobIdGenerator
{
    /**
     * @throws RandomException When no source of randomness is available
     */
    public function generate(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
        );
    }
}
