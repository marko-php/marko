<?php

declare(strict_types=1);

use function Amp\async;
use function Amp\delay;

use Amp\TimeoutCancellation;
use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Broadcasting\Amphp\Server\ChannelHub;
use Marko\Broadcasting\Amphp\Server\ReplayBuffer;
use Marko\Broadcasting\Amphp\Server\SseConnection;
use Marko\Broadcasting\Amphp\Tests\Support\InMemoryPubSub;
use Marko\Log\LogLevel;
use Marko\PubSub\Exceptions\PubSubException;
use Marko\PubSub\Message;
use Marko\PubSub\Subscription;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeLogger;

function channelHub(
    InMemoryPubSub $pubSub,
    ?FakeLogger $logger = null,
): ChannelHub {
    return new ChannelHub(
        subscriber: $pubSub,
        replayBuffer: new ReplayBuffer(size: 100, ttl: 300, clock: new FakeClock()),
        amphpBroadcastingConfig: new AmphpBroadcastingConfig(channelPrefix: 'b.'),
        logger: $logger ?? new FakeLogger(),
    );
}

function hubPublish(
    InMemoryPubSub $pubSub,
    string $channel,
    string $id,
    string $event = 'seat.sold',
): void {
    $pubSub->publish('b.' . $channel, new Message('b.' . $channel, json_encode([
        'event' => $event,
        'data' => ['id' => $id],
        'id' => $id,
    ])));
}

function readFrame(
    SseConnection $connection,
): ?string {
    return $connection->stream()->read(new TimeoutCancellation(1));
}

describe('ChannelHub', function (): void {
    it('shares one subscription per channel across connections', function (): void {
        $pubSub = new InMemoryPubSub();
        $hub = channelHub($pubSub);

        $hub->join(new SseConnection(1, ['shows.42'], '127.0.0.1'));
        $hub->join(new SseConnection(2, ['shows.42', 'shows.43'], '127.0.0.1'));

        expect($pubSub->subscribeCalls)->toBe(['b.shows.42', 'b.shows.43'])
            ->and($pubSub->activeSubscriptions('b.shows.42'))->toBe(1)
            ->and($hub->channelCounts())->toBe(['shows.42' => 2, 'shows.43' => 1])
            ->and($hub->connectionCount())->toBe(2);
    });

    it('opens only one subscription when two connections join the same channel concurrently', function (): void {
        $pubSub = new InMemoryPubSub();
        $hub = channelHub($pubSub);

        $first = async(fn () => $hub->join(new SseConnection(1, ['shows.42'], '127.0.0.1')));
        $second = async(fn () => $hub->join(new SseConnection(2, ['shows.42'], '127.0.0.1')));
        $first->await();
        $second->await();

        expect($pubSub->subscribeCalls)->toBe(['b.shows.42'])
            ->and($hub->channelCounts())->toBe(['shows.42' => 2]);
    });

    it('fans a message out to every connection on the channel', function (): void {
        $pubSub = new InMemoryPubSub();
        $hub = channelHub($pubSub);
        $first = new SseConnection(1, ['shows.42'], '127.0.0.1');
        $second = new SseConnection(2, ['shows.42'], '127.0.0.1');
        $hub->join($first);
        $hub->join($second);

        hubPublish($pubSub, 'shows.42', 'evt-1');

        $frame = "id: evt-1\nevent: seat.sold\ndata: {\"id\":\"evt-1\"}\n\n";

        expect(readFrame($first))->toBe($frame)
            ->and(readFrame($second))->toBe($frame);
    });

    it('cancels the subscription when the last connection leaves', function (): void {
        $pubSub = new InMemoryPubSub();
        $hub = channelHub($pubSub);
        $first = new SseConnection(1, ['shows.42'], '127.0.0.1');
        $second = new SseConnection(2, ['shows.42'], '127.0.0.1');
        $hub->join($first);
        $hub->join($second);

        $first->close();

        expect($pubSub->activeSubscriptions('b.shows.42'))->toBe(1)
            ->and($hub->connectionCount())->toBe(1);

        $second->close();

        expect($pubSub->activeSubscriptions('b.shows.42'))->toBe(0)
            ->and($hub->hasChannel('shows.42'))->toBeFalse()
            ->and($hub->connectionCount())->toBe(0);
    });

    it('skips malformed messages and logs an error', function (): void {
        $pubSub = new InMemoryPubSub();
        $logger = new FakeLogger();
        $hub = channelHub($pubSub, $logger);
        $connection = new SseConnection(1, ['shows.42'], '127.0.0.1');
        $hub->join($connection);

        $pubSub->publish('b.shows.42', new Message('b.shows.42', 'not json'));
        $pubSub->publish('b.shows.42', new Message('b.shows.42', '{"event":"x\ny","data":{},"id":"1"}'));
        hubPublish($pubSub, 'shows.42', 'evt-2');

        expect(readFrame($connection))->toStartWith('id: evt-2')
            ->and($logger->entriesForLevel(LogLevel::Error))->toHaveCount(2);
    });

    it(
        'never blocks fan-out on a slow connection and closes it when its pending frames exceed the cap',
        function (): void {
            $pubSub = new InMemoryPubSub();
            $hub = channelHub($pubSub);
            $slow = new SseConnection(1, ['shows.42'], '127.0.0.1');
            $hub->join($slow);

            for ($i = 0; $i <= SseConnection::MAX_PENDING_FRAMES; $i++) {
                hubPublish($pubSub, 'shows.42', "evt-$i");
            }

            delay(0.05);

            expect($slow->isClosed())->toBeTrue()
                ->and($hub->connectionCount())->toBe(0);
        },
    );

    it(
        "closes the channel's connections and logs an error when the subscription ends unexpectedly",
        function (): void {
            $pubSub = new InMemoryPubSub();
            $logger = new FakeLogger();
            $hub = channelHub($pubSub, $logger);
            $connection = new SseConnection(1, ['shows.42'], '127.0.0.1');
            $hub->join($connection);

            $pubSub->endSubscriptions('b.shows.42');
            delay(0.01);

            expect($connection->isClosed())->toBeTrue()
                ->and($hub->hasChannel('shows.42'))->toBeFalse()
                ->and($logger->entriesForLevel(LogLevel::Error)[0]['message'])->toContain('ended unexpectedly');
        },
    );

    it('throws and keeps no channel open when a subscription cannot be opened', function (): void {
        $pubSub = new class () extends InMemoryPubSub
        {
            public function subscribe(
                string ...$channels,
            ): Subscription {
                throw PubSubException::connectionFailed('redis', 'refused');
            }
        };
        $hub = channelHub($pubSub);

        expect(fn () => $hub->join(new SseConnection(1, ['shows.42'], '127.0.0.1')))
            ->toThrow(PubSubException::class)
            ->and($hub->hasChannel('shows.42'))->toBeFalse();
    });

    it("drops the channel's replay history when the subscription is cancelled", function (): void {
        $pubSub = new InMemoryPubSub();
        $hub = channelHub($pubSub);
        $connection = new SseConnection(1, ['shows.42'], '127.0.0.1');
        $hub->join($connection);
        hubPublish($pubSub, 'shows.42', 'evt-1');
        readFrame($connection);
        $connection->close();

        $rejoined = new SseConnection(2, ['shows.42'], '127.0.0.1');
        $hub->join($rejoined, 'evt-1');

        expect(readFrame($rejoined))->toStartWith("event: reset\n");
    });

    it('replays events after the last event id before live delivery', function (): void {
        $pubSub = new InMemoryPubSub();
        $hub = channelHub($pubSub);
        $hub->join(new SseConnection(1, ['shows.42'], '127.0.0.1'));
        hubPublish($pubSub, 'shows.42', 'evt-1');
        hubPublish($pubSub, 'shows.42', 'evt-2');
        hubPublish($pubSub, 'shows.42', 'evt-3');
        delay(0.01);

        $reconnected = new SseConnection(2, ['shows.42'], '127.0.0.1');
        $hub->join($reconnected, 'evt-1');
        hubPublish($pubSub, 'shows.42', 'evt-4');

        expect(readFrame($reconnected))->toStartWith('id: evt-2')
            ->and(readFrame($reconnected))->toStartWith('id: evt-3')
            ->and(readFrame($reconnected))->toStartWith('id: evt-4');
    });

    it('sends heartbeats and closes everything on closeAll', function (): void {
        $pubSub = new InMemoryPubSub();
        $hub = channelHub($pubSub);
        $connection = new SseConnection(1, ['shows.42'], '127.0.0.1');
        $hub->join($connection);

        $hub->heartbeat();
        $hub->closeAll("event: bye\ndata: {}\n\n");

        expect(readFrame($connection))->toBe(":\n\n")
            ->and(readFrame($connection))->toBe("event: bye\ndata: {}\n\n")
            ->and(readFrame($connection))->toBeNull()
            ->and($pubSub->activeSubscriptions('b.shows.42'))->toBe(0);
    });
});
