<?php

declare(strict_types=1);

namespace Marko\PubSub\Redis\Driver;

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

    public function getIterator(): Generator
    {
        foreach ($this->amphpSubscriptions as $index => $amphpSubscription) {
            $pattern = $this->patterns[$index] ?? null;
            $channel = $this->channels[$index] ?? null;

            if ($pattern !== null) {
                foreach ($amphpSubscription as [$payload, $matchedChannel]) {
                    $strippedChannel = $this->stripPrefix($matchedChannel);
                    yield new Message(channel: $strippedChannel, payload: $payload, pattern: $pattern);
                }
            } else {
                foreach ($amphpSubscription as $payload) {
                    yield new Message(channel: (string) $channel, payload: $payload);
                }
            }
        }
    }

    public function cancel(): void
    {
        foreach ($this->amphpSubscriptions as $amphpSubscription) {
            $amphpSubscription->unsubscribe();
        }
    }

    private function stripPrefix(string $channel): string
    {
        if ($this->prefix !== '' && str_starts_with($channel, $this->prefix)) {
            return substr($channel, strlen($this->prefix));
        }

        return $channel;
    }
}
