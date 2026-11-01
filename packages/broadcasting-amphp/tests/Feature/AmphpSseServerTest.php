<?php

declare(strict_types=1);

use function Amp\delay;
use function Amp\Socket\connect;

use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Broadcasting\Amphp\Auth\AmphpSignature;
use Marko\Broadcasting\Amphp\Driver\AmphpBroadcaster;
use Marko\Broadcasting\Amphp\Server\AmphpSseServer;
use Marko\Broadcasting\Amphp\Tests\Support\InMemoryPubSub;
use Marko\Broadcasting\Amphp\Tests\Support\Poll;
use Marko\Broadcasting\Amphp\Tests\Support\SseTestClient;
use Marko\Broadcasting\PrivateChannel;
use Marko\Clock\SystemClock;
use Marko\Log\LogLevel;
use Marko\Testing\Fake\FakeLogger;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver\StreamSelectDriver;

/**
 * @param array<string, mixed> $overrides AmphpBroadcastingConfig constructor arguments
 */
function sseServerConfig(array $overrides = []): AmphpBroadcastingConfig
{
    return new AmphpBroadcastingConfig(...[
        'host' => '127.0.0.1',
        'port' => 0,
        'channelPrefix' => 'b.',
        'appKey' => 'app-secret',
        'heartbeat' => 30,
        'logInterval' => 0,
        ...$overrides,
    ]);
}

/**
 * @param array<string, mixed> $overrides
 * @return array{server: AmphpSseServer, pubSub: InMemoryPubSub, broadcaster: AmphpBroadcaster, signature: AmphpSignature, logger: FakeLogger, port: int}
 */
function startSseServer(
    array $overrides = [],
    ?int $streamTimeout = null,
): array {
    $config = sseServerConfig($overrides);
    $pubSub = new InMemoryPubSub();
    $logger = new FakeLogger();
    $clock = new SystemClock();
    $signature = new AmphpSignature($config, $clock);

    $server = $streamTimeout === null
        ? new AmphpSseServer($config, $pubSub, $signature, $logger, $clock)
        : new class ($config, $pubSub, $signature, $logger, $clock, $streamTimeout) extends AmphpSseServer
        {
            public function __construct(
                AmphpBroadcastingConfig $amphpBroadcastingConfig,
                InMemoryPubSub $subscriber,
                AmphpSignature $amphpSignature,
                FakeLogger $logger,
                SystemClock $clock,
                private readonly int $timeout,
            ) {
                parent::__construct($amphpBroadcastingConfig, $subscriber, $amphpSignature, $logger, $clock);
            }

            protected function streamTimeout(): int
            {
                return $this->timeout;
            }
        };

    $server->start();

    return [
        'server' => $server,
        'pubSub' => $pubSub,
        'broadcaster' => new AmphpBroadcaster($pubSub, $config, $clock),
        'signature' => $signature,
        'logger' => $logger,
        'port' => (int) $server->port(),
    ];
}

afterEach(function (): void {
    if (isset($this->sse)) {
        $this->sse['server']->stop(1.0);
    }
});

describe('AmphpSseServer streaming', function (): void {
    it('delivers a published event to two connected clients', function (): void {
        $this->sse = startSseServer();
        $first = SseTestClient::get($this->sse['port'], '/stream?channels=shows.42');
        $second = SseTestClient::get($this->sse['port'], '/stream?channels=shows.42,shows.43');
        $first->waitFor(":ok\n\n");
        $second->waitFor(":ok\n\n");

        $this->sse['broadcaster']->broadcast('shows.42', 'seat.sold', ['seat' => 'A1'], 'evt-1');

        $frame = "id: evt-1\nevent: seat.sold\ndata: {\"seat\":\"A1\"}\n\n";

        expect($first->waitFor($frame))->toContain($frame)
            ->and($second->waitFor($frame))->toContain($frame)
            ->and($this->sse['pubSub']->subscribeCalls)->toBe(['b.shows.42', 'b.shows.43']);
    });

    it('sends text/event-stream, no-cache and X-Accel-Buffering: no headers', function (): void {
        $this->sse = startSseServer();
        $client = SseTestClient::get($this->sse['port'], '/stream?channels=shows.42');

        expect($client->status)->toBe(200)
            ->and($client->headers['content-type'])->toBe('text/event-stream; charset=utf-8')
            ->and($client->headers['cache-control'])->toBe('no-cache, no-transform')
            ->and($client->headers['x-accel-buffering'])->toBe('no')
            ->and($client->headers)->not->toHaveKey('content-encoding');
    });

    it('sends a heartbeat comment', function (): void {
        $this->sse = startSseServer(['heartbeat' => 1]);
        $client = SseTestClient::get($this->sse['port'], '/stream?channels=shows.42');
        $client->waitFor(":ok\n\n");

        expect($client->waitFor(":\n\n", 3.0))->toBe(":ok\n\n:\n\n");
    });

    it('keeps an idle stream open longer than the HTTP driver stream timeout', function (): void {
        // amphp checks stream timeouts against a whole-second clock it refreshes once a second, so a
        // write can expire up to 2s early. A 5s timeout keeps the 1s heartbeat clear of that, even when
        // the process stalls; the wait still outlasts the latest a heartbeat-less stream would be dropped.
        $this->sse = startSseServer(['heartbeat' => 1], streamTimeout: 5);
        $client = SseTestClient::get($this->sse['port'], '/stream?channels=shows.42');
        $client->waitFor(":ok\n\n");
        delay(7.0);

        $this->sse['broadcaster']->broadcast('shows.42', 'seat.sold', [], 'evt-late');

        expect($client->waitFor('id: evt-late'))->toContain('id: evt-late');
    });

    it('frees the slot and subscription when the last client disconnects', function (): void {
        $this->sse = startSseServer(['maxConnections' => 1, 'heartbeat' => 1]);
        $client = SseTestClient::get($this->sse['port'], '/stream?channels=shows.42');
        $client->waitFor(":ok\n\n");

        expect($this->sse['pubSub']->activeSubscriptions('b.shows.42'))->toBe(1);

        // HTTP/1 does not read from the socket while a response streams, so the disconnect
        // surfaces on a later heartbeat write. The slot is released in the same close as the
        // subscription, so once the subscription is gone the slot is free.
        $client->close();
        Poll::until(
            fn (): bool => $this->sse['pubSub']->activeSubscriptions('b.shows.42') === 0,
            'the disconnected stream to cancel its subscription',
        );

        $next = SseTestClient::get($this->sse['port'], '/stream?channels=shows.43');

        expect($this->sse['pubSub']->activeSubscriptions('b.shows.42'))->toBe(0)
            ->and($this->sse['server']->channelHub()?->hasChannel('shows.42'))->toBeFalse()
            ->and($next->status)->toBe(200);
    });

    it('returns 400 when channels is missing or empty', function (string $target): void {
        $this->sse = startSseServer();

        expect(SseTestClient::get($this->sse['port'], $target, close: true)->status)->toBe(400);
    })->with(['/stream', '/stream?channels=', '/stream?channels=,,', '/stream?channels=bad%20name']);

    it('returns 400 when more than the maximum channels are requested', function (): void {
        $this->sse = startSseServer();
        $channels = implode(',', array_map(fn (int $i): string => "c$i", range(1, 51)));

        expect(SseTestClient::get($this->sse['port'], "/stream?channels=$channels", close: true)->status)->toBe(400)
            ->and($this->sse['pubSub']->subscribeCalls)->toBeEmpty();
    });

    it('returns 404 for unknown paths', function (): void {
        $this->sse = startSseServer();

        expect(SseTestClient::get($this->sse['port'], '/nope', close: true)->status)->toBe(404);
    });

    it('reports only aggregate counts on the health endpoint by default', function (): void {
        $this->sse = startSseServer();
        SseTestClient::get($this->sse['port'], '/stream?channels=shows.42')->waitFor(":ok\n\n");
        $token = $this->sse['signature']->sign(7, ['private-users.7'], time() + 60);
        SseTestClient::get($this->sse['port'], "/stream?channels=shows.42,private-users.7&token=$token")
            ->waitFor(":ok\n\n");

        $health = SseTestClient::get(
            $this->sse['port'],
            '/health',
            ['X-Health-Secret' => 'anything'],
            close: true,
        );
        $body = $health->waitForEnd();

        expect($health->status)->toBe(200)
            ->and(json_decode($body, true))->toBe([
                'status' => 'ok',
                'connections' => 2,
                'channels' => 2,
            ])
            ->and($body)->not->toContain('private-users.7');
    });

    it('reports per-channel counts when health detail is enabled and the secret matches', function (): void {
        $this->sse = startSseServer(['healthDetail' => true, 'healthSecret' => 'health-secret']);
        SseTestClient::get($this->sse['port'], '/stream?channels=shows.42')->waitFor(":ok\n\n");
        SseTestClient::get($this->sse['port'], '/stream?channels=shows.42,shows.43')->waitFor(":ok\n\n");

        $health = SseTestClient::get(
            $this->sse['port'],
            '/health',
            ['X-Health-Secret' => 'health-secret'],
            close: true,
        );

        expect($health->status)->toBe(200)
            ->and(json_decode($health->waitForEnd(), true))->toBe([
                'status' => 'ok',
                'connections' => 2,
                'channels' => 2,
                'channel_counts' => ['shows.42' => 2, 'shows.43' => 1],
            ]);
    });

    it('hides per-channel health counts from a missing or wrong secret', function (): void {
        $this->sse = startSseServer(['healthDetail' => true, 'healthSecret' => 'health-secret']);
        $token = $this->sse['signature']->sign(7, ['private-users.7'], time() + 60);
        SseTestClient::get($this->sse['port'], "/stream?channels=private-users.7&token=$token")->waitFor(":ok\n\n");

        $missing = SseTestClient::get($this->sse['port'], '/health', close: true);
        $wrong = SseTestClient::get(
            $this->sse['port'],
            '/health',
            ['X-Health-Secret' => 'health-secreT'],
            close: true,
        );
        $missingBody = $missing->waitForEnd();
        $wrongBody = $wrong->waitForEnd();

        expect(json_decode($missingBody, true))->toBe(['status' => 'ok', 'connections' => 1, 'channels' => 1])
            ->and(json_decode($wrongBody, true))->toBe(['status' => 'ok', 'connections' => 1, 'channels' => 1])
            ->and($missingBody . $wrongBody)->not->toContain('private-users.7');
    });
});

describe('AmphpSseServer CORS', function (): void {
    it('sends CORS headers for allowed origins', function (): void {
        $this->sse = startSseServer(['allowedOrigins' => ['https://app.test']]);
        $client = SseTestClient::get(
            $this->sse['port'],
            '/stream?channels=shows.42',
            ['Origin' => 'https://app.test'],
        );

        expect($client->status)->toBe(200)
            ->and($client->headers['access-control-allow-origin'])->toBe('https://app.test')
            ->and($client->headers['vary'])->toBe('Origin');
    });

    it('sends a wildcard CORS header when any origin is allowed', function (): void {
        $this->sse = startSseServer();
        $client = SseTestClient::get(
            $this->sse['port'],
            '/stream?channels=shows.42',
            ['Origin' => 'https://other.test'],
        );

        expect($client->headers['access-control-allow-origin'])->toBe('*');
    });

    it('rejects origins that are not allowed', function (): void {
        $this->sse = startSseServer(['allowedOrigins' => ['https://app.test']]);
        $client = SseTestClient::get(
            $this->sse['port'],
            '/stream?channels=shows.42',
            ['Origin' => 'https://evil.test'],
            close: true,
        );

        expect($client->status)->toBe(403)
            ->and($client->headers)->not->toHaveKey('access-control-allow-origin')
            ->and($this->sse['pubSub']->subscribeCalls)->toBeEmpty();
    });

    it('answers preflight requests', function (): void {
        $this->sse = startSseServer(['allowedOrigins' => ['https://app.test']]);
        $socket = connect('tcp://127.0.0.1:' . $this->sse['port']);
        $socket->write(
            "OPTIONS /stream HTTP/1.1\r\nHost: 127.0.0.1\r\nOrigin: https://app.test\r\nConnection: close\r\n\r\n",
        );
        $response = '';

        while (($chunk = $socket->read()) !== null) {
            $response .= $chunk;
        }

        expect($response)->toStartWith('HTTP/1.1 204')
            ->toContain('access-control-allow-headers: Authorization, Last-Event-ID, Cache-Control');
    });
});

describe('AmphpSseServer private channels', function (): void {
    it('streams a private channel with a valid token', function (): void {
        $this->sse = startSseServer();
        $token = $this->sse['signature']->sign(7, ['private-orders.7'], time() + 60);
        $client = SseTestClient::get($this->sse['port'], "/stream?channels=private-orders.7&token=$token");
        $client->waitFor(":ok\n\n");

        $this->sse['broadcaster']->broadcast(new PrivateChannel('orders.7'), 'order.shipped', [], 'evt-1');

        expect($client->status)->toBe(200)
            ->and($client->waitFor('id: evt-1'))->toContain('event: order.shipped');
    });

    it('accepts the token as an Authorization bearer header', function (): void {
        $this->sse = startSseServer();
        $token = $this->sse['signature']->sign(7, ['private-orders.7'], time() + 60);
        $client = SseTestClient::get(
            $this->sse['port'],
            '/stream?channels=private-orders.7',
            ['Authorization' => "Bearer $token"],
        );

        expect($client->status)->toBe(200);
    });

    it('returns 403 for a private channel without, with expired, or with forged token', function (string $kind): void {
        $this->sse = startSseServer();
        $token = match ($kind) {
            'missing' => null,
            'expired' => $this->sse['signature']->sign(7, ['private-orders.7'], time() - 10),
            'forged' => new AmphpSignature(sseServerConfig(['appKey' => 'attacker']), new SystemClock())->sign(
                7,
                ['private-orders.7'],
                time() + 60,
            ),
        };
        $target = '/stream?channels=private-orders.7' . ($token === null ? '' : "&token=$token");

        expect(SseTestClient::get($this->sse['port'], $target, close: true)->status)->toBe(403)
            ->and($this->sse['pubSub']->subscribeCalls)->toBeEmpty();
    })->with(['missing', 'expired', 'forged']);

    it('returns 403 when the token does not list the requested private channel', function (): void {
        $this->sse = startSseServer();
        $token = $this->sse['signature']->sign(7, ['private-orders.7'], time() + 60);

        expect(
            SseTestClient::get(
                $this->sse['port'],
                "/stream?channels=private-orders.8&token=$token",
                close: true,
            )->status,
        )
            ->toBe(403);
    });

    it('returns 403 for private channels when app_key is empty', function (): void {
        $this->sse = startSseServer(['appKey' => '']);
        $token = new AmphpSignature(sseServerConfig(), new SystemClock())->sign(7, ['private-orders.7'], time() + 60);

        expect(
            SseTestClient::get(
                $this->sse['port'],
                "/stream?channels=private-orders.7&token=$token",
                close: true,
            )->status,
        )
            ->toBe(403)
            ->and(array_column($this->sse['logger']->entriesForLevel(LogLevel::Warning), 'message'))
            ->toContain('broadcasting-amphp.app_key is empty: requests for private channels will be refused with 403');
    });
});

describe('AmphpSseServer limits', function (): void {
    it('returns 503 with Retry-After over max_connections', function (): void {
        $this->sse = startSseServer(['maxConnections' => 2]);
        SseTestClient::get($this->sse['port'], '/stream?channels=shows.42')->waitFor(":ok\n\n");
        SseTestClient::get($this->sse['port'], '/stream?channels=shows.42')->waitFor(":ok\n\n");

        $rejected = SseTestClient::get($this->sse['port'], '/stream?channels=shows.42', close: true);

        expect($rejected->status)->toBe(503)
            ->and($rejected->headers['retry-after'])->toBe('5');
    });

    it('returns 503 with Retry-After over max_connections_per_ip', function (): void {
        $this->sse = startSseServer(['maxConnectionsPerIp' => 1]);
        SseTestClient::get($this->sse['port'], '/stream?channels=shows.42')->waitFor(":ok\n\n");

        $rejected = SseTestClient::get($this->sse['port'], '/stream?channels=shows.43', close: true);

        expect($rejected->status)->toBe(503)
            ->and($rejected->headers['retry-after'])->toBe('5')
            ->and($this->sse['pubSub']->subscribeCalls)->toBe(['b.shows.42']);
    });

    it('caps max_connections below FD_SETSIZE when the loop uses stream_select', function (): void {
        if (!EventLoop::getDriver() instanceof StreamSelectDriver) {
            $this->markTestSkipped('An ev/uv/event loop driver is installed, so stream_select() limits do not apply.');
        }

        $this->sse = startSseServer(['maxConnections' => 5000]);

        expect(array_column($this->sse['logger']->entriesForLevel(LogLevel::Warning), 'message'))
            ->toContain(
                'The event loop is using stream_select(), which cannot watch more than 1024 sockets: capping max_connections from 5000 to 1000. Install ext-ev, ext-uv or ext-event for more connections.',
            );
    });

    it('uses X-Forwarded-For only from trusted_proxies', function (): void {
        $this->sse = startSseServer(['maxConnectionsPerIp' => 1, 'trustedProxies' => ['127.0.0.1']]);
        SseTestClient::get(
            $this->sse['port'],
            '/stream?channels=shows.42',
            ['X-Forwarded-For' => '203.0.113.1'],
        )->waitFor(
            ":ok\n\n",
        );

        $otherClient = SseTestClient::get(
            $this->sse['port'],
            '/stream?channels=shows.42',
            ['X-Forwarded-For' => '203.0.113.2'],
        );
        $sameClient = SseTestClient::get(
            $this->sse['port'],
            '/stream?channels=shows.42',
            ['X-Forwarded-For' => '203.0.113.1'],
            close: true,
        );

        expect($otherClient->status)->toBe(200)
            ->and($sameClient->status)->toBe(503);
    });

    it('ignores X-Forwarded-For from untrusted peers', function (): void {
        $this->sse = startSseServer(['maxConnectionsPerIp' => 1]);
        SseTestClient::get(
            $this->sse['port'],
            '/stream?channels=shows.42',
            ['X-Forwarded-For' => '203.0.113.1'],
        )->waitFor(
            ":ok\n\n",
        );

        $spoofed = SseTestClient::get(
            $this->sse['port'],
            '/stream?channels=shows.42',
            ['X-Forwarded-For' => '203.0.113.2'],
            close: true,
        );

        expect($spoofed->status)->toBe(503);
    });
});

describe('AmphpSseServer replay', function (): void {
    it('replays events after Last-Event-ID in order', function (): void {
        $this->sse = startSseServer();
        $listener = SseTestClient::get($this->sse['port'], '/stream?channels=shows.42,shows.43');
        $listener->waitFor(":ok\n\n");

        foreach (['evt-1' => 'shows.42', 'evt-2' => 'shows.43', 'evt-3' => 'shows.42'] as $id => $channel) {
            $this->sse['broadcaster']->broadcast($channel, 'seat.sold', [], $id);
        }

        $listener->waitFor('id: evt-3');
        $listener->waitFor('id: evt-2');
        // Events on different channels are ordered by when this process received them.
        $live = substr($listener->body, strlen(":ok\n\nid: evt-1\nevent: seat.sold\ndata: {}\n\n"));

        $reconnected = SseTestClient::get(
            $this->sse['port'],
            '/stream?channels=shows.42,shows.43',
            ['Last-Event-ID' => 'evt-1'],
        );
        $reconnected->waitFor('id: evt-3');
        $reconnected->waitFor('id: evt-2');

        expect($listener->body)->toStartWith(":ok\n\nid: evt-1\n")
            ->and($reconnected->body)->toBe(":ok\n\n" . $live);
    });

    it('accepts the last event id as a lastEventId query parameter', function (): void {
        $this->sse = startSseServer();
        $listener = SseTestClient::get($this->sse['port'], '/stream?channels=shows.42');
        $listener->waitFor(":ok\n\n");
        $this->sse['broadcaster']->broadcast('shows.42', 'seat.sold', [], 'evt-1');
        $this->sse['broadcaster']->broadcast('shows.42', 'seat.sold', [], 'evt-2');
        $listener->waitFor('id: evt-2');

        $reconnected = SseTestClient::get($this->sse['port'], '/stream?channels=shows.42&lastEventId=evt-1');

        expect($reconnected->waitFor('id: evt-2'))->not->toContain('id: evt-1');
    });

    it('sends reset when the Last-Event-ID is too old', function (): void {
        $this->sse = startSseServer(['replayBuffer' => 2]);
        $listener = SseTestClient::get($this->sse['port'], '/stream?channels=shows.42');
        $listener->waitFor(":ok\n\n");

        foreach (['evt-1', 'evt-2', 'evt-3'] as $id) {
            $this->sse['broadcaster']->broadcast('shows.42', 'seat.sold', [], $id);
        }

        $listener->waitFor('id: evt-3');
        $reconnected = SseTestClient::get(
            $this->sse['port'],
            '/stream?channels=shows.42',
            ['Last-Event-ID' => 'evt-1'],
        );

        expect($reconnected->waitFor("event: reset\n"))->not->toContain('id: evt-');
    });

    it('sends reset when the Last-Event-ID is unknown', function (): void {
        $this->sse = startSseServer();
        $client = SseTestClient::get(
            $this->sse['port'],
            '/stream?channels=shows.42',
            ['Last-Event-ID' => 'never-seen'],
        );

        expect($client->waitFor("event: reset\n"))->toContain('data: {"reason":"last-event-id-unavailable"}');
    });

    it('does not duplicate or drop an event published while replaying', function (): void {
        $this->sse = startSseServer();
        $listener = SseTestClient::get($this->sse['port'], '/stream?channels=shows.42');
        $listener->waitFor(":ok\n\n");
        $this->sse['broadcaster']->broadcast('shows.42', 'seat.sold', [], 'evt-1');
        $this->sse['broadcaster']->broadcast('shows.42', 'seat.sold', [], 'evt-2');
        $listener->waitFor('id: evt-2');

        $reconnected = SseTestClient::get(
            $this->sse['port'],
            '/stream?channels=shows.42',
            ['Last-Event-ID' => 'evt-1'],
        );
        $this->sse['broadcaster']->broadcast('shows.42', 'seat.sold', [], 'evt-3');
        $body = $reconnected->waitFor('id: evt-3');

        expect(substr_count($body, 'id: evt-2'))->toBe(1)
            ->and(substr_count($body, 'id: evt-3'))->toBe(1)
            ->and(strpos($body, 'id: evt-2'))->toBeLessThan(strpos($body, 'id: evt-3'));
    });
});

describe('AmphpSseServer shutdown', function (): void {
    it('closes streams with a reconnect event on shutdown', function (): void {
        $sse = startSseServer();
        $client = SseTestClient::get($sse['port'], '/stream?channels=shows.42');
        $client->waitFor(":ok\n\n");

        $sse['server']->stop(2.0);

        expect($client->waitForEnd())->toEndWith("retry: 1000\nevent: reconnect\ndata: {}\n\n")
            ->and($sse['server']->isRunning())->toBeFalse();
    });

    it('cancels every pubsub subscription and timer on shutdown so the event loop can exit', function (): void {
        $sse = startSseServer(['heartbeat' => 1, 'logInterval' => 1]);
        SseTestClient::get($sse['port'], '/stream?channels=shows.42,shows.43')->waitFor(":ok\n\n");
        $before = count(EventLoop::getIdentifiers());

        $sse['server']->stop(2.0);
        Poll::until(
            fn (): bool => $sse['pubSub']->activeSubscriptions('b.shows.42') === 0
                && $sse['pubSub']->activeSubscriptions('b.shows.43') === 0
                && count(EventLoop::getIdentifiers()) < $before,
            'the shutdown to cancel every subscription and timer',
        );

        expect($sse['pubSub']->activeSubscriptions('b.shows.42'))->toBe(0)
            ->and($sse['pubSub']->activeSubscriptions('b.shows.43'))->toBe(0)
            ->and(count(EventLoop::getIdentifiers()))->toBeLessThan($before);
    });

    it('logs connection counts every log_interval seconds', function (): void {
        $this->sse = startSseServer(['logInterval' => 1]);
        // The interval timer starts with the server, so the first tick can still see 0 streams
        // under load; wait for the tick that sees the connected client.
        $client = SseTestClient::get($this->sse['port'], '/stream?channels=shows.42');
        $client->waitFor(":ok\n\n");
        $infoMessages = fn (): array => array_column(
            $this->sse['logger']->entriesForLevel(LogLevel::Info),
            'message',
        );
        Poll::until(
            fn (): bool => in_array('Broadcasting server: 1 open streams across 1 channels', $infoMessages(), true),
            'the log_interval tick to log the open stream',
        );

        expect($infoMessages())->toContain('Broadcasting server: 1 open streams across 1 channels')
            ->and($client->status)->toBe(200);
    });

    it('does not log connection counts when log_interval is 0', function (): void {
        $this->sse = startSseServer();
        delay(0.1);

        expect(array_filter(
            array_column($this->sse['logger']->entriesForLevel(LogLevel::Info), 'message'),
            fn (string $message): bool => str_starts_with($message, 'Broadcasting server:'),
        ))->toBeEmpty();
    });
});
