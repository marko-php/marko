<?php

declare(strict_types=1);

use function Amp\async;
use function Amp\delay;

use Amp\Redis\RedisClient;
use Amp\TimeoutCancellation;
use Marko\PubSub\Message;
use Marko\PubSub\PubSubConfig;
use Marko\PubSub\Redis\Driver\RedisSubscriber;
use Marko\PubSub\Redis\RedisPubSubConnection;
use Marko\Testing\Fake\FakeConfigRepository;

/*
 * These tests run against a real Redis server (the integration-services group).
 * They are skipped, with the reason, unless REDIS_HOST is set and reachable;
 * with MARKO_INTEGRATION_REQUIRED set (CI) an unreachable Redis is a failure.
 *
 *   docker compose -f tests/Integration/compose.yml up -d
 *   REDIS_HOST=127.0.0.1 composer test:integration
 */

pest()->group('integration-services');

/**
 * Null when Redis is reachable, otherwise why the test is skipped.
 *
 * @throws RuntimeException When MARKO_INTEGRATION_REQUIRED is set and Redis is unusable
 */
function sharedConnectionSkipReason(): ?string
{
    $host = (string) getenv('REDIS_HOST');
    $port = (int) (getenv('REDIS_PORT') ?: 6379);
    $reason = null;

    if ($host === '') {
        $reason = 'REDIS_HOST is not set. Start Redis with `docker compose -f tests/Integration/compose.yml up -d` and run with REDIS_HOST=127.0.0.1.';
    } else {
        set_error_handler(static fn (): bool => true);

        try {
            $socket = fsockopen($host, $port, $errorCode, $errorMessage, 1.0);
        } finally {
            restore_error_handler();
        }

        if ($socket === false) {
            $reason = "Redis is not reachable at $host:$port ($errorMessage).";
        } else {
            fclose($socket);
        }
    }

    $required = in_array(strtolower((string) getenv('MARKO_INTEGRATION_REQUIRED')), ['1', 'true', 'yes'], true);

    if (
        $reason !== null
        && $required
    ) {
        throw new RuntimeException("MARKO_INTEGRATION_REQUIRED is set but Redis is unusable: $reason");
    }

    return $reason;
}

/**
 * @return array{connection: RedisPubSubConnection, subscriber: RedisSubscriber, prefix: string}
 */
function sharedConnectionFixture(): array
{
    $prefix = 'marko-shared-' . bin2hex(random_bytes(4)) . ':';
    $connection = new RedisPubSubConnection(
        host: (string) getenv('REDIS_HOST'),
        port: (int) (getenv('REDIS_PORT') ?: 6379),
    );
    $config = new PubSubConfig(new FakeConfigRepository([
        'pubsub.driver' => 'redis',
        'pubsub.prefix' => $prefix,
    ]));

    return [
        'connection' => $connection,
        'subscriber' => new RedisSubscriber($connection, $config),
        'prefix' => $prefix,
    ];
}

/**
 * Pub/sub clients connected to Redis right now, keyed by client id.
 *
 * @return array<string, int> Number of channel subscriptions per client id
 */
function sharedConnectionPubSubClients(
    RedisClient $client,
): array {
    $clients = [];

    foreach (explode("\n", trim((string) $client->execute('CLIENT', 'LIST', 'TYPE', 'pubsub'))) as $line) {
        if (preg_match('/\bid=(\d+)\b.*\bsub=(\d+)\b/', $line, $matches) === 1) {
            $clients[$matches[1]] = (int) $matches[2];
        }
    }

    return $clients;
}

/**
 * Lets the event loop run until the condition holds, failing after the timeout.
 */
function sharedConnectionWaitUntil(
    Closure $condition,
    string $description,
    float $timeout = 5.0,
): void {
    $deadline = microtime(true) + $timeout;

    while (!$condition()) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException("Timed out after {$timeout}s waiting until $description.");
        }

        delay(0.01);
    }
}

/**
 * Number of subscribers Redis reports for each channel.
 *
 * @param list<string> $channels Prefixed channel names
 * @return array<string, int>
 */
function sharedConnectionNumSub(
    RedisClient $client,
    array $channels,
): array {
    $reply = $client->execute('PUBSUB', 'NUMSUB', ...$channels);
    $counts = [];

    for ($i = 0; $i < count($reply); $i += 2) {
        $counts[(string) $reply[$i]] = (int) $reply[$i + 1];
    }

    return $counts;
}

/**
 * The next message from a subscription iterator, or null when it has ended.
 *
 * @param array<int, bool> $started Iterators that already yielded, by object id
 */
function sharedConnectionNextMessage(
    Generator $iterator,
    array &$started,
    float $timeout = 3.0,
): ?Message {
    $id = spl_object_id($iterator);
    $first = !isset($started[$id]);
    $started[$id] = true;

    return async(static function () use ($iterator, $first): ?Message {
        if (!$first) {
            $iterator->next();
        }

        return $iterator->valid() ? $iterator->current() : null;
    })->await(new TimeoutCancellation($timeout, 'Timed out waiting for a pub/sub message'));
}

it('subscribes to 500 channels over one redis connection', function (): void {
    $reason = sharedConnectionSkipReason();

    if ($reason !== null) {
        $this->markTestSkipped($reason);
    }

    ['connection' => $connection, 'subscriber' => $subscriber, 'prefix' => $prefix] = sharedConnectionFixture();
    $client = $connection->client();
    $before = sharedConnectionPubSubClients($client);
    $subscriptions = [];

    try {
        for ($i = 0; $i < 500; $i++) {
            $subscriptions[$i] = $subscriber->subscribe("channel.$i");
        }

        sharedConnectionWaitUntil(
            static fn (): bool => in_array(500, array_diff_key(sharedConnectionPubSubClients($client), $before), true),
            'one client holds all 500 subscriptions',
        );

        $new = array_diff_key(sharedConnectionPubSubClients($client), $before);

        expect($new)->toHaveCount(1)
            ->and(array_values($new))->toBe([500])
            ->and(array_values(sharedConnectionNumSub($client, ["{$prefix}channel.0", "{$prefix}channel.499"])))
            ->toBe([1, 1]);
    } finally {
        foreach ($subscriptions as $subscription) {
            $subscription->cancel();
        }
    }
});

it('delivers each message only to the subscription for its channel', function (): void {
    $reason = sharedConnectionSkipReason();

    if ($reason !== null) {
        $this->markTestSkipped($reason);
    }

    ['connection' => $connection, 'subscriber' => $subscriber, 'prefix' => $prefix] = sharedConnectionFixture();
    $client = $connection->client();
    $channels = ['orders', 'shipments', 'returns'];
    $subscriptions = [];
    $iterators = [];
    $started = [];

    try {
        foreach ($channels as $channel) {
            $subscriptions[$channel] = $subscriber->subscribe($channel);
            $iterators[$channel] = $subscriptions[$channel]->getIterator();
        }

        $prefixed = array_map(static fn (string $channel): string => $prefix . $channel, $channels);
        sharedConnectionWaitUntil(
            static fn (): bool => array_values(sharedConnectionNumSub($client, $prefixed)) === [1, 1, 1],
            'every channel is subscribed',
        );

        // Published in reverse order: a message must reach its own channel's
        // subscription, not whichever subscription happens to be read first.
        foreach (array_reverse($channels) as $channel) {
            $client->publish($prefix . $channel, "payload-for-$channel");
        }

        foreach (array_reverse($channels) as $channel) {
            $message = sharedConnectionNextMessage($iterators[$channel], $started);

            expect($message)->toBeInstanceOf(Message::class)
                ->and($message->channel)->toBe($channel)
                ->and($message->payload)->toBe("payload-for-$channel");
        }
    } finally {
        foreach ($subscriptions as $subscription) {
            $subscription->cancel();
        }
    }
});

it('stops only the cancelled channel', function (): void {
    $reason = sharedConnectionSkipReason();

    if ($reason !== null) {
        $this->markTestSkipped($reason);
    }

    ['connection' => $connection, 'subscriber' => $subscriber, 'prefix' => $prefix] = sharedConnectionFixture();
    $client = $connection->client();
    $kept = $subscriber->subscribe('kept');
    $cancelled = $subscriber->subscribe('cancelled');
    $keptIterator = $kept->getIterator();
    $started = [];

    try {
        $prefixed = ["{$prefix}kept", "{$prefix}cancelled"];
        sharedConnectionWaitUntil(
            static fn (): bool => array_values(sharedConnectionNumSub($client, $prefixed)) === [1, 1],
            'both channels are subscribed',
        );

        $cancelled->cancel();

        sharedConnectionWaitUntil(
            static fn (): bool => array_values(sharedConnectionNumSub($client, $prefixed)) === [1, 0],
            'only the cancelled channel is unsubscribed',
        );

        // PUBLISH returns the number of subscribers that received the message
        expect($client->publish("{$prefix}cancelled", 'dropped'))->toBe(0)
            ->and($client->publish("{$prefix}kept", 'still-flowing'))->toBe(1)
            ->and(sharedConnectionNextMessage($keptIterator, $started)?->payload)->toBe('still-flowing');
    } finally {
        $kept->cancel();
    }
});

it('restores every remaining subscription after the connection drops', function (): void {
    $reason = sharedConnectionSkipReason();

    if ($reason !== null) {
        $this->markTestSkipped($reason);
    }

    ['connection' => $connection, 'subscriber' => $subscriber, 'prefix' => $prefix] = sharedConnectionFixture();
    $client = $connection->client();
    $before = sharedConnectionPubSubClients($client);
    $channels = ['alpha', 'beta', 'gamma'];
    $prefixed = array_map(static fn (string $channel): string => $prefix . $channel, $channels);
    $subscriptions = [];
    $iterators = [];
    $started = [];

    try {
        foreach ($channels as $channel) {
            $subscriptions[$channel] = $subscriber->subscribe($channel);
            $iterators[$channel] = $subscriptions[$channel]->getIterator();
        }

        sharedConnectionWaitUntil(
            static fn (): bool => array_values(sharedConnectionNumSub($client, $prefixed)) === [1, 1, 1],
            'every channel is subscribed',
        );

        $original = array_keys(array_diff_key(sharedConnectionPubSubClients($client), $before));

        expect($original)->toHaveCount(1);

        $client->execute('CLIENT', 'KILL', 'ID', (string) $original[0]);

        sharedConnectionWaitUntil(
            static function () use ($client, $before, $original, $prefixed): bool {
                $now = array_diff_key(sharedConnectionPubSubClients($client), $before);

                return !isset($now[$original[0]])
                    && count($now) === 1
                    && array_values(sharedConnectionNumSub($client, $prefixed)) === [1, 1, 1];
            },
            'a new connection re-subscribes every channel',
        );

        foreach ($channels as $channel) {
            $client->publish($prefix . $channel, "after-reconnect-$channel");
        }

        foreach ($channels as $channel) {
            $message = sharedConnectionNextMessage($iterators[$channel], $started);

            expect($message?->channel)->toBe($channel)
                ->and($message?->payload)->toBe("after-reconnect-$channel");
        }
    } finally {
        foreach ($subscriptions as $subscription) {
            $subscription->cancel();
        }
    }
});
