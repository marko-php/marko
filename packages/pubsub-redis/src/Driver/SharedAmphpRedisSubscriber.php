<?php

declare(strict_types=1);

namespace Marko\PubSub\Redis\Driver;

use Amp\Redis\RedisSubscription as AmphpRedisSubscription;
use Closure;

/**
 * Creates one amphp subscriber on the first subscription and sends every
 * later subscription through it, so they all share one Redis connection.
 * The amphp subscriber multiplexes any number of channels and patterns over
 * that connection and re-subscribes all of them after a reconnect.
 */
class SharedAmphpRedisSubscriber implements AmphpRedisSubscriberInterface
{
    private ?AmphpRedisSubscriberInterface $subscriber = null;

    /**
     * @param Closure(): AmphpRedisSubscriberInterface $factory Called once, on the first subscription
     */
    public function __construct(
        private readonly Closure $factory,
    ) {}

    public function subscribe(
        string $channel,
    ): AmphpRedisSubscription {
        return $this->subscriber()->subscribe($channel);
    }

    public function subscribeToPattern(
        string $pattern,
    ): AmphpRedisSubscription {
        return $this->subscriber()->subscribeToPattern($pattern);
    }

    private function subscriber(): AmphpRedisSubscriberInterface
    {
        if ($this->subscriber === null) {
            $this->subscriber = ($this->factory)();
        }

        return $this->subscriber;
    }
}
