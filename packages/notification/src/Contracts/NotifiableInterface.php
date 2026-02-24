<?php

declare(strict_types=1);

namespace Marko\Notification\Contracts;

interface NotifiableInterface
{
    /**
     * Get the notification routing information for the given channel.
     *
     * For 'mail' channel: returns email address string.
     * For 'database' channel: returns notifiable type and ID.
     */
    public function routeNotificationFor(
        string $channel,
    ): mixed;

    /**
     * Get the unique identifier for this notifiable.
     */
    public function getNotifiableId(): string|int;

    /**
     * Get the notifiable type (typically the class name).
     */
    public function getNotifiableType(): string;
}
