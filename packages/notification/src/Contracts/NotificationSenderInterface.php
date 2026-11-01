<?php

declare(strict_types=1);

namespace Marko\Notification\Contracts;

use Marko\Notification\Exceptions\ChannelException;
use Marko\Notification\Exceptions\NotificationException;

interface NotificationSenderInterface
{
    /**
     * Send a notification to the given notifiable(s) on every channel the notification declares.
     *
     * @param NotifiableInterface|array<NotifiableInterface> $notifiables
     * @throws NotificationException|ChannelException
     */
    public function send(
        NotifiableInterface|array $notifiables,
        NotificationInterface $notification,
    ): void;

    /**
     * Queue a notification for later sending.
     *
     * @param NotifiableInterface|array<NotifiableInterface> $notifiables
     * @throws NotificationException When no queue is available
     */
    public function queue(
        NotifiableInterface|array $notifiables,
        NotificationInterface $notification,
    ): void;
}
