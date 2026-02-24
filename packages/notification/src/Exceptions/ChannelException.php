<?php

declare(strict_types=1);

namespace Marko\Notification\Exceptions;

class ChannelException extends NotificationException
{
    public static function routeMissing(
        string $channel,
        string $notifiableType,
    ): self {
        return new self(
            message: "No routing information for channel '$channel'.",
            context: "The notifiable '$notifiableType' returned null/empty from routeNotificationFor('$channel').",
            suggestion: "Implement routeNotificationFor('$channel') on '$notifiableType' to return the appropriate routing value (e.g., email address for mail channel).",
        );
    }

    public static function deliveryFailed(
        string $channel,
        string $error,
    ): self {
        return new self(
            message: "Failed to deliver notification via '$channel' channel.",
            context: "Channel delivery error: $error",
            suggestion: "Check the '$channel' channel configuration and ensure the underlying service is available and properly configured.",
        );
    }
}
