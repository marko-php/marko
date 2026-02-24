# Task 008: Database Driver Wiring

**Status**: done
**Depends on**: 006, 007
**Retry count**: 0

## Description
Create the package scaffolding and wiring for `marko/notification-database`: composer.json, module.php, Pest.php, and the DatabaseNotificationRepository implementation. This driver package provides the repository for querying, reading, and managing database-stored notifications.

## Context
- Namespace: `Marko\Notification\Database\`
- Package: `marko/notification-database`
- Dependencies: marko/core, marko/config, marko/notification, marko/database
- Reference: packages/session-database/ (recently created driver package with similar pattern)
- Reference: packages/queue-database/ (database driver pattern)

### DatabaseNotificationRepository
Implements NotificationRepositoryInterface using ConnectionInterface for SQL queries.

```php
class DatabaseNotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function forNotifiable(NotifiableInterface $notifiable): array;
    public function unread(NotifiableInterface $notifiable): array;
    public function markAsRead(string $notificationId): void;
    public function markAllAsRead(NotifiableInterface $notifiable): void;
    public function delete(string $notificationId): void;
    public function deleteAll(NotifiableInterface $notifiable): void;
    public function unreadCount(NotifiableInterface $notifiable): int;
}
```

### Query Patterns
- `forNotifiable()`: SELECT * WHERE notifiable_type = ? AND notifiable_id = ? ORDER BY created_at DESC
- `unread()`: SELECT * WHERE notifiable_type = ? AND notifiable_id = ? AND read_at IS NULL ORDER BY created_at DESC
- `markAsRead()`: UPDATE SET read_at = NOW() WHERE id = ?
- `markAllAsRead()`: UPDATE SET read_at = NOW() WHERE notifiable_type = ? AND notifiable_id = ? AND read_at IS NULL
- `delete()`: DELETE WHERE id = ?
- `deleteAll()`: DELETE WHERE notifiable_type = ? AND notifiable_id = ?
- `unreadCount()`: SELECT COUNT(*) WHERE notifiable_type = ? AND notifiable_id = ? AND read_at IS NULL

### composer.json
```json
{
    "name": "marko/notification-database",
    "description": "Database-backed notification storage for the Marko Framework",
    "type": "marko-module",
    "require": {
        "php": "^8.5",
        "marko/core": "^1.0",
        "marko/notification": "^1.0",
        "marko/database": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "Marko\\Notification\\Database\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Marko\\Notification\\Database\\Tests\\": "tests/"
        }
    },
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

### module.php
```php
return [
    'enabled' => true,
    'bindings' => [
        NotificationRepositoryInterface::class => DatabaseNotificationRepository::class,
    ],
];
```

## Requirements (Test Descriptions)
- [ ] `it has marko module flag in composer.json`
- [ ] `it has correct PSR-4 autoloading namespace`
- [ ] `it requires marko/notification and marko/database`
- [ ] `it binds NotificationRepositoryInterface to DatabaseNotificationRepository`
- [ ] `it returns all notifications for a notifiable ordered by created_at desc`
- [ ] `it returns only unread notifications for a notifiable`
- [ ] `it marks a single notification as read`
- [ ] `it marks all notifications as read for a notifiable`
- [ ] `it deletes a single notification by id`
- [ ] `it deletes all notifications for a notifiable`
- [ ] `it counts unread notifications for a notifiable`

## Acceptance Criteria
- composer.json with correct name, type, require, autoload, extra (no hardcoded version)
- module.php binds NotificationRepositoryInterface to DatabaseNotificationRepository
- DatabaseNotificationRepository implements all NotificationRepositoryInterface methods
- All queries filter by notifiable_type AND notifiable_id for polymorphic support
- forNotifiable() and unread() return results ordered by created_at DESC
- markAsRead() updates read_at to current timestamp
- markAllAsRead() only updates rows where read_at IS NULL
- unreadCount() uses COUNT query for efficiency
- Pest.php configured for the package
- Strict types, constructor property promotion

## Implementation Notes
(Left blank)
