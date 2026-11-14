<?php

declare(strict_types=1);

use Marko\Broadcasting\BroadcastableInterface;
use Marko\Broadcasting\Exceptions\BroadcastException;
use Marko\Broadcasting\Mercure\Driver\MercureBroadcaster;
use Marko\Broadcasting\Mercure\Exceptions\MercureException;
use Marko\Broadcasting\Mercure\Jwt\MercureJwt;
use Marko\Broadcasting\Mercure\MercureConfig;
use Marko\Broadcasting\PresenceChannel;
use Marko\Broadcasting\PrivateChannel;
use Marko\Http\Exceptions\ConnectionException;
use Marko\Http\HttpResponse;
use Marko\Http\RequestOptions;
use Marko\Testing\Fake\FakeHttpClient;
use Marko\Testing\Fake\Http\RecordedRequest;

const MERCURE_TEST_HUB_URL = 'http://caddy/.well-known/mercure';

function mercureBroadcaster(
    FakeHttpClient $httpClient,
    string $publisherJwtKey = 'publisher-secret',
    string $publisherJwt = '',
    string $topicPrefix = '',
): MercureBroadcaster {
    return new MercureBroadcaster(
        httpClient: $httpClient,
        mercureJwt: new MercureJwt(),
        mercureConfig: new MercureConfig(
            hubUrl: MERCURE_TEST_HUB_URL,
            publicUrl: 'https://example.com/.well-known/mercure',
            publisherJwtKey: $publisherJwtKey,
            publisherJwt: $publisherJwt,
            topicPrefix: $topicPrefix,
        ),
    );
}

/**
 * A FakeHttpClient whose hub accepts every update, as a real Mercure hub does
 * by answering with the update's id.
 */
function mercureHub(): FakeHttpClient
{
    return new FakeHttpClient()->stub(MERCURE_TEST_HUB_URL, new HttpResponse(200, 'urn:uuid:1'));
}

/**
 * @return array<string, string>
 */
function mercureFormFields(
    FakeHttpClient $httpClient,
    int $index = 0,
): array {
    parse_str($httpClient->requests[$index]->body(), $fields);

    return $fields;
}

describe('MercureBroadcaster', function (): void {
    it('posts topic, data, type and id form fields to the hub url', function (): void {
        $httpClient = mercureHub();

        mercureBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', ['seat' => 'A1'], 'evt-1');

        expect($httpClient->requests)->toHaveCount(1)
            ->and($httpClient)->toHaveSentRequest(fn (RecordedRequest $request): bool => $request->method === 'POST'
                && $request->url === MERCURE_TEST_HUB_URL
                && $request->header('Content-Type') === 'application/x-www-form-urlencoded'
                && $request->options['timeout'] === 5)
            ->and(mercureFormFields($httpClient))->toBe([
                'topic' => 'shows.42',
                'data' => '{"seat":"A1"}',
                'type' => 'seat.sold',
                'id' => 'evt-1',
            ]);
    });

    it('omits the id field when no id is given', function (): void {
        $httpClient = mercureHub();

        mercureBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []);

        expect(mercureFormFields($httpClient))->not->toHaveKey('id')
            ->and(mercureFormFields($httpClient)['data'])->toBe('[]');
    });

    it('marks private channel updates with private=on', function (): void {
        $httpClient = mercureHub();

        mercureBroadcaster($httpClient)->broadcast(new PrivateChannel('orders.7'), 'order.shipped', ['id' => 7]);

        expect(mercureFormFields($httpClient)['private'])->toBe('on');
    });

    it('sends a bearer publisher jwt with a publish claim', function (): void {
        $httpClient = mercureHub();

        mercureBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []);

        $expected = new MercureJwt()->encode(['mercure' => ['publish' => ['*']]], 'publisher-secret');

        expect($httpClient)->toHaveSentRequest(
            fn (RecordedRequest $request): bool => $request->header('Authorization') === "Bearer $expected",
        );
    });

    it('uses the static publisher jwt when configured', function (): void {
        $httpClient = mercureHub();

        mercureBroadcaster($httpClient, publisherJwtKey: '', publisherJwt: 'static.jwt.token')
            ->broadcast('shows.42', 'seat.sold', []);

        expect($httpClient)->toHaveSentRequest(
            fn (RecordedRequest $request): bool => $request->header('Authorization') === 'Bearer static.jwt.token',
        );
    });

    it('throws when no publisher credentials are configured', function (): void {
        $httpClient = new FakeHttpClient();

        expect(fn () => mercureBroadcaster($httpClient, publisherJwtKey: '')->broadcast('a', 'b', []))
            ->toThrow(MercureException::class, 'No Mercure publisher credentials are configured')
            ->and($httpClient->requests)->toBeEmpty();
    });

    it('prefixes topics with the configured topic prefix', function (): void {
        $httpClient = mercureHub();

        mercureBroadcaster($httpClient, topicPrefix: 'https://example.com/')->broadcast('shows.42', 'seat.sold', []);

        expect(mercureFormFields($httpClient)['topic'])->toBe('https://example.com/shows.42');
    });

    it('throws BroadcastException when the hub cannot be reached', function (): void {
        $httpClient = new FakeHttpClient()->stub(MERCURE_TEST_HUB_URL, new ConnectionException('Connection refused'));

        try {
            mercureBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []);
            $this->fail('Expected BroadcastException');
        } catch (BroadcastException $exception) {
            expect($exception->getMessage())->toBe("Failed to broadcast to channel 'shows.42' via Mercure.")
                ->and($exception->getContext())->toContain('Connection refused')
                ->and($exception->getPrevious())->toBeInstanceOf(ConnectionException::class);
        }
    });

    it('sends the publish request with http_errors disabled', function (): void {
        $httpClient = mercureHub();

        mercureBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []);

        expect($httpClient->requests[0]->options[RequestOptions::HTTP_ERRORS])->toBeFalse();
    });

    it('puts the hub status and response body in the exception when the hub rejects the update', function (): void {
        $httpClient = new FakeHttpClient()->stub(MERCURE_TEST_HUB_URL, new HttpResponse(401, 'Unauthorized'));

        try {
            mercureBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []);
            $this->fail('Expected BroadcastException');
        } catch (BroadcastException $exception) {
            expect($exception->getMessage())->toBe("Failed to broadcast to channel 'shows.42' via Mercure.")
                ->and($exception->getContext())->toContain('HTTP 401: Unauthorized')
                ->and($exception->getSuggestion())->toContain('credentials');
        }
    });

    it('reports a hub server error with a server-side suggestion', function (): void {
        $httpClient = new FakeHttpClient()->stub(MERCURE_TEST_HUB_URL, new HttpResponse(503, 'Service Unavailable'));

        try {
            mercureBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []);
            $this->fail('Expected BroadcastException');
        } catch (BroadcastException $exception) {
            expect($exception->getContext())->toContain('HTTP 503: Service Unavailable')
                ->and($exception->getSuggestion())->toContain('server-side failure');
        }
    });

    it('caps a large hub error body in the exception', function (): void {
        $page = '<html>' . str_repeat('x', 5000) . '</html>';
        $httpClient = new FakeHttpClient()->stub(MERCURE_TEST_HUB_URL, new HttpResponse(500, $page));

        try {
            mercureBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []);
            $this->fail('Expected BroadcastException');
        } catch (BroadcastException $exception) {
            expect($exception->getContext())->toContain('[truncated')
                ->and($exception->getContext())->not->toContain('</html>');
        }
    });

    it('never puts the publisher jwt in a rejected publish exception', function (): void {
        $httpClient = new FakeHttpClient()->stub(MERCURE_TEST_HUB_URL, new HttpResponse(403, 'Forbidden'));

        try {
            mercureBroadcaster($httpClient, publisherJwt: 'static.publisher.jwt')->broadcast(
                'shows.42',
                'seat.sold',
                [],
            );
            $this->fail('Expected BroadcastException');
        } catch (BroadcastException $exception) {
            expect($exception->getMessage() . $exception->getContext() . $exception->getSuggestion())
                ->not->toContain('static.publisher.jwt');
        }
    });

    it('throws BroadcastException when the hub answers with a redirect', function (): void {
        $httpClient = new FakeHttpClient()->stub(MERCURE_TEST_HUB_URL, new HttpResponse(304, ''));

        try {
            mercureBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []);
            $this->fail('Expected BroadcastException');
        } catch (BroadcastException $exception) {
            expect($exception->getContext())->toContain('HTTP 304: (empty body)');
        }
    });

    it('rejects an empty event name', function (): void {
        $httpClient = new FakeHttpClient();

        expect(fn () => mercureBroadcaster($httpClient)->broadcast('shows.42', '', []))
            ->toThrow(BroadcastException::class, 'event name must not be empty')
            ->and($httpClient->requests)->toBeEmpty();
    });

    it('broadcasts once per channel when dispatching a broadcastable', function (): void {
        $httpClient = mercureHub();

        mercureBroadcaster($httpClient)->dispatch(new readonly class () implements BroadcastableInterface
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
            ->and(mercureFormFields($httpClient)['topic'])->toBe('orders')
            ->and(mercureFormFields($httpClient, 1)['topic'])->toBe('orders.7')
            ->and(mercureFormFields($httpClient, 1)['private'])->toBe('on');
    });

    it('throws a clear exception when broadcasting to a presence channel', function (): void {
        $httpClient = mercureHub();

        expect(fn () => mercureBroadcaster($httpClient)->broadcast(new PresenceChannel('room.1'), 'user.joined', []))
            ->toThrow(BroadcastException::class, "Presence channel 'room.1' is not supported by Mercure.")
            ->and($httpClient->requests)->toBeEmpty();
    });
});
