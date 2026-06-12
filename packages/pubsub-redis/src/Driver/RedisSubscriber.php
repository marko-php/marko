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

        $amphpSubscriptions = [];
        $channelNames = [];

        foreach ($channels as $channel) {
            $amphpSubscriptions[] = $amphpSubscriber->subscribe($prefix . $channel);
            $channelNames[] = $channel;
        }

        return new RedisSubscription($amphpSubscriptions, $prefix, $channelNames);
    }

    public function psubscribe(string ...$patterns): Subscription
    {
        $amphpSubscriber = $this->createAmphpSubscriber();
        $prefix = $this->config->prefix();

        $amphpSubscriptions = [];
        $patternNames = [];

        foreach ($patterns as $pattern) {
            $prefixedPattern = $prefix . $pattern;
            $amphpSubscriptions[] = $amphpSubscriber->subscribeToPattern($prefixedPattern);
            $patternNames[] = $pattern;
        }

        return new RedisSubscription($amphpSubscriptions, $prefix, [], $patternNames);
    }

    protected function createAmphpSubscriber(): AmphpRedisSubscriberInterface
    {
        return new DefaultAmphpRedisSubscriber($this->connection->connector());
    }
}
