<?php

declare(strict_types=1);

namespace Marko\PubSub\Redis\Driver;

use Amp\Redis\Connection\RedisConnector;
use Amp\Redis\RedisSubscriber as AmphpRedisSubscriber;
use Amp\Redis\RedisSubscription as AmphpRedisSubscription;

readonly class DefaultAmphpRedisSubscriber implements AmphpRedisSubscriberInterface
{
    private AmphpRedisSubscriber $subscriber;

    public function __construct(RedisConnector $connector)
    {
        $this->subscriber = new AmphpRedisSubscriber($connector);
    }

    public function subscribe(string $channel): AmphpRedisSubscription
    {
        return $this->subscriber->subscribe($channel);
    }

    public function subscribeToPattern(string $pattern): AmphpRedisSubscription
    {
        return $this->subscriber->subscribeToPattern($pattern);
    }
}
