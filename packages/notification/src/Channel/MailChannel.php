<?php

declare(strict_types=1);

namespace Marko\Notification\Channel;

use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Exception\TransportException;
use Marko\Notification\Contracts\ChannelInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Notification\Exceptions\ChannelException;

class MailChannel implements ChannelInterface
{
    public function __construct(
        private MailerInterface $mailer,
    ) {}

    /**
     * Send the notification as a mail message.
     *
     * @throws ChannelException On delivery failure or missing route
     */
    public function send(
        NotifiableInterface $notifiable,
        NotificationInterface $notification,
    ): void {
        $message = $notification->toMail($notifiable);

        if ($message->to === []) {
            $route = $notifiable->routeNotificationFor('mail');

            if ($route === null || $route === '') {
                throw ChannelException::routeMissing('mail', $notifiable->getNotifiableType());
            }

            $message->to($route);
        }

        try {
            $this->mailer->send($message);
        } catch (TransportException $e) {
            throw ChannelException::deliveryFailed('mail', $e->getMessage());
        }
    }
}
