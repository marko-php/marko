# Task 007: DatabaseChannel Implementation

**Status**: done
**Depends on**: 001, 006
**Retry count**: 0

## Description
Implement the DatabaseChannel that persists notifications to the database. This channel is part of the `marko/notification` interface package (not the database driver package) since it implements ChannelInterface and is registered by the interface package's boot callback. It writes notification records to the notifications table using ConnectionInterface.

## Context
- Namespace: `Marko\Notification\Channel\`
- Location: `packages/notification/src/Channel/DatabaseChannel.php`
- Dependencies: `Marko\Database\Connection\ConnectionInterface`
- Note: This is in the interface package because it implements ChannelInterface (a core contract). The notification-database package provides the *repository* for reading/querying stored notifications.

### Behavior
1. Call `$notification->toDatabase($notifiable)` to get the data array
2. Generate a UUID for the notification ID
3. Insert a row into the notifications table with:
   - id: generated UUID
   - type: notification class name (`$notification::class`)
   - notifiable_type: from `$notifiable->getNotifiableType()`
   - notifiable_id: from `$notifiable->getNotifiableId()`
   - data: JSON-encoded data array
   - read_at: null (unread)
   - created_at: current timestamp
4. Throw ChannelException::deliveryFailed() if the insert fails

### Class Design
```php
class DatabaseChannel implements ChannelInterface
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function send(
        NotifiableInterface $notifiable,
        NotificationInterface $notification,
    ): void;
}
```

## Requirements (Test Descriptions)
- [ ] `it implements ChannelInterface`
- [ ] `it inserts notification record into database`
- [ ] `it stores notification type as class name`
- [ ] `it stores notifiable type and id from notifiable interface`
- [ ] `it JSON-encodes notification data from toDatabase()`
- [ ] `it sets read_at to null for new notifications`
- [ ] `it throws ChannelException when database insert fails`

## Acceptance Criteria
- DatabaseChannel implements ChannelInterface
- Constructor accepts ConnectionInterface
- Calls toDatabase() on notification to get data array
- Generates UUID for notification ID
- Inserts complete row into notifications table via connection->execute()
- Stores notification class name as type
- JSON-encodes the data array
- Sets read_at to null, created_at to current timestamp
- Wraps database exceptions in ChannelException::deliveryFailed()
- Strict types, @throws PHPDoc

## Implementation Notes
(Left blank)
