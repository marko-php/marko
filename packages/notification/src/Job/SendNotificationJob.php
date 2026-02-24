<?php

declare(strict_types=1);

namespace Marko\Notification\Job;

use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Notification\NotificationSender;
use Marko\Queue\Job;

class SendNotificationJob extends Job
{
    public function __construct(
        private readonly NotificationSender $sender,
        /** @var NotifiableInterface|array<NotifiableInterface> */
        private readonly NotifiableInterface|array $notifiables,
        private readonly NotificationInterface $notification,
    ) {}

    public function handle(): void
    {
        $this->sender->send($this->notifiables, $this->notification);
    }
}
