<?php

declare(strict_types=1);

use Marko\Authentication\AuthenticatableInterface;
use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Broadcasting\Amphp\Auth\AmphpSignature;
use Marko\Broadcasting\Amphp\Exceptions\AmphpBroadcastException;
use Marko\Broadcasting\Amphp\Subscriber\AmphpSubscriberToken;
use Marko\Broadcasting\ChannelRegistry;
use Marko\Broadcasting\Exceptions\BroadcastException;
use Marko\Broadcasting\Exceptions\ChannelAuthorizationException;
use Marko\Broadcasting\PresenceChannel;
use Marko\Broadcasting\PrivateChannel;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;

function amphpBroadcastingTestConfig(
    string $appKey = 'app-secret',
): AmphpBroadcastingConfig {
    return new AmphpBroadcastingConfig(
        publicUrl: 'https://realtime.example.com/',
        appKey: $appKey,
        tokenTtl: 600,
    );
}

function amphpSubscriberToken(
    string $appKey = 'app-secret',
    ?FakeClock $clock = null,
): AmphpSubscriberToken {
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
                'orders.7' => $user !== null,
                'orders.8' => false,
                default => throw ChannelAuthorizationException::unknownChannel($channelName),
            };
        }
    };

    $config = amphpBroadcastingTestConfig($appKey);
    $clock ??= new FakeClock();

    return new AmphpSubscriberToken(
        amphpSignature: new AmphpSignature($config, $clock),
        amphpBroadcastingConfig: $config,
        channelRegistry: $channelRegistry,
        clock: $clock,
    );
}

describe('AmphpSubscriberToken', function (): void {
    it('includes only authorized private channels', function (): void {
        $token = amphpSubscriberToken()->for(
            ['shows.42', new PrivateChannel('orders.7'), new PrivateChannel('orders.8')],
            new FakeAuthenticatable(id: 7),
        );

        $claims = new AmphpSignature(amphpBroadcastingTestConfig(), new FakeClock())->verify($token);

        expect($claims?->channels)->toBe(['private-orders.7'])
            ->and($claims?->userId)->toBe('7');
    });

    it('signs subscriber tokens that expire token_ttl seconds after the injected clock', function (): void {
        $clock = new FakeClock('2026-01-01 12:00:00 UTC');
        $token = amphpSubscriberToken(clock: $clock)->for(
            [new PrivateChannel('orders.7')],
            new FakeAuthenticatable(id: 7),
        );

        $claims = new AmphpSignature(amphpBroadcastingTestConfig(), $clock)->verify($token);

        expect($claims?->expiresAt)->toBe(1767268800 + 600);
    });

    it('denies private channels to guests when the authorizer requires a user', function (): void {
        $token = amphpSubscriberToken()->for([new PrivateChannel('orders.7')], null);

        $claims = new AmphpSignature(amphpBroadcastingTestConfig(), new FakeClock())->verify($token);

        expect($claims?->channels)->toBe([]);
    });

    it('throws loudly for private channels without an authorizer', function (): void {
        amphpSubscriberToken()->for([new PrivateChannel('unknown.1')], null);
    })->throws(ChannelAuthorizationException::class);

    it('throws when the app key is empty', function (): void {
        amphpSubscriberToken('')->for([new PrivateChannel('orders.7')], new FakeAuthenticatable(id: 7));
    })->throws(AmphpBroadcastException::class, 'No amphp broadcasting app key is configured.');

    it('rejects a private channel name outside the amphp pattern before authorizing it', function (): void {
        expect(fn () => amphpSubscriberToken()->for(
            [new PrivateChannel('user.1/private-admin')],
            new FakeAuthenticatable(id: 1),
        ))->toThrow(BroadcastException::class, "Channel name 'user.1/private-admin' is not valid for Amphp.");
    });

    it('rejects a public channel name outside the channel name pattern', function (): void {
        expect(fn () => amphpSubscriberToken()->for(['shows.42/private-orders.8'], null))
            ->toThrow(BroadcastException::class, "Channel name 'shows.42/private-orders.8' is not valid for Amphp.");
    });

    it('rejects a channel name outside the amphp pattern when building a stream url', function (): void {
        expect(fn () => amphpSubscriberToken('')->streamUrl(['shows.42/private-orders.8'], null))
            ->toThrow(BroadcastException::class, "Channel name 'shows.42/private-orders.8' is not valid for Amphp.");
    });

    it('builds a stream url with channels and token', function (): void {
        $url = amphpSubscriberToken()->streamUrl(
            ['shows.42', new PrivateChannel('orders.7')],
            new FakeAuthenticatable(id: 7),
        );

        expect($url)->toStartWith('https://realtime.example.com/stream?channels=shows.42%2Cprivate-orders.7&token=');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        expect(new AmphpSignature(amphpBroadcastingTestConfig(), new FakeClock())->verify($query['token'])?->channels)
            ->toBe(['private-orders.7']);
    });

    it('builds a stream url without a token for public channels', function (): void {
        expect(amphpSubscriberToken('')->streamUrl(['shows.42', 'shows.43'], null))
            ->toBe('https://realtime.example.com/stream?channels=shows.42%2Cshows.43');
    });

    it('throws a clear exception when issuing a subscriber token for a presence channel', function (): void {
        expect(fn () => amphpSubscriberToken()->for([new PresenceChannel('room.1')], new FakeAuthenticatable(id: 7)))
            ->toThrow(BroadcastException::class, "Presence channel 'room.1' is not supported by Amphp.");
    });

    it('throws a clear exception when building a stream url for a presence channel', function (): void {
        expect(fn () => amphpSubscriberToken()->streamUrl(['shows.42', new PresenceChannel('room.1')], null))
            ->toThrow(BroadcastException::class, "Presence channel 'room.1' is not supported by Amphp.");
    });
});
