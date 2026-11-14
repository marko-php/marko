<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Driver;

use JsonException;
use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Broadcasting\Amphp\Exceptions\AmphpBroadcastException;
use Marko\Broadcasting\BroadcastableInterface;
use Marko\Broadcasting\BroadcasterInterface;
use Marko\Broadcasting\Channel;
use Marko\Broadcasting\Exceptions\BroadcastException;
use Marko\PubSub\Exceptions\PubSubException;
use Marko\PubSub\Message;
use Marko\PubSub\PgSql\Driver\PgSqlPublisher;
use Marko\PubSub\PublisherInterface;
use Psr\Clock\ClockInterface;
use Random\RandomException;
use stdClass;

/**
 * Publishes broadcasts onto marko/pubsub. The `broadcasting:serve` process subscribes to the same
 * channels and streams the events to browsers, so a broadcast costs one Redis PUBLISH or Postgres
 * NOTIFY and no HTTP request.
 *
 * Wire format: {"event": string, "data": object, "id": string} on channel_prefix + [private-]name.
 */
readonly class AmphpBroadcaster implements BroadcasterInterface
{
    public const string PRIVATE_PREFIX = 'private-';

    public const string CHANNEL_NAME_PATTERN = '/^[A-Za-z0-9_\-=@.;:]{1,200}$/';

    /**
     * Human-readable form of CHANNEL_NAME_PATTERN for error messages.
     */
    public const string CHANNEL_NAME_ALLOWED = 'letters, digits and _ - = @ . ; : (200 characters at most)';

    /**
     * PostgreSQL rejects NOTIFY payloads of 8000 bytes or more.
     */
    public const int PGSQL_PAYLOAD_LIMIT = 7999;

    private const string DRIVER = 'Amphp';

    public function __construct(
        private PublisherInterface $publisher,
        private AmphpBroadcastingConfig $amphpBroadcastingConfig,
        private ClockInterface $clock,
    ) {}

    /**
     * @throws BroadcastException|AmphpBroadcastException|RandomException
     */
    public function broadcast(
        string|Channel $channel,
        string $event,
        array $data,
        ?string $id = null,
    ): void {
        $channel = Channel::from($channel);

        if ($channel->isPresence()) {
            throw BroadcastException::presenceChannelsUnsupported(self::DRIVER, $channel->name);
        }

        if ($event === '') {
            throw BroadcastException::emptyEventName();
        }

        $id ??= $this->generateId();

        if (strpbrk($event . $id, "\r\n") !== false) {
            throw AmphpBroadcastException::lineBreakInFrameField($channel->name, $event, $id);
        }

        $pubSubChannel = $this->amphpBroadcastingConfig->channelPrefix . $this->channelName($channel);
        $payload = $this->encodeJson([
            'event' => $event,
            'data' => $data === [] ? new stdClass() : $data,
            'id' => $id,
        ], $event);

        if (
            $this->publisher instanceof PgSqlPublisher
            && strlen($payload) > self::PGSQL_PAYLOAD_LIMIT
        ) {
            throw AmphpBroadcastException::payloadTooLarge(
                $channel->name,
                strlen($payload),
                self::PGSQL_PAYLOAD_LIMIT,
            );
        }

        try {
            $this->publisher->publish($pubSubChannel, new Message(channel: $pubSubChannel, payload: $payload));
        } catch (PubSubException $e) {
            throw BroadcastException::publishFailed(self::DRIVER, $channel->name, $e->getMessage(), $e);
        }
    }

    /**
     * @throws BroadcastException|AmphpBroadcastException|RandomException
     */
    public function dispatch(
        BroadcastableInterface $broadcastable,
    ): void {
        foreach ($broadcastable->channels() as $channel) {
            $this->broadcast($channel, $broadcastable->event(), $broadcastable->payload());
        }
    }

    /**
     * A time-ordered id: 13-digit unix milliseconds, a dash, and 16 random hex characters.
     *
     * @throws RandomException
     */
    protected function generateId(): string
    {
        return sprintf('%013d-%s', (int) $this->clock->now()->format('Uv'), bin2hex(random_bytes(8)));
    }

    /**
     * @throws BroadcastException|AmphpBroadcastException
     */
    private function channelName(
        Channel $channel,
    ): string {
        if (preg_match(self::CHANNEL_NAME_PATTERN, $channel->name) !== 1) {
            throw BroadcastException::invalidChannelName(
                self::DRIVER,
                $channel->name,
                self::CHANNEL_NAME_ALLOWED,
            );
        }

        if ($channel->isPrivate()) {
            return self::PRIVATE_PREFIX . $channel->name;
        }

        if (str_starts_with($channel->name, self::PRIVATE_PREFIX)) {
            throw AmphpBroadcastException::reservedPrivatePrefix($channel->name);
        }

        return $channel->name;
    }

    /**
     * @param array<string, mixed> $message
     * @throws BroadcastException
     */
    private function encodeJson(
        array $message,
        string $event,
    ): string {
        try {
            return json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw BroadcastException::unencodablePayload($event, $e->getMessage(), $e);
        }
    }
}
