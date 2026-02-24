<?php

declare(strict_types=1);

namespace Marko\Notification\Contracts;

use Marko\Notification\Exceptions\ChannelException;

interface ChannelInterface
{
    /**
     * Send the given notification to the given notifiable.
     *
     * @throws ChannelException On delivery failure
     */
    public function send(
        NotifiableInterface $notifiable,
        NotificationInterface $notification,
    ): void;
}
