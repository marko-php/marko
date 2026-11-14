<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Server;

use Amp\Http\HttpStatus;
use Amp\Http\Server\Middleware\Forwarded;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Socket\InternetAddress;
use JsonException;
use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Broadcasting\Amphp\Auth\AmphpSignature;
use Marko\Broadcasting\Amphp\Driver\AmphpBroadcaster;
use Marko\Log\Contracts\LoggerInterface;
use Throwable;

/**
 * Serves `GET {path}?channels=a,private-b` as text/event-stream and `GET {health_path}` as JSON.
 *
 * Order of checks: origin, channel list, private-channel token, connection limits, then the
 * pub/sub subscription. Nothing is subscribed for a request that is going to be rejected.
 */
class StreamRequestHandler implements RequestHandler
{
    /**
     * Each distinct channel can hold one pub/sub subscription (one Redis connection with
     * marko/pubsub-redis), so a single stream may not ask for more than this.
     */
    public const int MAX_CHANNELS_PER_STREAM = 50;

    public const int RETRY_AFTER_SECONDS = 5;

    /**
     * Header that must carry `health_secret` for `/health` to include per-channel counts.
     */
    public const string HEALTH_SECRET_HEADER = 'x-health-secret';

    private int $nextConnectionId = 1;

    private int $openConnections = 0;

    /**
     * @var array<string, int>
     */
    private array $connectionsPerIp = [];

    public function __construct(
        private readonly AmphpBroadcastingConfig $amphpBroadcastingConfig,
        private readonly ChannelHub $channelHub,
        private readonly AmphpSignature $amphpSignature,
        private readonly LoggerInterface $logger,
        private readonly int $maxConnections,
    ) {}

    /**
     * @throws JsonException
     */
    public function handleRequest(
        Request $request,
    ): Response {
        $path = $request->getUri()->getPath();
        $origin = $request->getHeader('origin');

        if (
            $origin !== null
            && !$this->originAllowed($origin)
        ) {
            return $this->reject(HttpStatus::FORBIDDEN, 'Origin not allowed.');
        }

        if (
            $path !== $this->amphpBroadcastingConfig->path
            && $path !== $this->amphpBroadcastingConfig->healthPath
        ) {
            return $this->reject(HttpStatus::NOT_FOUND, 'Not found.', $origin);
        }

        if ($request->getMethod() === 'OPTIONS') {
            return new Response(HttpStatus::NO_CONTENT, [
                ...$this->corsHeaders($origin),
                'access-control-allow-methods' => 'GET, OPTIONS',
                'access-control-allow-headers' => 'Authorization, Last-Event-ID, Cache-Control',
                'access-control-max-age' => '600',
            ]);
        }

        if ($path === $this->amphpBroadcastingConfig->healthPath) {
            return $this->health($request, $origin);
        }

        return $this->stream($request, $origin);
    }

    /**
     * Aggregate counts only, unless health detail is enabled and the request proves it knows
     * the health secret: channel names such as private-users.7 reveal who is online.
     *
     * @throws JsonException
     */
    private function health(
        Request $request,
        ?string $origin,
    ): Response {
        $channelCounts = $this->channelHub->channelCounts();
        $body = [
            'status' => 'ok',
            'connections' => $this->channelHub->connectionCount(),
            'channels' => count($channelCounts),
        ];

        if ($this->healthDetailAllowed($request)) {
            $body['channel_counts'] = (object) $channelCounts;
        }

        return new Response(HttpStatus::OK, [
            ...$this->corsHeaders($origin),
            'content-type' => 'application/json',
            'cache-control' => 'no-store',
        ], json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function healthDetailAllowed(
        Request $request,
    ): bool {
        if (
            !$this->amphpBroadcastingConfig->healthDetail
            || $this->amphpBroadcastingConfig->healthSecret === ''
        ) {
            return false;
        }

        $secret = $request->getHeader(self::HEALTH_SECRET_HEADER);

        return $secret !== null
            && hash_equals($this->amphpBroadcastingConfig->healthSecret, $secret);
    }

    private function stream(
        Request $request,
        ?string $origin,
    ): Response {
        $channels = $this->requestedChannels($request);

        if ($channels === []) {
            return $this->reject(
                HttpStatus::BAD_REQUEST,
                'Pass at least one channel: ?channels=orders.42,private-users.7',
                $origin,
            );
        }

        if (count($channels) > self::MAX_CHANNELS_PER_STREAM) {
            return $this->reject(
                HttpStatus::BAD_REQUEST,
                'At most ' . self::MAX_CHANNELS_PER_STREAM . ' channels may be requested per stream.',
                $origin,
            );
        }

        if (array_any(
            $channels,
            fn (string $channel): bool => preg_match(AmphpBroadcaster::CHANNEL_NAME_PATTERN, $channel) !== 1,
        )) {
            return $this->reject(HttpStatus::BAD_REQUEST, 'Invalid channel name.', $origin);
        }

        if (!$this->authorized($request, $channels)) {
            return $this->reject(
                HttpStatus::FORBIDDEN,
                'A valid token is required for the requested private channels.',
                $origin,
            );
        }

        $ip = $this->clientIp($request);

        if (
            $this->openConnections >= $this->maxConnections
            || ($this->amphpBroadcastingConfig->maxConnectionsPerIp > 0
                && ($this->connectionsPerIp[$ip] ?? 0) >= $this->amphpBroadcastingConfig->maxConnectionsPerIp)

        ) {
            return $this->reject(HttpStatus::SERVICE_UNAVAILABLE, 'Too many connections; retry later.', $origin, [
                'retry-after' => (string) self::RETRY_AFTER_SECONDS,
            ]);
        }

        $connection = new SseConnection($this->nextConnectionId++, $channels, $ip);
        $this->reserve($connection);
        $request->getClient()->onClose(static function () use ($connection): void {
            $connection->close();
        });
        $connection->send(":ok\n\n");

        try {
            $this->channelHub->join($connection, $this->lastEventId($request));
        } catch (Throwable $e) {
            $connection->close();
            $this->logger->error('Could not open a broadcast stream: pub/sub subscription failed', [
                'channels' => $channels,
                'exception' => $e,
            ]);

            return $this->reject(
                HttpStatus::SERVICE_UNAVAILABLE,
                'Broadcast backend unavailable; retry later.',
                $origin,
                [
                    'retry-after' => (string) self::RETRY_AFTER_SECONDS,
                ],
            );
        }

        return new Response(HttpStatus::OK, [
            ...$this->corsHeaders($origin),
            'content-type' => 'text/event-stream; charset=utf-8',
            'cache-control' => 'no-cache, no-transform',
            'x-accel-buffering' => 'no',
        ], $connection->stream());
    }

    /**
     * @return list<string>
     */
    private function requestedChannels(
        Request $request,
    ): array {
        $raw = $request->getQueryParameter('channels') ?? '';

        return array_values(array_unique(array_filter(
            array_map(trim(...), explode(',', $raw)),
            fn (string $channel): bool => $channel !== '',
        )));
    }

    /**
     * @param list<string> $channels
     */
    private function authorized(
        Request $request,
        array $channels,
    ): bool {
        $private = array_filter(
            $channels,
            fn (string $channel): bool => str_starts_with($channel, AmphpBroadcaster::PRIVATE_PREFIX),
        );

        if ($private === []) {
            return true;
        }

        $token = $request->getQueryParameter('token') ?? $this->bearerToken($request);
        $claims = $token === null ? null : $this->amphpSignature->verify($token);

        return $claims !== null
            && array_all($private, $claims->allows(...));
    }

    private function bearerToken(
        Request $request,
    ): ?string {
        $header = $request->getHeader('authorization');

        if (
            $header === null
            || !str_starts_with(strtolower($header), 'bearer ')
        ) {
            return null;
        }

        return trim(substr($header, 7));
    }

    private function lastEventId(
        Request $request,
    ): ?string {
        return $request->getHeader('last-event-id') ?? $request->getQueryParameter('lastEventId');
    }

    private function clientIp(
        Request $request,
    ): string {
        if ($request->hasAttribute(Forwarded::class)) {
            $forwarded = $request->getAttribute(Forwarded::class);

            if ($forwarded instanceof Forwarded) {
                return $forwarded->getFor()->getAddress();
            }
        }

        $address = $request->getClient()->getRemoteAddress();

        return $address instanceof InternetAddress ? $address->getAddress() : $address->toString();
    }

    private function reserve(
        SseConnection $connection,
    ): void {
        $this->openConnections++;
        $this->connectionsPerIp[$connection->ip] = ($this->connectionsPerIp[$connection->ip] ?? 0) + 1;

        $connection->onClose(function (SseConnection $connection): void {
            $this->openConnections--;
            $this->connectionsPerIp[$connection->ip]--;

            if ($this->connectionsPerIp[$connection->ip] <= 0) {
                unset($this->connectionsPerIp[$connection->ip]);
            }
        });
    }

    private function originAllowed(
        string $origin,
    ): bool {
        $allowed = $this->amphpBroadcastingConfig->allowedOrigins;

        return in_array('*', $allowed, true)
            || in_array($origin, $allowed, true);
    }

    /**
     * @return array<non-empty-string, string>
     */
    private function corsHeaders(
        ?string $origin,
    ): array {
        if ($origin === null) {
            return [];
        }

        if (in_array('*', $this->amphpBroadcastingConfig->allowedOrigins, true)) {
            return ['access-control-allow-origin' => '*'];
        }

        return ['access-control-allow-origin' => $origin, 'vary' => 'Origin'];
    }

    /**
     * @param array<non-empty-string, string> $headers
     */
    private function reject(
        int $status,
        string $message,
        ?string $origin = null,
        array $headers = [],
    ): Response {
        return new Response($status, [
            ...$this->corsHeaders($origin),
            ...$headers,
            'content-type' => 'text/plain; charset=utf-8',
            'cache-control' => 'no-store',
        ], $message . "\n");
    }
}
