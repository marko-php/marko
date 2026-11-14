<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Tests\Support;

use Amp\Pipeline\Queue;
use Closure;
use Generator;
use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;
use Marko\PubSub\SubscriberInterface;
use Marko\PubSub\Subscription;
use RuntimeException;

/**
 * In-process pub/sub double: publish() delivers to every live subscription on the channel
 * through an amphp queue, so subscribers iterate it on the event loop like a real driver.
 */
class InMemoryPubSub implements PublisherInterface, SubscriberInterface
{
    /**
     * @var list<array{channel: string, message: Message}>
     */
    public array $published = [];

    /**
     * @var array<string, array<int, Queue<Message>>>
     */
    private array $queues = [];

    /**
     * @var list<string>
     */
    public array $subscribeCalls = [];

    private int $nextId = 0;

    public function publish(
        string $channel,
        Message $message,
    ): void {
        $this->published[] = ['channel' => $channel, 'message' => $message];

        foreach ($this->queues[$channel] ?? [] as $queue) {
            $queue->pushAsync(new Message(channel: $channel, payload: $message->payload))->ignore();
        }
    }

    public function subscribe(
        string ...$channels,
    ): Subscription {
        if (count($channels) !== 1) {
            throw new RuntimeException('InMemoryPubSub subscriptions take exactly one channel');
        }

        $channel = $channels[0];
        $this->subscribeCalls[] = $channel;
        $id = $this->nextId++;
        /** @var Queue<Message> $queue */
        $queue = new Queue();
        $this->queues[$channel][$id] = $queue;

        return new readonly class ($queue, function () use ($channel, $id): void {
            unset($this->queues[$channel][$id]);

            if (($this->queues[$channel] ?? null) === []) {
                unset($this->queues[$channel]);
            }
        }) implements Subscription
        {

            /**
             * @param Queue<Message> $queue
             */
            public function __construct(
                private Queue $queue,
                private Closure $onCancel,
            ) {}

            public function getIterator(): Generator
            {
                foreach ($this->queue->iterate() as $message) {
                    yield $message;
                }
            }

            public function cancel(): void
            {
                ($this->onCancel)();

                if (!$this->queue->isComplete()) {
                    $this->queue->complete();
                }
            }
        };
    }

    public function psubscribe(
        string ...$patterns,
    ): Subscription {
        throw new RuntimeException('InMemoryPubSub does not support pattern subscriptions');
    }

    /**
     * Simulate the backend dropping every subscription on a channel (e.g. a lost connection).
     */
    public function endSubscriptions(
        string $channel,
    ): void {
        foreach ($this->queues[$channel] ?? [] as $queue) {
            $queue->complete();
        }

        unset($this->queues[$channel]);
    }

    public function activeSubscriptions(
        string $channel,
    ): int {
        return count($this->queues[$channel] ?? []);
    }
}
