<?php

declare(strict_types=1);

use Marko\Broadcasting\BroadcastableInterface;
use Marko\Broadcasting\Exceptions\BroadcastException;
use Marko\Broadcasting\PresenceChannel;
use Marko\Broadcasting\PrivateChannel;
use Marko\Broadcasting\Pusher\Auth\PusherSignature;
use Marko\Broadcasting\Pusher\Driver\PusherBroadcaster;
use Marko\Broadcasting\Pusher\PusherConfig;
use Marko\Http\Exceptions\ConnectionException;
use Marko\Http\Exceptions\HttpException;
use Marko\Http\HttpResponse;
use Marko\Http\RequestOptions;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeHttpClient;
use Marko\Testing\Fake\Http\RecordedRequest;

const PUSHER_TEST_EVENTS_URL = 'http://soketi.test:6001/apps/3/events';

function pusherBroadcaster(
    FakeHttpClient $httpClient,
    ?FakeClock $clock = null,
): PusherBroadcaster {
    $pusherConfig = new PusherConfig(
        appId: '3',
        key: '278d425bdf160c739803',
        secret: '7ad3773142a6692b25b8',
        host: 'soketi.test',
        port: 6001,
        scheme: 'http',
    );

    return new PusherBroadcaster(
        httpClient: $httpClient,
        pusherSignature: new PusherSignature($pusherConfig),
        pusherConfig: $pusherConfig,
        clock: $clock ?? new FakeClock(),
    );
}

/**
 * A FakeHttpClient whose events endpoint answers every signed request (whatever
 * its auth query string) with the given response or failure.
 */
function pusherApi(
    HttpResponse|HttpException $response = new HttpResponse(200, '{}'),
): FakeHttpClient {
    return new FakeHttpClient()->stub(PUSHER_TEST_EVENTS_URL . '?*', $response);
}

/**
 * @return array<string, mixed>
 */
function pusherRequestBody(
    FakeHttpClient $httpClient,
    int $index = 0,
): array {
    return json_decode($httpClient->requests[$index]->body(), true);
}

/**
 * @return array<string, string>
 */
function pusherRequestQuery(
    FakeHttpClient $httpClient,
): array {
    parse_str((string) parse_url($httpClient->requests[0]->url, PHP_URL_QUERY), $query);

    return $query;
}

/**
 * A FakeHttpClient that fails like Guzzle does when the server is down: the
 * ConnectionException message ends with the full request URL, signed query included.
 */
function pusherUnreachableApi(): FakeHttpClient
{
    return new class () extends FakeHttpClient
    {
        public function request(
            string $method,
            string $url,
            array $options = [],
        ): HttpResponse {
            $this->preventStrayRequests(false);
            parent::request($method, $url, $options);

            throw new ConnectionException(
                "cURL error 7: Failed to connect to soketi.test port 6001 after 0 ms: Couldn't connect to server "
                . "(see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for $url",
            );
        }
    };
}

describe('PusherBroadcaster', function (): void {
    it('posts name, channels and json data to the events endpoint', function (): void {
        $httpClient = pusherApi();

        pusherBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', ['seat' => 'A1']);

        expect($httpClient->requests)->toHaveCount(1)
            ->and($httpClient)->toHaveSentRequest(fn (RecordedRequest $request): bool => $request->method === 'POST'
                && strtok($request->url, '?') === PUSHER_TEST_EVENTS_URL
                && $request->header('Content-Type') === 'application/json'
                && $request->options['timeout'] === 5)
            ->and(pusherRequestBody($httpClient))->toBe([
                'name' => 'seat.sold',
                'channels' => ['shows.42'],
                'data' => '{"seat":"A1"}',
            ]);
    });

    it('signs the request with auth query parameters', function (): void {
        $httpClient = pusherApi();

        pusherBroadcaster($httpClient, new FakeClock('@1767268800'))->broadcast('shows.42', 'seat.sold', []);

        $query = pusherRequestQuery($httpClient);
        $body = $httpClient->requests[0]->body();
        $unsigned = array_diff_key($query, ['auth_signature' => true]);

        expect($query['auth_key'])->toBe('278d425bdf160c739803')
            ->and($query['auth_version'])->toBe('1.0')
            ->and($query['body_md5'])->toBe(md5($body))
            ->and($query['auth_timestamp'])->toBe('1767268800')
            ->and($query['auth_signature'])->toBe(hash_hmac(
                'sha256',
                "POST\n/apps/3/events\n" . urldecode(http_build_query($unsigned)),
                '7ad3773142a6692b25b8',
            ));
    });

    it('prefixes private channels with private-', function (): void {
        $httpClient = pusherApi();

        pusherBroadcaster($httpClient)->broadcast(new PrivateChannel('orders.7'), 'order.shipped', []);

        expect(pusherRequestBody($httpClient)['channels'])->toBe(['private-orders.7']);
    });

    it('prefixes presence channels with presence-', function (): void {
        $httpClient = pusherApi();

        pusherBroadcaster($httpClient)->broadcast(new PresenceChannel('room.7'), 'member.joined', []);

        expect(pusherRequestBody($httpClient)['channels'])->toBe(['presence-room.7']);
    });

    it('counts the presence- prefix toward the channel name length limit', function (): void {
        $httpClient = pusherApi();

        pusherBroadcaster($httpClient)->broadcast(new PresenceChannel(str_repeat('a', 155)), 'e', []);

        expect(fn () => pusherBroadcaster($httpClient)->broadcast(new PresenceChannel(str_repeat('a', 156)), 'e', []))
            ->toThrow(BroadcastException::class, 'is not valid for Pusher')
            ->and($httpClient->requests)->toHaveCount(1);
    });

    it('rejects channel names with characters Pusher does not allow', function (): void {
        $httpClient = new FakeHttpClient();

        expect(fn () => pusherBroadcaster($httpClient)->broadcast('shows/42', 'seat.sold', []))
            ->toThrow(BroadcastException::class, "Channel name 'shows/42' is not valid for Pusher")
            ->and($httpClient->requests)->toBeEmpty();
    });

    it('rejects channel names longer than 164 characters', function (): void {
        $httpClient = new FakeHttpClient();

        expect(fn () => pusherBroadcaster($httpClient)->broadcast(str_repeat('a', 165), 'e', []))
            ->toThrow(BroadcastException::class, 'is not valid for Pusher')
            ->and($httpClient->requests)->toBeEmpty();
    });

    it('throws BroadcastException when the api request fails', function (): void {
        $httpClient = pusherApi(new ConnectionException('Connection refused'));

        expect(fn () => pusherBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []))
            ->toThrow(BroadcastException::class, "Failed to broadcast to channel 'shows.42' via Pusher");
    });

    it('redacts the signed query string from transport failure messages', function (): void {
        $httpClient = pusherUnreachableApi();

        try {
            pusherBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []);
            $this->fail('Expected BroadcastException');
        } catch (BroadcastException $exception) {
            $signature = pusherRequestQuery($httpClient)['auth_signature'];
            $text = $exception->getMessage() . $exception->getContext() . $exception->getSuggestion();

            expect($text)->toContain('Failed to connect to soketi.test port 6001')
                ->and($text)->toContain(PUSHER_TEST_EVENTS_URL)
                ->and($text)->not->toContain('auth_signature')
                ->and($text)->not->toContain('auth_key')
                ->and($text)->not->toContain($signature);
        }
    });

    it('does not chain the transport exception that carries the signed url', function (): void {
        $httpClient = pusherUnreachableApi();

        try {
            pusherBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []);
            $this->fail('Expected BroadcastException');
        } catch (BroadcastException $exception) {
            expect($exception->getPrevious())->toBeNull();
        }
    });

    it('sends the trigger request with http_errors disabled', function (): void {
        $httpClient = pusherApi();

        pusherBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []);

        expect($httpClient->requests[0]->options[RequestOptions::HTTP_ERRORS])->toBeFalse();
    });

    it('puts the api status and response body in the exception when the api rejects the event', function (): void {
        $httpClient = pusherApi(new HttpResponse(413, 'Payload too large'));

        try {
            pusherBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []);
            $this->fail('Expected BroadcastException');
        } catch (BroadcastException $exception) {
            expect($exception->getMessage())->toBe("Failed to broadcast to channel 'shows.42' via Pusher.")
                ->and($exception->getContext())->toContain('HTTP 413: Payload too large')
                ->and($exception->getSuggestion())->toContain('10 KB');
        }
    });

    it('suggests checking credentials when the api rejects the signature', function (): void {
        $httpClient = pusherApi(new HttpResponse(401, 'Invalid signature: you should have sent HmacSHA256Hex(...)'));

        try {
            pusherBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []);
            $this->fail('Expected BroadcastException');
        } catch (BroadcastException $exception) {
            expect($exception->getContext())->toContain('HTTP 401: Invalid signature')
                ->and($exception->getSuggestion())->toContain('config/broadcasting-pusher.php');
        }
    });

    it('does not put the signed query string in the exception when the api rejects the event', function (): void {
        $httpClient = pusherApi(new HttpResponse(400, 'Unknown app'));

        try {
            pusherBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []);
            $this->fail('Expected BroadcastException');
        } catch (BroadcastException $exception) {
            $signature = pusherRequestQuery($httpClient)['auth_signature'];
            $text = $exception->getMessage() . $exception->getContext() . $exception->getSuggestion();

            expect($text)->not->toContain('auth_signature')
                ->and($text)->not->toContain($signature)
                ->and($text)->not->toContain('7ad3773142a6692b25b8')
                ->and($exception->getPrevious())->toBeNull();
        }
    });

    it('throws BroadcastException when the api answers with a redirect', function (): void {
        $httpClient = pusherApi(new HttpResponse(304, ''));

        try {
            pusherBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []);
            $this->fail('Expected BroadcastException');
        } catch (BroadcastException $exception) {
            expect($exception->getContext())->toContain('HTTP 304: (empty body)');
        }
    });

    it('rejects an empty event name', function (): void {
        $httpClient = new FakeHttpClient();

        expect(fn () => pusherBroadcaster($httpClient)->broadcast('shows.42', '', []))
            ->toThrow(BroadcastException::class, 'event name must not be empty')
            ->and($httpClient->requests)->toBeEmpty();
    });

    it('broadcasts once per channel when dispatching a broadcastable', function (): void {
        $httpClient = pusherApi();

        pusherBroadcaster($httpClient)->dispatch(new readonly class () implements BroadcastableInterface
        {
            public function channels(): array
            {
                return ['orders', new PrivateChannel('orders.7')];
            }

            public function event(): string
            {
                return 'order.shipped';
            }

            public function payload(): array
            {
                return ['id' => 7];
            }
        });

        expect($httpClient->requests)->toHaveCount(2)
            ->and(pusherRequestBody($httpClient)['channels'])->toBe(['orders'])
            ->and(pusherRequestBody($httpClient, 1)['channels'])->toBe(['private-orders.7']);
    });
});
