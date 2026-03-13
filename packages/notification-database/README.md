# marko/notification-database

Database notification storage — persist, query, and manage notification read state in the database.

## Installation

```bash
composer require marko/notification-database
```

## Quick Example

```php
use Marko\Notification\Database\Repository\NotificationRepositoryInterface;

public function __construct(
    private NotificationRepositoryInterface $notificationRepository,
) {}

// Fetch all notifications for a user
$notifications = $this->notificationRepository->forNotifiable($user);

// Count unread
$count = $this->notificationRepository->unreadCount($user);

// Mark all as read
$this->notificationRepository->markAllAsRead($user);
```

## Documentation

Full usage, API reference, and examples: [marko/notification-database](https://marko.build/docs/packages/notification-database/)
