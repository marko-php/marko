<?php

declare(strict_types=1);

namespace Marko\Testing\Tests\Unit\Fake\NotificationFixtures;

use Marko\Mail\Message;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;

/**
 * Channels depend on the notifiable: id 1 gets mail + database, everyone else database only.
 */
readonly class FixtureOrderShipped implements NotificationInterface
{
    public function __construct(
        public string $trackingNumber = 'TRK-1',
    ) {}

    public function channels(
        NotifiableInterface $notifiable,
    ): array {
        return (string) $notifiable->getNotifiableId() === '1' ? ['mail', 'database'] : ['database'];
    }

    public function toMail(
        NotifiableInterface $notifiable,
    ): Message {
        return Message::create();
    }

    public function toDatabase(
        NotifiableInterface $notifiable,
    ): array {
        return [];
    }
}
