<?php

declare(strict_types=1);

namespace Marko\PubSub\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Marko\Core\Exceptions\UnsupportedFeature;

class PubSubException extends MarkoException
{
    public static function connectionFailed(
        string $driver,
        string $reason,
    ): self {
        return new self(
            message: "Failed to connect to pub/sub driver '$driver'.",
            context: "Connection attempt to driver '$driver' failed: $reason",
            suggestion: "Check that the '$driver' server is running and accessible, and verify your connection configuration.",
        );
    }

    public static function subscriptionFailed(
        string $channel,
        string $reason,
    ): self {
        return new self(
            message: "Failed to subscribe to channel '$channel'.",
            context: "Subscription to channel '$channel' failed: $reason",
            suggestion: "Verify that the channel '$channel' exists and that you have permission to subscribe to it.",
        );
    }

    public static function publishFailed(
        string $channel,
        string $reason,
    ): self {
        return new self(
            message: "Failed to publish to channel '$channel'.",
            context: "Publish to channel '$channel' failed: $reason",
            suggestion: "Verify that the channel '$channel' is available and that the message payload is valid.",
        );
    }

    public static function patternSubscriptionNotSupported(string $driver): UnsupportedPubSubFeatureException
    {
        return new UnsupportedPubSubFeatureException(new UnsupportedFeature(
            feature: 'pattern subscriptions',
            driver: $driver,
            suggestion: 'Use the Redis driver if you need pattern-based subscriptions',
            context: "While attempting to call psubscribe() on the $driver pub/sub driver",
            message: "Pattern subscriptions are not supported by the '$driver' driver",
        ));
    }

    /**
     * @param list<string> $validValues
     */
    public static function invalidConnectionOption(
        string $configKey,
        string $value,
        array $validValues,
    ): self {
        $valid = implode(', ', $validValues);

        return new self(
            message: "Invalid value '$value' for pub/sub connection option '$configKey'",
            context: "While configuring the pub/sub connection from '$configKey'",
            suggestion: "Set '$configKey' to one of: $valid",
        );
    }
}
