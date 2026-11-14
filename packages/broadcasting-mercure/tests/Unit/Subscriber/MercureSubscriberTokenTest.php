<?php

declare(strict_types=1);

use Marko\Authentication\AuthenticatableInterface;
use Marko\Broadcasting\Channel;
use Marko\Broadcasting\ChannelRegistry;
use Marko\Broadcasting\Exceptions\BroadcastException;
use Marko\Broadcasting\Exceptions\ChannelAuthorizationException;
use Marko\Broadcasting\Mercure\Exceptions\MercureException;
use Marko\Broadcasting\Mercure\Jwt\MercureJwt;
use Marko\Broadcasting\Mercure\MercureConfig;
use Marko\Broadcasting\Mercure\Subscriber\MercureSubscriberToken;
use Marko\Broadcasting\PresenceChannel;
use Marko\Broadcasting\PrivateChannel;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;

function mercureSubscriberToken(
    string $subscriberJwtKey = 'subscriber-secret',
    string $cookieDomain = '',
    ?FakeClock $clock = null,
    string $topicPrefix = 'https://example.com/',
): MercureSubscriberToken {
    /** @noinspection PhpMissingParentConstructorInspection - Test stub replaces discovery-backed authorization */
    $channelRegistry = new class () extends ChannelRegistry
    {
        /** @noinspection PhpMissingParentConstructorInspection */
        public function __construct() {}

        public function authorize(
            string $channelName,
            ?AuthenticatableInterface $user,
        ): bool {
            return match ($channelName) {
                'orders.7', 'customers.1{x}', 'customers.1,foo' => $user !== null,
                'orders.8' => false,
                default => throw ChannelAuthorizationException::unknownChannel($channelName),
            };
        }
    };

    return new MercureSubscriberToken(
        mercureJwt: new MercureJwt(),
        mercureConfig: new MercureConfig(
            hubUrl: 'http://caddy/.well-known/mercure',
            publicUrl: 'https://example.com/.well-known/mercure',
            subscriberJwtKey: $subscriberJwtKey,
            subscriberJwtTtl: 600,
            topicPrefix: $topicPrefix,
            cookieDomain: $cookieDomain,
        ),
        channelRegistry: $channelRegistry,
        clock: $clock ?? new FakeClock(),
    );
}

/**
 * @return array<string, mixed>
 */
/**
 * A private channel that skips Channel's name validation, standing in for a subclass that
 * overrides the constructor.
 */
function unvalidatedPrivateChannel(
    string $name,
): PrivateChannel {
    return new readonly class ($name) extends PrivateChannel
    {
        /** @noinspection PhpMissingParentConstructorInspection - Deliberately bypasses name validation */
        public function __construct(
            public string $name,
        ) {}
    };
}

function mercureClaims(
    string $token,
): array {
    return json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);
}

describe('MercureSubscriberToken', function (): void {
    it('includes only authorized private topics in the subscribe claim', function (): void {
        $token = mercureSubscriberToken()->for(
            [new PrivateChannel('orders.7'), new PrivateChannel('orders.8')],
            new FakeAuthenticatable(),
        );

        expect(mercureClaims($token)['mercure'])->toBe(['subscribe' => ['https://example.com/orders.7']]);
    });

    it('excludes public topics from the subscribe claim', function (): void {
        $token = mercureSubscriberToken()->for(['shows.42', new Channel('shows.43')], null);

        expect(mercureClaims($token)['mercure'])->toBe(['subscribe' => []]);
    });

    it('denies private topics to guests when the authorizer requires a user', function (): void {
        $token = mercureSubscriberToken()->for([new PrivateChannel('orders.7')], null);

        expect(mercureClaims($token)['mercure']['subscribe'])->toBeEmpty();
    });

    it('throws for a private channel with no registered authorizer', function (): void {
        expect(fn () => mercureSubscriberToken()->for([new PrivateChannel('invoices.1')], new FakeAuthenticatable()))
            ->toThrow(ChannelAuthorizationException::class);
    });

    it('signs the token with the subscriber key', function (): void {
        $token = mercureSubscriberToken()->for([], null);
        [$header, $payload, $signature] = explode('.', $token);
        $expected = rtrim(
            strtr(base64_encode(hash_hmac('sha256', "$header.$payload", 'subscriber-secret', true)), '+/', '-_'),
            '=',
        );

        expect($signature)->toBe($expected);
    });

    it('sets the subscriber JWT exp from the injected clock', function (): void {
        $clock = new FakeClock('2026-01-01 12:00:00 UTC');
        $claims = mercureClaims(mercureSubscriberToken(clock: $clock)->for([], null));

        expect($claims['exp'])->toBe(1767268800 + 600);
    });

    it('sets the authorization cookie expiry from the injected clock', function (): void {
        $clock = new FakeClock('2026-01-01 12:00:00 UTC');
        $response = mercureSubscriberToken(clock: $clock)->withAuthorizationCookie(
            new Response('ok'),
            [new PrivateChannel('orders.7')],
            new FakeAuthenticatable(),
        );

        expect($response->cookies()[0]->expires())->toBe(1767268800 + 600);
    });

    it('throws when no subscriber key is configured', function (): void {
        expect(fn () => mercureSubscriberToken(subscriberJwtKey: '')->for([], null))
            ->toThrow(MercureException::class, 'No Mercure subscriber JWT key is configured');
    });

    it('sets the mercureAuthorization cookie on the response', function (): void {
        $response = mercureSubscriberToken(cookieDomain: 'example.com')->withAuthorizationCookie(
            new Response('ok'),
            [new PrivateChannel('orders.7')],
            new FakeAuthenticatable(),
        );

        $cookieLine = array_find(
            $response->headerLines(),
            fn (string $line): bool => str_starts_with($line, 'Set-Cookie: mercureAuthorization='),
        );

        expect($response->cookies())->toHaveCount(1)
            ->and($response->cookies()[0]->name())->toBe('mercureAuthorization')
            ->and($response->cookies()[0]->path())->toBe('/.well-known/mercure')
            ->and($response->cookies()[0]->domain())->toBe('example.com')
            ->and($cookieLine)->toContain('HttpOnly')
            ->toContain('Secure')
            ->toContain('SameSite=Strict');
    });

    it('builds the subscribe url with one topic parameter per channel', function (): void {
        $url = mercureSubscriberToken()->subscribeUrl(['shows.42', new PrivateChannel('orders.7')]);

        expect($url)->toBe(
            'https://example.com/.well-known/mercure'
            . '?topic=https%3A%2F%2Fexample.com%2Fshows.42'
            . '&topic=https%3A%2F%2Fexample.com%2Forders.7',
        );
    });

    it('refuses to sign a topic containing URI-template characters', function (string $name): void {
        expect(fn () => mercureSubscriberToken()->for([unvalidatedPrivateChannel($name)], new FakeAuthenticatable()))
            ->toThrow(MercureException::class, 'not a safe Mercure topic');
    })->with([
        'URI template' => ['customers.1{x}'],
        'comma list' => ['customers.1,foo'],
    ]);

    it('refuses to issue a token when the topic prefix contains URI-template characters', function (): void {
        expect(fn () => mercureSubscriberToken(topicPrefix: 'https://example.com/{tenant}/')->for(
            [new PrivateChannel('orders.7')],
            new FakeAuthenticatable(),
        ))->toThrow(MercureException::class, 'not a safe Mercure topic');
    });

    it('refuses to build a subscribe url for a topic containing URI-template characters', function (): void {
        expect(fn () => mercureSubscriberToken()->subscribeUrl([unvalidatedPrivateChannel('customers.{id}')]))
            ->toThrow(MercureException::class, 'not a safe Mercure topic');
    });

    it('throws a clear exception when issuing a subscriber token for a presence channel', function (): void {
        expect(fn () => mercureSubscriberToken()->for([new PresenceChannel('room.1')], new FakeAuthenticatable(id: 7)))
            ->toThrow(BroadcastException::class, "Presence channel 'room.1' is not supported by Mercure.");
    });

    it('throws a clear exception when building a subscribe url for a presence channel', function (): void {
        expect(fn () => mercureSubscriberToken()->subscribeUrl(['shows.42', new PresenceChannel('room.1')]))
            ->toThrow(BroadcastException::class, "Presence channel 'room.1' is not supported by Mercure.");
    });
});
