<?php

declare(strict_types=1);

namespace Marko\Notification\Contracts;

use Marko\Mail\Message;

interface NotificationInterface
{
    /**
     * Get the channels this notification should be sent on.
     *
     * @return array<string> Channel names (e.g., ['mail', 'database'])
     */
    public function channels(
        NotifiableInterface $notifiable,
    ): array;

    /**
     * Build the mail representation of the notification.
     * Only required when 'mail' is in channels().
     */
    public function toMail(
        NotifiableInterface $notifiable,
    ): Message;

    /**
     * Build the database representation of the notification.
     * Only required when 'database' is in channels().
     *
     * @return array<string, mixed>
     */
    public function toDatabase(
        NotifiableInterface $notifiable,
    ): array;
}
