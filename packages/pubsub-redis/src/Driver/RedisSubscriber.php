<?php

declare(strict_types=1);

namespace Marko\PubSub\Redis\Driver;

use Marko\PubSub\PubSubConfig;
use Marko\PubSub\Redis\RedisPubSubConnection;
use Marko\PubSub\SubscriberInterface;
use Marko\PubSub\Subscription;

readonly class RedisSubscriber implements SubscriberInterface
{
    public function __construct(
        private RedisPubSubConnection $connection,
        private PubSubConfig $config,
    ) {}

    public function subscribe(string ...$channels): Subscription
    {
        $amphpSubscriber = $this->createAmphpSubscriber();
        $prefix = $this->config->prefix();

        $channel = $channels[0];
        $amphpSubscription = $amphpSubscriber->subscribe($prefix . $channel);

        return new RedisSubscription($amphpSubscription, $prefix, $channel);
    }

    public function psubscribe(string ...$patterns): Subscription
    {
        $amphpSubscriber = $this->createAmphpSubscriber();
        $prefix = $this->config->prefix();

        $pattern = $patterns[0];
        $prefixedPattern = $prefix . $pattern;
        $amphpSubscription = $amphpSubscriber->subscribeToPattern($prefixedPattern);

        return new RedisSubscription($amphpSubscription, $prefix, null, $pattern);
    }

    protected function createAmphpSubscriber(): AmphpRedisSubscriberInterface
    {
        return new DefaultAmphpRedisSubscriber($this->connection->connector());
    }
}
