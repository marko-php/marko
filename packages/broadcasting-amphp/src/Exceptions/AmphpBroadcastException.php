<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Exceptions;

use Marko\Broadcasting\Exceptions\BroadcastException;
use Throwable;

class AmphpBroadcastException extends BroadcastException
{
    public static function missingAppKey(): self
    {
        return new self(
            message: 'No amphp broadcasting app key is configured.',
            context: "'broadcasting-amphp.app_key' is empty while signing or verifying a subscriber token",
            suggestion: 'Set BROADCASTING_AMPHP_APP_KEY to a long random secret (e.g. bin2hex(random_bytes(32))) for both the app and the broadcasting:serve process.',
        );
    }

    public static function missingHealthSecret(): self
    {
        return new self(
            message: 'Health detail is enabled but no health secret is configured.',
            context: "'broadcasting-amphp.health_detail' is true while 'broadcasting-amphp.health_secret' is empty, so per-channel counts (including private channel names) would have no protection",
            suggestion: 'Set BROADCASTING_AMPHP_HEALTH_SECRET to a long random secret and send it as the X-Health-Secret header, or set BROADCASTING_AMPHP_HEALTH_DETAIL=false.',
        );
    }

    public static function reservedPrivatePrefix(
        string $channel,
    ): self {
        $name = substr($channel, strlen('private-'));

        return new self(
            message: "Public channel '$channel' uses the reserved private- prefix.",
            context: 'The amphp driver publishes private channels as private-{name}, so a public channel with that prefix would bypass token checks',
            suggestion: "Broadcast to new PrivateChannel('$name') instead, or rename the channel.",
        );
    }

    public static function lineBreakInFrameField(
        string $channel,
        string $event,
        string $id,
    ): self {
        $event = addcslashes($event, "\r\n");
        $id = addcslashes($id, "\r\n");

        return new self(
            message: "Broadcast event name or id for channel '$channel' contains a line break, which would break the SSE frame.",
            context: "Event '$event', id '$id'. SSE fields end at CR or LF, so a line break would inject extra fields",
            suggestion: "Use single-line event names and ids such as 'order.shipped' and 'evt-42'.",
        );
    }

    public static function payloadTooLarge(
        string $channel,
        int $bytes,
        int $limit,
    ): self {
        return new self(
            message: "Broadcast payload for channel '$channel' is $bytes bytes, which exceeds the PostgreSQL NOTIFY limit of $limit bytes.",
            context: 'marko/pubsub-pgsql delivers messages with NOTIFY, whose payload must be shorter than 8000 bytes',
            suggestion: 'Broadcast an identifier and let the client fetch the full record, or switch to marko/pubsub-redis, which has no practical payload limit.',
        );
    }

    public static function invalidPort(
        string $value,
    ): self {
        return new self(
            message: "Invalid --port value '$value'.",
            context: 'While starting broadcasting:serve',
            suggestion: 'Pass a port number between 0 and 65535, e.g. --port=8085.',
        );
    }

    public static function signalsUnsupported(
        ?Throwable $previous = null,
    ): self {
        return new self(
            message: 'The event loop cannot listen for SIGINT/SIGTERM, so broadcasting:serve could not shut down gracefully.',
            context: 'Registering signal handlers for broadcasting:serve',
            suggestion: 'Install the pcntl extension (or ext-ev / ext-uv) for the PHP CLI that runs broadcasting:serve.',
            previous: $previous,
        );
    }

    public static function serverStartFailed(
        string $address,
        string $reason,
        ?Throwable $previous = null,
    ): self {
        return new self(
            message: "Failed to start the broadcasting server on $address.",
            context: "Binding the HTTP server failed: $reason",
            suggestion: 'Check that the port is free and the host is a local address, or pass --host / --port to broadcasting:serve.',
            previous: $previous,
        );
    }
}
