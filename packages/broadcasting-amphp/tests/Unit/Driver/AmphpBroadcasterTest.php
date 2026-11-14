<?php

declare(strict_types=1);

use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Broadcasting\Amphp\Driver\AmphpBroadcaster;
use Marko\Broadcasting\Amphp\Exceptions\AmphpBroadcastException;
use Marko\Broadcasting\Amphp\Tests\Support\InMemoryPubSub;
use Marko\Broadcasting\BroadcastableInterface;
use Marko\Broadcasting\Exceptions\BroadcastException;
use Marko\Broadcasting\PresenceChannel;
use Marko\Broadcasting\PrivateChannel;
use Marko\PubSub\Exceptions\PubSubException;
use Marko\PubSub\Message;
use Marko\PubSub\PgSql\Driver\PgSqlPublisher;
use Marko\PubSub\PgSql\PgSqlPubSubConnection;
use Marko\PubSub\PublisherInterface;
use Marko\PubSub\PubSubConfig;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

function amphpBroadcaster(
    PublisherInterface $publisher,
    string $channelPrefix = 'broadcast.',
    ?FakeClock $clock = null,
): AmphpBroadcaster {
    return new AmphpBroadcaster(
        publisher: $publisher,
        amphpBroadcastingConfig: new AmphpBroadcastingConfig(channelPrefix: $channelPrefix),
        clock: $clock ?? new FakeClock(),
    );
}

/**
 * @return array<string, mixed>
 */
function publishedPayload(
    InMemoryPubSub $pubSub,
    int $index = 0,
): array {
    return json_decode($pubSub->published[$index]['message']->payload, true, flags: JSON_THROW_ON_ERROR);
}

describe('AmphpBroadcaster', function (): void {
    it('publishes to the prefixed channel', function (): void {
        $pubSub = new InMemoryPubSub();

        amphpBroadcaster($pubSub)->broadcast('shows.42', 'seat.sold', ['seat' => 'A1'], 'evt-1');

        expect($pubSub->published)->toHaveCount(1)
            ->and($pubSub->published[0]['channel'])->toBe('broadcast.shows.42')
            ->and($pubSub->published[0]['message']->channel)->toBe('broadcast.shows.42');
    });

    it('publishes private channels under the private- prefix', function (): void {
        $pubSub = new InMemoryPubSub();

        amphpBroadcaster($pubSub, '')->broadcast(new PrivateChannel('orders.7'), 'order.shipped', []);

        expect($pubSub->published[0]['channel'])->toBe('private-orders.7');
    });

    it('encodes event data and id as JSON', function (): void {
        $pubSub = new InMemoryPubSub();

        amphpBroadcaster($pubSub)->broadcast('shows.42', 'seat.sold', ['seat' => 'A1', 'name' => 'Café/1'], 'evt-1');

        expect($pubSub->published[0]['message']->payload)
            ->toBe('{"event":"seat.sold","data":{"seat":"A1","name":"Café/1"},"id":"evt-1"}');
    });

    it('encodes empty data as a JSON object', function (): void {
        $pubSub = new InMemoryPubSub();

        amphpBroadcaster($pubSub)->broadcast('shows.42', 'ping', [], 'evt-1');

        expect($pubSub->published[0]['message']->payload)->toBe('{"event":"ping","data":{},"id":"evt-1"}');
    });

    it('generates a sortable id when none is given', function (): void {
        $pubSub = new InMemoryPubSub();
        $clock = new FakeClock();
        $broadcaster = amphpBroadcaster($pubSub, clock: $clock);

        $broadcaster->broadcast('shows.42', 'seat.sold', []);
        $clock->travel('+2 milliseconds');
        $broadcaster->broadcast('shows.42', 'seat.sold', []);

        $first = publishedPayload($pubSub)['id'];
        $second = publishedPayload($pubSub, 1)['id'];

        expect($first)->toMatch('/^\d{13}-[0-9a-f]{16}$/')
            ->and($first)->not->toBe($second)
            ->and(strcmp($first, $second))->toBeLessThan(0);
    });

    it('prefixes generated event ids with the injected clock in unix milliseconds', function (): void {
        $pubSub = new InMemoryPubSub();

        amphpBroadcaster($pubSub, clock: new FakeClock('2026-01-01 12:00:00.123456 UTC'))
            ->broadcast('shows.42', 'seat.sold', []);

        expect(publishedPayload($pubSub)['id'])->toStartWith('1767268800123-');
    });

    it('rejects public channel names that start with private-', function (): void {
        amphpBroadcaster(new InMemoryPubSub())->broadcast('private-orders.7', 'order.shipped', []);
    })->throws(AmphpBroadcastException::class, "Public channel 'private-orders.7' uses the reserved private- prefix.");

    it('rejects channel names with characters outside the allowed set', function (): void {
        amphpBroadcaster(new InMemoryPubSub())->broadcast('orders/7', 'order.shipped', []);
    })->throws(BroadcastException::class, "Channel name 'orders/7' is not valid for Amphp.");

    it('rejects an empty event name', function (): void {
        amphpBroadcaster(new InMemoryPubSub())->broadcast('orders.7', '', []);
    })->throws(BroadcastException::class, 'Broadcast event name must not be empty.');

    it('rejects event names and ids containing CR or LF', function (string $event, ?string $id): void {
        expect(fn () => amphpBroadcaster(new InMemoryPubSub())->broadcast('orders.7', $event, [], $id))
            ->toThrow(AmphpBroadcastException::class, 'would break the SSE frame');
    })->with([
        'event with LF' => ["shipped\ndata: injected", null],
        'event with CR' => ["shipped\r", null],
        'id with LF' => ['shipped', "evt-1\nevent: forged"],
    ]);

    it('wraps publisher failures in BroadcastException::publishFailed', function (): void {
        $publisher = new class () implements PublisherInterface
        {
            public function publish(
                string $channel,
                Message $message,
            ): void {
                throw PubSubException::publishFailed($channel, 'connection refused');
            }
        };

        amphpBroadcaster($publisher)->broadcast('orders.7', 'order.shipped', []);
    })->throws(BroadcastException::class, "Failed to broadcast to channel 'orders.7' via Amphp.");

    it('throws when the payload exceeds the PostgreSQL NOTIFY limit', function (): void {
        $publisher = new PgSqlPublisher(
            connection: new PgSqlPubSubConnection(),
            config: new PubSubConfig(new FakeConfigRepository(['pubsub.prefix' => 'marko:'])),
        );

        amphpBroadcaster($publisher)->broadcast('shows.42', 'seat.sold', ['blob' => str_repeat('x', 8000)]);
    })->throws(AmphpBroadcastException::class, 'exceeds the PostgreSQL NOTIFY limit of 7999 bytes');

    it('allows large payloads on drivers without a size limit', function (): void {
        $pubSub = new InMemoryPubSub();

        amphpBroadcaster($pubSub)->broadcast('shows.42', 'seat.sold', ['blob' => str_repeat('x', 9000)]);

        expect($pubSub->published)->toHaveCount(1);
    });

    it('dispatches a broadcastable to each channel', function (): void {
        $pubSub = new InMemoryPubSub();

        amphpBroadcaster($pubSub, '')->dispatch(new class () implements BroadcastableInterface
        {
            public function channels(): array
            {
                return ['shows.42', new PrivateChannel('users.7')];
            }

            public function event(): string
            {
                return 'seat.sold';
            }

            public function payload(): array
            {
                return ['seat' => 'A1'];
            }
        });

        expect(array_column($pubSub->published, 'channel'))->toBe(['shows.42', 'private-users.7'])
            ->and(publishedPayload($pubSub)['event'])->toBe('seat.sold');
    });

    it('throws a clear exception when broadcasting to a presence channel', function (): void {
        $pubSub = new InMemoryPubSub();

        expect(fn () => amphpBroadcaster($pubSub)->broadcast(new PresenceChannel('room.1'), 'user.joined', []))
            ->toThrow(BroadcastException::class, "Presence channel 'room.1' is not supported by Amphp.")
            ->and($pubSub->published)->toBeEmpty();
    });
});
