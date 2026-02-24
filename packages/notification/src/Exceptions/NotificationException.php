<?php

declare(strict_types=1);

namespace Marko\Notification\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class NotificationException extends MarkoException
{
    public static function unknownChannel(
        string $channel,
    ): self {
        return new self(
            message: "Unknown notification channel '$channel'.",
            context: "Attempted to send a notification via channel '$channel' but it is not registered.",
            suggestion: 'Register the channel with NotificationManager::register() or check the channel name for typos. Available channels are registered during module boot.',
        );
    }

    public static function noQueueAvailable(): self
    {
        return new self(
            message: 'No queue implementation available for queued notifications.',
            context: 'Attempted to queue a notification but QueueInterface is not bound in the container.',
            suggestion: 'Install a queue driver: composer require marko/queue-sync or marko/queue-database',
        );
    }

    public static function sendFailed(
        string $channel,
        string $reason,
    ): self {
        return new self(
            message: "Notification delivery failed on channel '$channel'.",
            context: "The '$channel' channel encountered an error: $reason",
            suggestion: 'Check the channel configuration and ensure the underlying service (mail server, database, etc.) is available.',
        );
    }
}
