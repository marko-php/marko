<?php

declare(strict_types=1);

namespace Marko\PubSub\Redis\Driver;

use Amp\Pipeline\Pipeline;
use Amp\Redis\RedisSubscription as AmphpRedisSubscription;
use Generator;
use Marko\PubSub\Message;
use Marko\PubSub\Subscription;

readonly class RedisSubscription implements Subscription
{
    /**
     * @param AmphpRedisSubscription[] $amphpSubscriptions
     * @param string[]|null[]          $channels           Channel name per subscription (null for pattern subs)
     * @param string[]|null[]          $patterns           Pattern per subscription (null for channel subs)
     */
    public function __construct(
        private array $amphpSubscriptions,
        private string $prefix,
        private array $channels = [],
        private array $patterns = [],
    ) {}

    /**
     * Reads every channel and pattern at once. All subscriptions of a
     * RedisSubscriber share one connection, and that connection waits for each
     * message to be read before reading the next, so draining the channels one
     * after another would stall every subscription in the process.
     */
    public function getIterator(): Generator
    {
        if (count($this->amphpSubscriptions) === 1) {
            $index = array_key_first($this->amphpSubscriptions);

            yield from $this->messages($index, $this->amphpSubscriptions[$index]);

            return;
        }

        $sources = [];

        foreach ($this->amphpSubscriptions as $index => $amphpSubscription) {
            $sources[] = Pipeline::fromIterable(fn (): Generator => $this->messages($index, $amphpSubscription));
        }

        foreach (Pipeline::merge($sources) as $message) {
            yield $message;
        }
    }

    public function cancel(): void
    {
        foreach ($this->amphpSubscriptions as $amphpSubscription) {
            $amphpSubscription->unsubscribe();
        }
    }

    /**
     * @return Generator<int, Message>
     */
    private function messages(
        int $index,
        AmphpRedisSubscription $amphpSubscription,
    ): Generator {
        $pattern = $this->patterns[$index] ?? null;
        $channel = $this->channels[$index] ?? null;

        if ($pattern !== null) {
            foreach ($amphpSubscription as [$payload, $matchedChannel]) {
                $strippedChannel = $this->stripPrefix($matchedChannel);
                yield new Message(channel: $strippedChannel, payload: $payload, pattern: $pattern);
            }

            return;
        }

        foreach ($amphpSubscription as $payload) {
            yield new Message(channel: (string) $channel, payload: $payload);
        }
    }

    private function stripPrefix(
        string $channel,
    ): string {
        if (
            $this->prefix !== ''
            && str_starts_with($channel, $this->prefix)
        ) {
            return substr($channel, strlen($this->prefix));
        }

        return $channel;
    }
}
