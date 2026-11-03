<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Marko\Core\Exceptions\UnsupportedFeature;
use Throwable;

class BroadcastException extends MarkoException
{
    public static function emptyChannelName(): self
    {
        return new self(
            message: 'Channel name must not be empty.',
            context: 'While creating a broadcasting Channel',
            suggestion: "Pass a non-empty name such as 'orders.42' to Channel or PrivateChannel.",
        );
    }

    public static function unsafeChannelName(string $channel): self
    {
        $printable = addcslashes($channel, "\0..\37\177");

        return new self(
            message: "Channel name '$printable' contains a character that is not allowed.",
            context: 'While creating a broadcasting Channel',
            suggestion: 'Channel names must not contain { } * , whitespace or control characters; '
                . "use letters, digits and separators such as . - _ = @, e.g. 'orders.42'.",
        );
    }

    public static function emptyPresenceMemberId(): self
    {
        return new self(
            message: 'Presence member id must not be empty.',
            context: 'While creating a PresenceMember',
            suggestion: "Pass the user's identifier, such as \$user->getAuthIdentifier(), as the member id.",
        );
    }

    public static function presenceChannelsUnsupported(
        string $driver,
        string $channel,
    ): UnsupportedBroadcastFeatureException {
        return new UnsupportedBroadcastFeatureException(new UnsupportedFeature(
            feature: 'presence channels',
            driver: $driver,
            suggestion: 'Use a PrivateChannel instead, or switch to the Pusher driver (marko/broadcasting-pusher) for presence channels.',
            context: "The $driver driver cannot track presence channel members",
            message: "Presence channel '$channel' is not supported by $driver.",
        ));
    }

    public static function emptyEventName(): self
    {
        return new self(
            message: 'Broadcast event name must not be empty.',
            context: 'While broadcasting an event',
            suggestion: "Pass a non-empty event name such as 'order.shipped'.",
        );
    }

    public static function unencodablePayload(
        string $event,
        string $reason,
        ?Throwable $previous = null,
    ): self {
        return new self(
            message: "Payload for broadcast event '$event' cannot be encoded as JSON.",
            context: "json_encode failed: $reason",
            suggestion: 'Broadcast only JSON-serializable data: scalars, arrays and JsonSerializable objects.',
            previous: $previous,
        );
    }

    public static function publishFailed(
        string $driver,
        string $channel,
        string $reason,
        ?Throwable $previous = null,
    ): self {
        return new self(
            message: "Failed to broadcast to channel '$channel' via $driver.",
            context: "The $driver request for channel '$channel' failed: $reason",
            suggestion: "Check that the $driver server is reachable and that the credentials in config/broadcasting-" . strtolower(
                $driver,
            ) . '.php are correct.',
            previous: $previous,
        );
    }

    /**
     * The server answered the publish with a non-2xx status. $body should already be capped
     * (HttpResponse::bodyExcerpt()); it must never contain the request URL or credentials.
     */
    public static function rejected(
        string $driver,
        string $channel,
        int $status,
        string $body,
    ): self {
        $config = 'config/broadcasting-' . strtolower($driver) . '.php';

        $suggestion = match (true) {
            $status === 401, $status === 403 => "Check the credentials in $config: the $driver server did not accept them.",
            $status === 413 => 'Send a smaller payload, e.g. broadcast ids and let clients fetch the details. Hosted Pusher allows 10 KB per event; Mercure hubs and self-hosted servers set their own limit.',
            $status >= 500 => "This is a server-side failure in the $driver server. Check its logs and retry later.",
            $status >= 400 => "Check the event name, channel name and payload against what the $driver server accepts.",
            default => "Check the server URL in $config: the $driver server answered with a non-2xx status (such as a redirect) instead of accepting the publish.",
        };

        return new self(
            message: "Failed to broadcast to channel '$channel' via $driver.",
            context: "The $driver server responded with HTTP $status: " . ($body === '' ? '(empty body)' : $body),
            suggestion: $suggestion,
        );
    }

    public static function invalidChannelName(
        string $driver,
        string $channel,
        string $allowed,
    ): self {
        return new self(
            message: "Channel name '$channel' is not valid for $driver.",
            context: "$driver channel names may only contain $allowed",
            suggestion: "Rename the channel so it only uses $allowed, e.g. 'orders.42'.",
        );
    }
}
