# Marko Notification Database

Database notification storage--persist, query, and manage notification read state in the database.

## Overview

This package provides the `DatabaseNotification` entity, `NotificationRepositoryInterface`, and a `DatabaseNotificationRepository` implementation. It lets you query a user's notifications, mark them as read, count unread notifications, and clean up old ones. Works with the database channel from `marko/notification`.

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
        private NotificationRepositoryInterface $notifications,
    ) {}

    public function index(
        User $user,
    ): array {
        return $this->notifications->forNotifiable($user);
    }

    public function unreadCount(
        User $user,
    ): int {
        return $this->notifications->unreadCount($user);
    }
}
```

### Marking as Read

Mark individual notifications or all at once:

```php
// Mark one notification as read
$this->notifications->markAsRead($notificationId);

// Mark all notifications as read for a user
$this->notifications->markAllAsRead($user);
```

### Fetching Unread Notifications

```php
$unread = $this->notifications->unread($user);

foreach ($unread as $notification) {
    $data = json_decode($notification->data, true);
    // Process notification data
}
```

### Deleting Notifications

```php
// Delete a single notification
$this->notifications->delete($notificationId);

// Delete all notifications for a user
$this->notifications->deleteAll($user);
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

```php
interface NotificationRepositoryInterface
{
    public function forNotifiable(NotifiableInterface $notifiable): array;
    public function unread(NotifiableInterface $notifiable): array;
    public function markAsRead(string $notificationId): void;
    public function markAllAsRead(NotifiableInterface $notifiable): void;
    public function delete(string $notificationId): void;
    public function deleteAll(NotifiableInterface $notifiable): void;
    public function unreadCount(NotifiableInterface $notifiable): int;
}
```

### DatabaseNotification

```php
#[Table(name: 'notifications')]
class DatabaseNotification extends Entity
{
    public string $id;
    public string $type;
    public string $notifiableType;
    public string $notifiableId;
    public string $data;           // JSON-encoded notification data
    public ?string $readAt;
    public string $createdAt;
}
```
