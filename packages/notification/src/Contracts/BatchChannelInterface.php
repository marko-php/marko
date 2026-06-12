<?php

declare(strict_types=1);

namespace Marko\Notification\Contracts;

use Marko\Notification\Exceptions\ChannelException;

interface BatchChannelInterface
{
    /**
     * Send the given notification to multiple notifiables in a single batch operation.
     *
     * @param array<NotifiableInterface> $notifiables
     * @throws ChannelException On batch delivery failure
     */
    public function sendMany(
        array $notifiables,
        NotificationInterface $notification,
    ): void;
}
