<?php

declare(strict_types=1);

namespace Marko\PubSub\Redis\Driver;

use Marko\PubSub\PubSubConfig;
use Marko\PubSub\Redis\RedisPubSubConnection;
use Marko\PubSub\SubscriberInterface;
use Marko\PubSub\Subscription;

/**
 * Every subscription made through one RedisSubscriber shares a single Redis
 * connection. Cancelling a subscription unsubscribes only its own channels.
 */
readonly class RedisSubscriber implements SubscriberInterface
{
    private SharedAmphpRedisSubscriber $amphpSubscriber;

    public function __construct(
        private RedisPubSubConnection $connection,
        private PubSubConfig $config,
    ) {
        $this->amphpSubscriber = new SharedAmphpRedisSubscriber(
            fn (): AmphpRedisSubscriberInterface => $this->createAmphpSubscriber(),
        );
    }

    public function subscribe(
        string ...$channels,
    ): Subscription {
        $prefix = $this->config->prefix();

        $amphpSubscriptions = [];
        $channelNames = [];

        foreach ($channels as $channel) {
            $amphpSubscriptions[] = $this->amphpSubscriber->subscribe($prefix . $channel);
            $channelNames[] = $channel;
        }

        return new RedisSubscription($amphpSubscriptions, $prefix, $channelNames);
    }

    public function psubscribe(
        string ...$patterns,
    ): Subscription {
        $prefix = $this->config->prefix();

        $amphpSubscriptions = [];
        $patternNames = [];

        foreach ($patterns as $pattern) {
            $prefixedPattern = $prefix . $pattern;
            $amphpSubscriptions[] = $this->amphpSubscriber->subscribeToPattern($prefixedPattern);
            $patternNames[] = $pattern;
        }

        return new RedisSubscription($amphpSubscriptions, $prefix, [], $patternNames);
    }

    /**
     * Called once per RedisSubscriber, on its first subscription.
     */
    protected function createAmphpSubscriber(): AmphpRedisSubscriberInterface
    {
        return new DefaultAmphpRedisSubscriber($this->connection->connector());
    }
}
