<?php

declare(strict_types=1);

namespace Marko\Testing\Tests\Unit\Fake\NotificationFixtures;

use Marko\Mail\Message;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;

readonly class FixtureInvoicePaid implements NotificationInterface
{
    public function channels(
        NotifiableInterface $notifiable,
    ): array {
        return ['mail'];
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
