<?php

declare(strict_types=1);

namespace Marko\Notification\Database\Repository;

use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Database\Entity\DatabaseNotification;

interface NotificationRepositoryInterface
{
    /**
     * Get all notifications for a notifiable.
     *
     * @return array<DatabaseNotification>
     */
    public function forNotifiable(
        NotifiableInterface $notifiable,
    ): array;

    /**
     * Get all unread notifications for a notifiable.
     *
     * @return array<DatabaseNotification>
     */
    public function unread(
        NotifiableInterface $notifiable,
    ): array;

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(
        string $notificationId,
    ): void;

    /**
     * Mark all notifications as read for a notifiable.
     */
    public function markAllAsRead(
        NotifiableInterface $notifiable,
    ): void;

    /**
     * Delete a single notification by ID.
     */
    public function delete(
        string $notificationId,
    ): void;

    /**
     * Delete all notifications for a notifiable.
     */
    public function deleteAll(
        NotifiableInterface $notifiable,
    ): void;

    /**
     * Count unread notifications for a notifiable.
     */
    public function unreadCount(
        NotifiableInterface $notifiable,
    ): int;
}
