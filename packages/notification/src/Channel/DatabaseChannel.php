<?php

declare(strict_types=1);

namespace Marko\Notification\Channel;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Notification\Contracts\ChannelInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Notification\Exceptions\ChannelException;
use Throwable;

class DatabaseChannel implements ChannelInterface
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    /**
     * Persist the notification to the database.
     *
     * @throws ChannelException On database insert failure
     */
    public function send(
        NotifiableInterface $notifiable,
        NotificationInterface $notification,
    ): void {
        $data = $notification->toDatabase($notifiable);

        try {
            $this->connection->execute(
                'INSERT INTO notifications (id, type, notifiable_type, notifiable_id, data, read_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $this->generateUuid(),
                    $notification::class,
                    $notifiable->getNotifiableType(),
                    (string) $notifiable->getNotifiableId(),
                    json_encode($data, JSON_THROW_ON_ERROR),
                    null,
                    date('Y-m-d H:i:s'),
                ],
            );
        } catch (Throwable $e) {
            throw ChannelException::deliveryFailed('database', $e->getMessage());
        }
    }

    /**
     * Generate a UUID v4 string.
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
