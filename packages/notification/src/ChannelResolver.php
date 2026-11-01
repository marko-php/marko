<?php

declare(strict_types=1);

namespace Marko\Notification;

use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;

/**
 * Works out which channels a notification goes out on for each notifiable.
 *
 * Shared by NotificationSender (to deliver) and test fakes (to record the channels a real send
 * would use), so the mapping lives in exactly one place.
 */
class ChannelResolver
{
    /**
     * Map each channel name to the notifiables that declared it, in declaration order.
     *
     * @param array<NotifiableInterface> $notifiables
     * @return array<string, list<NotifiableInterface>>
     */
    public function resolve(
        array $notifiables,
        NotificationInterface $notification,
    ): array {
        $channelRecipients = [];

        foreach ($notifiables as $notifiable) {
            foreach ($notification->channels($notifiable) as $channelName) {
                $channelRecipients[$channelName][] = $notifiable;
            }
        }

        return $channelRecipients;
    }
}
