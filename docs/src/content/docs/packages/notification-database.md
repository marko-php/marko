---
title: marko/notification-database
description: Database notification storage — persist, query, and manage notification read state in the database.
---

Database notification storage --- persist, query, and manage notification read state in the database. Provides the `DatabaseNotification` entity, `NotificationRepositoryInterface`, and a `DatabaseNotificationRepository` implementation. Query a user's notifications, mark them as read, count unread notifications, and clean up old ones.

Works with the database channel from [`marko/notification`](/docs/packages/notification/) and requires [`marko/database`](/docs/packages/database/) for the database connection.

## Installation

```bash
composer require marko/notification-database
```

## Usage

### Querying Notifications

Inject the repository to fetch notifications for a notifiable entity:

```php
use Marko\Notification\Database\Repository\NotificationRepositoryInterface;

class NotificationController
{
    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
    ) {}

    public function index(
        User $user,
    ): array {
        return $this->notificationRepository->forNotifiable($user);
    }

    public function unreadCount(
        User $user,
    ): int {
        return $this->notificationRepository->unreadCount($user);
    }
}
```

### Marking as Read

Mark individual notifications or all at once:

```php
// Mark one notification as read
$this->notificationRepository->markAsRead($notificationId);

// Mark all notifications as read for a user
$this->notificationRepository->markAllAsRead($user);
```

### Fetching Unread Notifications

```php
$unread = $this->notificationRepository->unread($user);

foreach ($unread as $notification) {
    $data = json_decode($notification->data, true);
    // Process notification data
}
```

### Deleting Notifications

```php
// Delete a single notification
$this->notificationRepository->delete($notificationId);

// Delete all notifications for a user
$this->notificationRepository->deleteAll($user);
```

## Customization

Replace the repository via Preference to add custom query logic:

```php
use Marko\Core\Attributes\Preference;
use Marko\Notification\Database\Repository\DatabaseNotificationRepository;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Database\Entity\DatabaseNotification;

#[Preference(replaces: DatabaseNotificationRepository::class)]
class CustomNotificationRepository extends DatabaseNotificationRepository
{
    /**
     * @return array<DatabaseNotification>
     */
    public function forNotifiable(
        NotifiableInterface $notifiable,
    ): array {
        // Custom query logic (e.g., pagination, filtering by type)
        return parent::forNotifiable($notifiable);
    }
}
```

## API Reference

### NotificationRepositoryInterface

| Method | Description |
|---|---|
| `forNotifiable(NotifiableInterface $notifiable): array` | Get all notifications for a notifiable, most recent first. Returns `array<DatabaseNotification>`. |
| `unread(NotifiableInterface $notifiable): array` | Get all unread notifications for a notifiable, most recent first. Returns `array<DatabaseNotification>`. |
| `markAsRead(string $notificationId): void` | Mark a single notification as read. |
| `markAllAsRead(NotifiableInterface $notifiable): void` | Mark all notifications as read for a notifiable. |
| `delete(string $notificationId): void` | Delete a single notification by ID. |
| `deleteAll(NotifiableInterface $notifiable): void` | Delete all notifications for a notifiable. |
| `unreadCount(NotifiableInterface $notifiable): int` | Count unread notifications for a notifiable. |

### DatabaseNotification

Entity mapped to the `notifications` table. Accepts a `ConnectionInterface` connection.

| Property | Type | Description |
|---|---|---|
| `$id` | `string` | UUID primary key (varchar 36). |
| `$type` | `string` | Notification class name. |
| `$notifiableType` | `string` | Notifiable entity class name. |
| `$notifiableId` | `string` | Notifiable entity ID. |
| `$data` | `string` | JSON-encoded notification data. |
| `$readAt` | `?string` | Timestamp when read, or `null` if unread. |
| `$createdAt` | `string` | Timestamp when the notification was created. |
