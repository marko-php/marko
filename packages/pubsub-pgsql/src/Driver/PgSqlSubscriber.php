<?php

declare(strict_types=1);

namespace Marko\PubSub\PgSql\Driver;

use Marko\PubSub\Exceptions\PubSubException;
use Marko\PubSub\PgSql\PgSqlPubSubConnection;
use Marko\PubSub\PubSubConfig;
use Marko\PubSub\SubscriberInterface;
use Marko\PubSub\Subscription;

readonly class PgSqlSubscriber implements SubscriberInterface
{
    public function __construct(
        private PgSqlPubSubConnection $connection,
        private PubSubConfig $config,
    ) {}

    public function subscribe(string ...$channels): Subscription
    {
        $listeners = [];
        $conn = $this->connection->connection();

        foreach ($channels as $channel) {
            $prefixed = $this->config->prefix() . $channel;
            $listeners[] = $conn->listen($prefixed);
        }

        return new PgSqlSubscription($listeners, $this->config->prefix());
    }

    /**
     * @throws PubSubException
     */
    public function psubscribe(string ...$patterns): Subscription
    {
        throw PubSubException::patternSubscriptionNotSupported('pgsql');
    }
}
