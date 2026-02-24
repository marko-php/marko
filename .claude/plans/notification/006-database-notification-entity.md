# Task 006: DatabaseNotification Entity, Migration Schema, and Repository Interface

**Status**: done
**Depends on**: 001
**Retry count**: 0

## Description
Create the DatabaseNotification entity with proper database attributes, define the notifications table schema, and create the NotificationRepositoryInterface contract for querying stored notifications. This task covers the data layer that both the DatabaseChannel and the notification-database driver package depend on.

## Context
- Namespace: `Marko\Notification\Database\`
- Package: `marko/notification-database`
- Dependencies: marko/core, marko/config, marko/notification, marko/database
- Reference: packages/database/src/Entity/Entity.php for entity base class
- Reference: packages/database/src/Attributes/ for #[Table], #[Column] attributes
- Reference: packages/database/src/Repository/RepositoryInterface.php for repository pattern

### DatabaseNotification Entity
```php
#[Table(name: 'notifications')]
class DatabaseNotification extends Entity
{
    #[Column(type: 'varchar', length: 36, primaryKey: true)]
    public string $id;

    #[Column(type: 'varchar', length: 255)]
    public string $type;

    #[Column(type: 'varchar', length: 255)]
    public string $notifiableType;

    #[Column(type: 'varchar', length: 255)]
    public string $notifiableId;

    #[Column(type: 'text')]
    public string $data;

    #[Column(type: 'timestamp', nullable: true)]
    public ?string $readAt = null;

    #[Column(type: 'timestamp')]
    public string $createdAt;
}
```

### Table Schema
```sql
CREATE TABLE notifications (
    id VARCHAR(36) PRIMARY KEY,
    type VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id VARCHAR(255) NOT NULL,
    data TEXT NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifiable (notifiable_type, notifiable_id),
    INDEX idx_read_at (read_at)
);
```

### NotificationRepositoryInterface
Located in the notification-database package since it is specific to database-stored notifications.

```php
interface NotificationRepositoryInterface
{
    /** @return array<DatabaseNotification> */
    public function forNotifiable(NotifiableInterface $notifiable): array;

    /** @return array<DatabaseNotification> */
    public function unread(NotifiableInterface $notifiable): array;

    public function markAsRead(string $notificationId): void;

    public function markAllAsRead(NotifiableInterface $notifiable): void;

    public function delete(string $notificationId): void;

    public function deleteAll(NotifiableInterface $notifiable): void;

    public function unreadCount(NotifiableInterface $notifiable): int;
}
```

## Requirements (Test Descriptions)
- [ ] `it defines DatabaseNotification entity with Table and Column attributes`
- [ ] `it has id, type, notifiableType, notifiableId, data, readAt, and createdAt properties`
- [ ] `it defines NotificationRepositoryInterface with forNotifiable and unread methods`
- [ ] `it defines NotificationRepositoryInterface with markAsRead and markAllAsRead methods`
- [ ] `it defines NotificationRepositoryInterface with delete, deleteAll, and unreadCount methods`
- [ ] `it maps entity properties to correct column types`

## Acceptance Criteria
- DatabaseNotification extends Entity with proper #[Table] and #[Column] attributes
- Entity has: id (varchar 36 PK), type (varchar 255), notifiableType (varchar 255), notifiableId (varchar 255), data (text), readAt (nullable timestamp), createdAt (timestamp)
- NotificationRepositoryInterface defines all query methods
- All methods use NotifiableInterface for polymorphic queries (notifiable_type + notifiable_id)
- readAt is nullable (null = unread, timestamp = read)
- Strict types, no final class on entity

## Implementation Notes
(Left blank)
