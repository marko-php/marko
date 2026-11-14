<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Pusher\Driver;

use JsonException;
use Marko\Broadcasting\BroadcastableInterface;
use Marko\Broadcasting\BroadcasterInterface;
use Marko\Broadcasting\Channel;
use Marko\Broadcasting\Exceptions\BroadcastException;
use Marko\Broadcasting\Pusher\Auth\PusherSignature;
use Marko\Broadcasting\Pusher\Exceptions\PusherException;
use Marko\Broadcasting\Pusher\PusherConfig;
use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\Exceptions\HttpException;
use Marko\Http\RequestOptions;
use Psr\Clock\ClockInterface;

/**
 * Triggers events through the Pusher HTTP API. Works with hosted Pusher and with any server that
 * speaks the Pusher protocol (Soketi, Laravel Reverb); the server holds the WebSocket connections.
 *
 * The Pusher protocol has no event ids, so the `$id` argument is not transmitted.
 */
readonly class PusherBroadcaster implements BroadcasterInterface
{
    private const string DRIVER = 'Pusher';

    private const string CHANNEL_NAME_PATTERN = '/^[A-Za-z0-9_\-=@,.;]{1,164}$/';

    public function __construct(
        private HttpClientInterface $httpClient,
        private PusherSignature $pusherSignature,
        private PusherConfig $pusherConfig,
        private ClockInterface $clock,
    ) {}

    /**
     * @throws BroadcastException|PusherException
     */
    public function broadcast(
        string|Channel $channel,
        string $event,
        array $data,
        ?string $id = null,
    ): void {
        $channel = Channel::from($channel);

        if ($event === '') {
            throw BroadcastException::emptyEventName();
        }

        $channelName = $this->channelName($channel);

        $body = $this->encodeJson([
            'name' => $event,
            'channels' => [$channelName],
            'data' => $this->encodeJson($data, $event),
        ], $event);

        $this->sendRequest($channel, $body);
    }

    /**
     * @throws BroadcastException|PusherException
     */
    public function dispatch(
        BroadcastableInterface $broadcastable,
    ): void {
        foreach ($broadcastable->channels() as $channel) {
            $this->broadcast($channel, $broadcastable->event(), $broadcastable->payload());
        }
    }

    /**
     * @throws BroadcastException|PusherException
     */
    private function sendRequest(
        Channel $channel,
        string $body,
    ): void {
        $path = "/apps/{$this->pusherConfig->appId}/events";
        $query = $this->pusherSignature->signedQuery('POST', $path, $body, $this->clock->now()->getTimestamp());

        $signedQuery = http_build_query($query);

        try {
            // http_errors off: a 4xx/5xx comes back as a response so Pusher's own reason is reported below.
            $response = $this->httpClient->post(
                $this->pusherConfig->baseUrl() . $path . '?' . $signedQuery,
                [
                    RequestOptions::HEADERS => ['Content-Type' => 'application/json'],
                    RequestOptions::BODY => $body,
                    RequestOptions::TIMEOUT => $this->pusherConfig->timeout,
                    RequestOptions::HTTP_ERRORS => false,
                ],
            );
        } catch (HttpException $e) {
            // Transport failure (ConnectionException extends HttpException). HTTP clients such as Guzzle put
            // the full request URL, signed query included, in the message, so the message is redacted and the
            // original exception is deliberately not chained.
            throw BroadcastException::publishFailed(
                self::DRIVER,
                $channel->name,
                $this->redactSignedQuery($e->getMessage(), $signedQuery, $query['auth_signature']),
            );
        }

        if (!$response->isSuccessful()) {
            throw BroadcastException::rejected(
                self::DRIVER,
                $channel->name,
                $response->statusCode(),
                $response->bodyExcerpt(),
            );
        }
    }

    /**
     * Strip the signed query string (auth_key, auth_timestamp, body_md5, auth_signature) from an error message.
     */
    private function redactSignedQuery(
        string $message,
        string $signedQuery,
        string $signature,
    ): string {
        $message = str_replace('?' . $signedQuery, '', $message);
        $message = (string) preg_replace('/\?[^\s]*auth_signature=[^\s]*/', '', $message);

        return str_replace($signature, '[redacted]', $message);
    }

    /**
     * @throws BroadcastException
     */
    private function channelName(
        Channel $channel,
    ): string {
        $channelName = match (true) {
            $channel->isPresence() => 'presence-' . $channel->name,
            $channel->isPrivate() => 'private-' . $channel->name,
            default => $channel->name,
        };

        if (preg_match(self::CHANNEL_NAME_PATTERN, $channelName) !== 1) {
            throw BroadcastException::invalidChannelName(
                self::DRIVER,
                $channel->name,
                'letters, digits and _ - = @ , . ; (164 characters at most, including the private- or presence- prefix)',
            );
        }

        return $channelName;
    }

    /**
     * @param array<string, mixed> $data
     * @throws BroadcastException
     */
    private function encodeJson(
        array $data,
        string $event,
    ): string {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw BroadcastException::unencodablePayload($event, $e->getMessage(), $e);
        }
    }
}
