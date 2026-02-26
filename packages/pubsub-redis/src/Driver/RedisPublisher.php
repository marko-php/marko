<?php

declare(strict_types=1);

namespace Marko\PubSub\Redis\Driver;

use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;
use Marko\PubSub\PubSubConfig;
use Marko\PubSub\Redis\RedisPubSubConnection;

readonly class RedisPublisher implements PublisherInterface
{
    public function __construct(
        private RedisPubSubConnection $connection,
        private PubSubConfig $config,
    ) {}

    public function publish(string $channel, Message $message): void
    {
        $prefixed = $this->config->prefix() . $channel;
        $this->connection->client()->publish($prefixed, $message->payload);
    }
}
