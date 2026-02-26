<?php

declare(strict_types=1);

namespace Marko\PubSub\PgSql\Driver;

use Marko\PubSub\Message;
use Marko\PubSub\PgSql\PgSqlPubSubConnection;
use Marko\PubSub\PublisherInterface;
use Marko\PubSub\PubSubConfig;

readonly class PgSqlPublisher implements PublisherInterface
{
    public function __construct(
        private PgSqlPubSubConnection $connection,
        private PubSubConfig $config,
    ) {}

    public function publish(string $channel, Message $message): void
    {
        $prefixed = $this->config->prefix() . $channel;
        $this->connection->connection()->notify($prefixed, $message->payload);
    }
}
