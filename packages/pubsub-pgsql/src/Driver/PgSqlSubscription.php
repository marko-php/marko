<?php

declare(strict_types=1);

namespace Marko\PubSub\PgSql\Driver;

use Amp\Postgres\PostgresListener;
use Generator;
use Iterator;
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
        /** @var Iterator[] $iterators */
        $iterators = [];

        foreach ($this->listeners as $listener) {
            $iterator = $listener->getIterator();
            $iterator->rewind();
            $iterators[] = $iterator;
        }

        $active = array_fill(0, count($iterators), true);

        while (array_any($active, fn (bool $a) => $a)) {
            foreach ($iterators as $index => $iterator) {
                if (!$active[$index]) {
                    continue;
                }

                if (!$iterator->valid()) {
                    $active[$index] = false;
                    continue;
                }

                $notification = $iterator->current();
                $channel = substr($notification->channel, strlen($this->prefix));
                yield new Message(channel: $channel, payload: $notification->payload);
                $iterator->next();
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
