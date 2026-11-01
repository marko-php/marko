<?php

declare(strict_types=1);

namespace Marko\Queue\Database\Tests\Fixtures;

use Marko\Queue\QueueConfig;
use Marko\Queue\RetryPolicy;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Psr\Clock\ClockInterface;

/**
 * Builds the real RetryPolicy with the given queue.max_attempts default and clock.
 */
class TestRetryPolicy
{
    public static function create(
        int $maxAttempts = 3,
        ClockInterface $clock = new FakeClock(),
    ): RetryPolicy {
        return new RetryPolicy(
            new QueueConfig(new FakeConfigRepository(['queue.max_attempts' => $maxAttempts])),
            $clock,
        );
    }
}
