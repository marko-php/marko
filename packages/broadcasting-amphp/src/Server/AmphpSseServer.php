<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Server;

use function Amp\async;

use Amp\Http\Server\DefaultErrorHandler;

use Amp\Http\Server\Driver\DefaultHttpDriverFactory;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Http\Server\Middleware\ForwardedHeaderType;
use Amp\Http\Server\Middleware\ForwardedMiddleware;

use function Amp\Http\Server\Middleware\stackMiddleware;

use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\InternetAddress;
use Amp\Socket\ResourceServerSocketFactory;
use Amp\Socket\SocketException;
use Amp\TimeoutCancellation;
use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Broadcasting\Amphp\Auth\AmphpSignature;
use Marko\Broadcasting\Amphp\Exceptions\AmphpBroadcastException;
use Marko\Broadcasting\Amphp\Log\PsrLoggerBridge;
use Marko\Log\Contracts\LoggerInterface;
use Marko\PubSub\SubscriberInterface;
use Psr\Clock\ClockInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver\StreamSelectDriver;
use Throwable;

/**
 * The `broadcasting:serve` HTTP server: an amphp/http-server instance that holds every
 * EventSource connection in one process on the Revolt event loop.
 *
 * The server is built without amphp's compression middleware (it buffers response bodies) and
 * without its connection/concurrency caps; max_connections and max_connections_per_ip are
 * enforced by StreamRequestHandler with a 503 + Retry-After instead.
 */
class AmphpSseServer
{
    /**
     * EventSource reconnect delay announced in the shutdown frame, in milliseconds.
     */
    public const int RECONNECT_RETRY_MS = 1000;

    /**
     * stream_select() is limited to FD_SETSIZE (1024) descriptors, minus a few for listeners and pub/sub.
     */
    public const int STREAM_SELECT_LIMIT = 1000;

    private ?SocketHttpServer $httpServer = null;

    private ?ChannelHub $channelHub = null;

    /**
     * @var list<string>
     */
    private array $timers = [];

    public function __construct(
        private readonly AmphpBroadcastingConfig $amphpBroadcastingConfig,
        private readonly SubscriberInterface $subscriber,
        private readonly AmphpSignature $amphpSignature,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Bind and start serving. Pass port 0 to bind a random free port (see port()).
     *
     * @throws AmphpBroadcastException
     */
    public function start(
        ?string $host = null,
        ?int $port = null,
    ): void {
        $config = $this->amphpBroadcastingConfig;
        $address = ($host ?? $config->host) . ':' . ($port ?? $config->port);
        $psrLogger = new PsrLoggerBridge($this->logger);

        $this->channelHub = new ChannelHub(
            $this->subscriber,
            new ReplayBuffer($config->replayBuffer, $config->replayTtl, $this->clock),
            $config,
            $this->logger,
        );

        $maxConnections = $this->maxConnections();
        $requestHandler = new StreamRequestHandler(
            $config,
            $this->channelHub,
            $this->amphpSignature,
            $this->logger,
            $maxConnections,
        );

        $httpServer = new SocketHttpServer(
            $psrLogger,
            new ResourceServerSocketFactory(),
            new SocketClientFactory($psrLogger),
            [],
            ['GET', 'HEAD', 'OPTIONS'],
            new DefaultHttpDriverFactory($psrLogger, streamTimeout: $this->streamTimeout()),
        );

        try {
            $httpServer->expose($address);
        } catch (SocketException $e) {
            throw AmphpBroadcastException::serverStartFailed($address, $e->getMessage(), $e);
        }

        $channelHub = $this->channelHub;
        $httpServer->onStop(static function () use ($channelHub): void {
            $channelHub->closeAll(SseFrame::reconnect(self::RECONNECT_RETRY_MS));
        });

        try {
            $httpServer->start(
                $config->trustedProxies === []
                    ? $requestHandler
                    : stackMiddleware(
                        $requestHandler,
                        new ForwardedMiddleware(ForwardedHeaderType::XForwardedFor, $config->trustedProxies),
                    ),
                new DefaultErrorHandler(),
            );
        } catch (Throwable $e) {
            throw AmphpBroadcastException::serverStartFailed($address, $e->getMessage(), $e);
        }

        $this->httpServer = $httpServer;
        $this->timers[] = EventLoop::repeat((float) $config->heartbeat, $channelHub->heartbeat(...));

        if ($config->logInterval > 0) {
            $this->timers[] = EventLoop::repeat((float) $config->logInterval, $this->logStats(...));
        }

        if ($maxConnections < $config->maxConnections) {
            $this->logger->warning(sprintf(
                'The event loop is using stream_select(), which cannot watch more than 1024 sockets: capping max_connections from %d to %d. Install ext-ev, ext-uv or ext-event for more connections.',
                $config->maxConnections,
                $maxConnections,
            ));
        }

        if ($config->appKey === '') {
            $this->logger->warning(
                'broadcasting-amphp.app_key is empty: requests for private channels will be refused with 403',
            );
        }
    }

    /**
     * Stop accepting connections, send every stream a reconnect frame, close the streams and
     * cancel all pub/sub subscriptions. Gives in-flight writes up to $timeout seconds.
     */
    public function stop(
        float $timeout = 30.0,
    ): void {
        foreach ($this->timers as $timer) {
            EventLoop::cancel($timer);
        }

        $this->timers = [];
        $httpServer = $this->httpServer;
        $this->httpServer = null;

        if ($httpServer === null) {
            return;
        }

        try {
            async($httpServer->stop(...))->await(new TimeoutCancellation($timeout));
        } catch (Throwable $e) {
            $this->logger->warning('Broadcasting server did not stop cleanly within the shutdown timeout', [
                'exception' => $e,
            ]);
        }

        $this->channelHub?->closeAll();
    }

    public function isRunning(): bool
    {
        return $this->httpServer !== null;
    }

    /**
     * The port actually bound (useful after starting on port 0).
     */
    public function port(): ?int
    {
        $address = $this->httpServer?->getServers()[0]?->getAddress();

        return $address instanceof InternetAddress ? $address->getPort() : null;
    }

    /**
     * The bound address, e.g. 0.0.0.0:8085, or an empty string when not running.
     */
    public function address(): string
    {
        return $this->httpServer?->getServers()[0]?->getAddress()->toString() ?? '';
    }

    public function channelHub(): ?ChannelHub
    {
        return $this->channelHub;
    }

    /**
     * Open streams this process accepts. stream_select() aborts the whole loop past FD_SETSIZE, so
     * without ev/uv/event the limit is capped to stay below it.
     */
    protected function maxConnections(): int
    {
        if (EventLoop::getDriver() instanceof StreamSelectDriver) {
            return min($this->amphpBroadcastingConfig->maxConnections, self::STREAM_SELECT_LIMIT);
        }

        return $this->amphpBroadcastingConfig->maxConnections;
    }

    /**
     * HTTP driver idle timeout. It is only refreshed by body writes, so it must stay well above
     * the heartbeat or idle streams are dropped.
     */
    protected function streamTimeout(): int
    {
        return max(60, $this->amphpBroadcastingConfig->heartbeat * 3);
    }

    private function logStats(): void
    {
        $hub = $this->channelHub;

        if ($hub === null) {
            return;
        }

        $this->logger->info(sprintf(
            'Broadcasting server: %d open streams across %d channels',
            $hub->connectionCount(),
            count($hub->channelCounts()),
        ), ['memory' => memory_get_usage(true)]);
    }
}
