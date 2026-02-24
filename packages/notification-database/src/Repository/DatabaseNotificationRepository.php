<?php

declare(strict_types=1);

namespace Marko\Notification\Database\Repository;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Database\Entity\DatabaseNotification;

class DatabaseNotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    /**
     * @return array<DatabaseNotification>
     */
    public function forNotifiable(
        NotifiableInterface $notifiable,
    ): array {
        $rows = $this->connection->query(
            'SELECT * FROM notifications WHERE notifiable_type = ? AND notifiable_id = ? ORDER BY created_at DESC',
            [$notifiable->getNotifiableType(), (string) $notifiable->getNotifiableId()],
        );

        return array_map($this->hydrate(...), $rows);
    }

    /**
     * @return array<DatabaseNotification>
     */
    public function unread(
        NotifiableInterface $notifiable,
    ): array {
        $rows = $this->connection->query(
            'SELECT * FROM notifications WHERE notifiable_type = ? AND notifiable_id = ? AND read_at IS NULL ORDER BY created_at DESC',
            [$notifiable->getNotifiableType(), (string) $notifiable->getNotifiableId()],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function markAsRead(
        string $notificationId,
    ): void {
        $this->connection->execute(
            'UPDATE notifications SET read_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $notificationId],
        );
    }

    public function markAllAsRead(
        NotifiableInterface $notifiable,
    ): void {
        $this->connection->execute(
            'UPDATE notifications SET read_at = ? WHERE notifiable_type = ? AND notifiable_id = ? AND read_at IS NULL',
            [date('Y-m-d H:i:s'), $notifiable->getNotifiableType(), (string) $notifiable->getNotifiableId()],
        );
    }

    public function delete(
        string $notificationId,
    ): void {
        $this->connection->execute(
            'DELETE FROM notifications WHERE id = ?',
            [$notificationId],
        );
    }

    public function deleteAll(
        NotifiableInterface $notifiable,
    ): void {
        $this->connection->execute(
            'DELETE FROM notifications WHERE notifiable_type = ? AND notifiable_id = ?',
            [$notifiable->getNotifiableType(), (string) $notifiable->getNotifiableId()],
        );
    }

    public function unreadCount(
        NotifiableInterface $notifiable,
    ): int {
        $result = $this->connection->query(
            'SELECT COUNT(*) as count FROM notifications WHERE notifiable_type = ? AND notifiable_id = ? AND read_at IS NULL',
            [$notifiable->getNotifiableType(), (string) $notifiable->getNotifiableId()],
        );

        return (int) ($result[0]['count'] ?? 0);
    }

    private function hydrate(
        array $row,
    ): DatabaseNotification {
        $notification = new DatabaseNotification();
        $notification->id = $row['id'];
        $notification->type = $row['type'];
        $notification->notifiableType = $row['notifiable_type'];
        $notification->notifiableId = $row['notifiable_id'];
        $notification->data = $row['data'];
        $notification->readAt = $row['read_at'] ?? null;
        $notification->createdAt = $row['created_at'];

        return $notification;
    }
}
