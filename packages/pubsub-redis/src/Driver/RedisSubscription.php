<?php

declare(strict_types=1);

namespace Marko\PubSub\Redis\Driver;

use Amp\Redis\RedisSubscription as AmphpRedisSubscription;
use Generator;
use Marko\PubSub\Message;
use Marko\PubSub\Subscription;

readonly class RedisSubscription implements Subscription
{
    public function __construct(
        private AmphpRedisSubscription $amphpSubscription,
        private string $prefix,
        private ?string $channel = null,
        private ?string $pattern = null,
    ) {}

    public function getIterator(): Generator
    {
        if ($this->pattern !== null) {
            foreach ($this->amphpSubscription as [$payload, $matchedChannel]) {
                $channel = $this->stripPrefix($matchedChannel);
                yield new Message(channel: $channel, payload: $payload, pattern: $this->pattern);
            }
        } else {
            foreach ($this->amphpSubscription as $payload) {
                yield new Message(channel: (string) $this->channel, payload: $payload);
            }
        }
    }

    public function cancel(): void
    {
        $this->amphpSubscription->unsubscribe();
    }

    private function stripPrefix(string $channel): string
    {
        if ($this->prefix !== '' && str_starts_with($channel, $this->prefix)) {
            return substr($channel, strlen($this->prefix));
        }

        return $channel;
    }
}
