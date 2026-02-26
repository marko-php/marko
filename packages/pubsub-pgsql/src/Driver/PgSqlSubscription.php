<?php

declare(strict_types=1);

namespace Marko\PubSub\PgSql\Driver;

use Amp\Postgres\PostgresListener;
use Generator;
use Marko\PubSub\Message;
use Marko\PubSub\Subscription;

readonly class PgSqlSubscription implements Subscription
{
    /**
     * @param PostgresListener[] $listeners
     */
    public function __construct(
        private array $listeners,
        private string $prefix,
    ) {}

    public function getIterator(): Generator
    {
        foreach ($this->listeners as $listener) {
            foreach ($listener as $notification) {
                $channel = substr($notification->channel, strlen($this->prefix));
                yield new Message(channel: $channel, payload: $notification->payload);
            }
        }
    }

    public function cancel(): void
    {
        foreach ($this->listeners as $listener) {
            $listener->unlisten();
        }
    }
}
