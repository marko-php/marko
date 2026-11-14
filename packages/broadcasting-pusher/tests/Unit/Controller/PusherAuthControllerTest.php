<?php

declare(strict_types=1);

use Marko\Authentication\AuthenticatableInterface;
use Marko\Broadcasting\Channel;
use Marko\Broadcasting\ChannelRegistry;
use Marko\Broadcasting\Exceptions\BroadcastException;
use Marko\Broadcasting\Exceptions\ChannelAuthorizationException;
use Marko\Broadcasting\PresenceMember;
use Marko\Broadcasting\Pusher\Auth\PusherSignature;
use Marko\Broadcasting\Pusher\Controller\PusherAuthController;
use Marko\Broadcasting\Pusher\Exceptions\PusherException;
use Marko\Broadcasting\Pusher\PusherConfig;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Http\Request;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeGuard;

function pusherAuthController(
    ?AuthenticatableInterface $user = null,
    string $secret = '7ad3773142a6692b25b8',
): PusherAuthController {
    /** @noinspection PhpMissingParentConstructorInspection - Test stub replaces discovery-backed authorization */
    $channelRegistry = new class () extends ChannelRegistry
    {
        /** @noinspection PhpMissingParentConstructorInspection */
        public function __construct() {}

        public function authorize(
            string $channelName,
            ?AuthenticatableInterface $user,
        ): bool {
            if (preg_match(Channel::FORBIDDEN_CHARACTERS_PATTERN, $channelName) === 1) {
                throw BroadcastException::unsafeChannelName($channelName);
            }

            if ($channelName === 'unknown') {
                throw ChannelAuthorizationException::unknownChannel($channelName);
            }

            return $channelName === 'foobar'
                && $user !== null;
        }

        public function authorizePresence(
            string $channelName,
            ?AuthenticatableInterface $user,
        ): ?PresenceMember {
            if ($channelName === 'unknown') {
                throw ChannelAuthorizationException::unknownPresenceChannel($channelName);
            }

            return match (true) {
                $channelName === 'foobar'
                    && $user !== null => new PresenceMember(10, ['name' => 'Mr. Channels']),
                $channelName === 'bare' => new PresenceMember('7'),
                default => null,
            };
        }
    };

    $guard = new FakeGuard();
    $guard->setUser($user);

    return new PusherAuthController(
        pusherSignature: new PusherSignature(new PusherConfig(
            appId: '3',
            key: '278d425bdf160c739803',
            secret: $secret,
        )),
        channelRegistry: $channelRegistry,
        guard: $guard,
    );
}

/**
 * @param array<string, string> $post
 */
function pusherAuthRequest(
    array $post,
): Request {
    return new Request(server: ['REQUEST_METHOD' => 'POST'], post: $post);
}

describe('PusherAuthController', function (): void {
    it('returns key and signature for an authorized user', function (): void {
        $response = pusherAuthController(new FakeAuthenticatable())->authorize(
            pusherAuthRequest(['socket_id' => '1234.1234', 'channel_name' => 'private-foobar']),
        );

        expect($response->statusCode())->toBe(200)
            ->and($response->headers()['Content-Type'])->toBe('application/json')
            ->and(json_decode($response->body(), true))->toBe([
                'auth' => '278d425bdf160c739803:58df8b0c36d6982b82c3ecf6b4662e34fe8c25bba48f5369f135bf843651c3a4',
            ]);
    });

    it('returns auth and channel_data for an authorized presence member', function (): void {
        $response = pusherAuthController(new FakeAuthenticatable())->authorize(
            pusherAuthRequest(['socket_id' => '1234.1234', 'channel_name' => 'presence-foobar']),
        );
        $channelData = '{"user_id":"10","user_info":{"name":"Mr. Channels"}}';
        $signature = hash_hmac('sha256', "1234.1234:presence-foobar:$channelData", '7ad3773142a6692b25b8');

        expect($response->statusCode())->toBe(200)
            ->and(json_decode($response->body(), true))->toBe([
                'auth' => "278d425bdf160c739803:$signature",
                'channel_data' => $channelData,
            ]);
    });

    it('omits user_info from channel_data when the member has no info', function (): void {
        $response = pusherAuthController()->authorize(
            pusherAuthRequest(['socket_id' => '1234.1234', 'channel_name' => 'presence-bare']),
        );

        expect(json_decode($response->body(), true)['channel_data'])->toBe('{"user_id":"7"}');
    });

    it('throws a 403 HttpException when the registry denies a presence channel', function (): void {
        expect(fn () => pusherAuthController(new FakeAuthenticatable())->authorize(
            pusherAuthRequest(['socket_id' => '1234.1234', 'channel_name' => 'presence-denied']),
        ))->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(403));
    });

    it('passes a guest to the presence authorizer like a private channel', function (): void {
        expect(fn () => pusherAuthController()->authorize(
            pusherAuthRequest(['socket_id' => '1234.1234', 'channel_name' => 'presence-foobar']),
        ))->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(403));
    });

    it('throws a generic 403 HttpException for a presence channel with no registered authorizer', function (): void {
        expect(fn () => pusherAuthController(new FakeAuthenticatable())->authorize(
            pusherAuthRequest(['socket_id' => '1234.1234', 'channel_name' => 'presence-unknown']),
        ))->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(403)
            ->and($e->getResponseData())->toBe(['message' => 'Forbidden.'])
            ->and($e->getPrevious())->toBeInstanceOf(ChannelAuthorizationException::class));
    });

    it('throws a generic 403 HttpException for a private channel with no registered authorizer', function (): void {
        expect(fn () => pusherAuthController(new FakeAuthenticatable())->authorize(
            pusherAuthRequest(['socket_id' => '1234.1234', 'channel_name' => 'private-unknown']),
        ))->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(403)
            ->and($e->getResponseData())->toBe(['message' => 'Forbidden.'])
            ->and($e->getPrevious())->toBeInstanceOf(ChannelAuthorizationException::class));
    });

    it('throws a generic 403 HttpException for an unsafe channel name', function (): void {
        expect(fn () => pusherAuthController(new FakeAuthenticatable())->authorize(
            pusherAuthRequest(['socket_id' => '1234.1234', 'channel_name' => 'private-orders.{id},*']),
        ))->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(403)
            ->and($e->getResponseData())->toBe(['message' => 'Forbidden.'])
            ->and($e->getPrevious())->toBeInstanceOf(BroadcastException::class));
    });

    it('lets a PusherException from missing credentials propagate instead of answering 403', function (): void {
        expect(fn () => pusherAuthController(new FakeAuthenticatable(), secret: '')->authorize(
            pusherAuthRequest(['socket_id' => '1234.1234', 'channel_name' => 'private-foobar']),
        ))->toThrow(PusherException::class);
    });

    it('throws a 403 HttpException when the registry denies a private channel', function (): void {
        expect(fn () => pusherAuthController()->authorize(
            pusherAuthRequest(['socket_id' => '1234.1234', 'channel_name' => 'private-foobar']),
        ))->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(403));
    });

    it('throws a 400 HttpException for a missing or malformed socket id', function (): void {
        $controller = pusherAuthController(new FakeAuthenticatable());
        $assert400 = fn (HttpException $e) => expect($e->getStatusCode())->toBe(400);

        expect(fn () => $controller->authorize(pusherAuthRequest(['channel_name' => 'private-foobar'])))
            ->toThrow($assert400)
            ->and(fn () => $controller->authorize(
                pusherAuthRequest(['socket_id' => '1234:1234', 'channel_name' => 'private-foobar']),
            ))->toThrow($assert400);
    });

    it('throws a 400 HttpException for a missing channel name', function (): void {
        expect(fn () => pusherAuthController(new FakeAuthenticatable())->authorize(
            pusherAuthRequest(['socket_id' => '1234.1234']),
        ))->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(400));
    });

    it('throws a 400 HttpException for a public channel', function (): void {
        expect(fn () => pusherAuthController(new FakeAuthenticatable())->authorize(
            pusherAuthRequest(['socket_id' => '1234.1234', 'channel_name' => 'foobar']),
        ))->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(400));
    });

    it('registers the route at POST /broadcasting/auth', function (): void {
        $attributes = new ReflectionMethod(PusherAuthController::class, 'authorize')->getAttributes(Post::class);

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->newInstance()->path)->toBe('/broadcasting/auth');
    });
});
