# Plan: Notification System

## Created
2026-02-24

## Status
done

## Objective
Build `marko/notification` (interface) and `marko/notification-database` (driver) -- a notification system that dispatches notifications across multiple channels (mail, database, etc.) with queue support. The interface package defines contracts and provides mail/database channels, while the database driver package provides persistent storage and querying for database notifications.

## Scope

### In Scope
- `marko/notification` interface package:
  - `NotificationInterface` - contract notifications must implement (channels, toMail, toDatabase, etc.)
  - `NotifiableInterface` - contract for entities that can receive notifications (routeNotificationFor)
  - `ChannelInterface` - contract for delivery channels (send method)
  - `NotificationManager` - registry of channels, resolves channel by name
  - `MailChannel` - sends notifications via `marko/mail`'s MailerInterface
  - `DatabaseChannel` - stores notifications via `marko/database`'s ConnectionInterface
  - `NotificationSender` - orchestrates sending across channels with optional queue support
  - `NotificationException` hierarchy
  - Config for default channels
  - module.php with bindings, composer.json
- `marko/notification-database` driver package:
  - `DatabaseNotification` entity with `#[Table]`/`#[Column]` attributes
  - Migration schema for notifications table
  - `NotificationRepositoryInterface` and `DatabaseNotificationRepository` for querying (unread, markAsRead, markAllAsRead, etc.)
  - module.php with bindings, composer.json

### Out of Scope
- SMS/Slack/webhook channels (future packages)
- Notification preferences per user (future feature)
- Broadcast/real-time notifications (WebSocket)
- Notification templates or formatting system
- CLI commands for notification management
- Notification grouping or digest

## Success Criteria
- [ ] `NotificationInterface` requires channels() and channel-specific methods (toMail, toDatabase)
- [ ] `NotifiableInterface` provides routeNotificationFor() to resolve recipient addresses per channel
- [ ] `ChannelInterface` defines send(NotifiableInterface, NotificationInterface) contract
- [ ] `NotificationManager` registers and resolves channels by name
- [ ] `MailChannel` converts notification to Message and sends via MailerInterface
- [ ] `DatabaseChannel` persists notification data to database via ConnectionInterface
- [ ] `NotificationSender` dispatches notification to all declared channels
- [ ] `NotificationSender` supports queue dispatch when QueueInterface is available
- [ ] `DatabaseNotification` entity maps to notifications table with proper columns
- [ ] `DatabaseNotificationRepository` supports unread(), markAsRead(), markAllAsRead(), forNotifiable()
- [ ] Loud error when notification declares unknown channel
- [ ] Loud error when NotifiableInterface cannot route for a channel
- [ ] All tests passing with >90% coverage on critical paths
- [ ] Code follows project standards (strict types, no final, constructor promotion)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Notification contracts (NotificationInterface, NotifiableInterface, ChannelInterface) | - | done |
| 002 | NotificationManager (channel registry and resolution) | 001 | done |
| 003 | MailChannel (send notifications via marko/mail) | 001 | done |
| 004 | NotificationSender (orchestrates multi-channel dispatch with queue support) | 001, 002 | done |
| 005 | Notification interface package config and wiring (config, module.php, composer.json) | 001, 002, 003, 004 | done |
| 006 | DatabaseNotification entity, migration schema, and repository interface | 001 | done |
| 007 | DatabaseChannel implementation (persists notifications to database) | 001, 006 | done |
| 008 | Database driver wiring (module.php, composer.json for notification-database) | 006, 007 | done |

## Architecture Notes

### Package Structure
```
packages/
  notification/                     # Interface package
    src/
      Contracts/
        NotificationInterface.php
        NotifiableInterface.php
        ChannelInterface.php
      Channel/
        MailChannel.php
        DatabaseChannel.php
      Config/
        NotificationConfig.php
      Exceptions/
        NotificationException.php
        ChannelException.php
      NotificationManager.php
      NotificationSender.php
    config/
      notification.php
    tests/
    composer.json
    module.php
  notification-database/            # Database driver package
    src/
      Entity/
        DatabaseNotification.php
      Repository/
        NotificationRepositoryInterface.php
        DatabaseNotificationRepository.php
    tests/
    composer.json
    module.php
```

### Config Location
```php
// packages/notification/config/notification.php
return [
    'channels' => ['mail', 'database'],
];
```

### Interface Design

```php
// NotificationInterface - what notifications implement
interface NotificationInterface
{
    /**
     * Get the channels this notification should be sent on.
     *
     * @return array<string> Channel names (e.g., ['mail', 'database'])
     */
    public function channels(
        NotifiableInterface $notifiable,
    ): array;

    /**
     * Build the mail representation of the notification.
     * Only required when 'mail' is in channels().
     */
    public function toMail(
        NotifiableInterface $notifiable,
    ): Message;

    /**
     * Build the database representation of the notification.
     * Only required when 'database' is in channels().
     *
     * @return array<string, mixed>
     */
    public function toDatabase(
        NotifiableInterface $notifiable,
    ): array;
}

// NotifiableInterface - entities that receive notifications
interface NotifiableInterface
{
    /**
     * Get the notification routing information for the given channel.
     *
     * For 'mail' channel: returns email address string.
     * For 'database' channel: returns notifiable type and ID.
     */
    public function routeNotificationFor(
        string $channel,
    ): mixed;

    /**
     * Get the unique identifier for this notifiable.
     */
    public function getNotifiableId(): string|int;

    /**
     * Get the notifiable type (typically the class name).
     */
    public function getNotifiableType(): string;
}

// ChannelInterface - delivery channels
interface ChannelInterface
{
    /**
     * Send the given notification to the given notifiable.
     *
     * @throws ChannelException On delivery failure
     */
    public function send(
        NotifiableInterface $notifiable,
        NotificationInterface $notification,
    ): void;
}
```

### NotificationManager
```php
class NotificationManager
{
    /** @var array<string, ChannelInterface> */
    private array $channels = [];

    public function channel(
        string $name,
    ): ChannelInterface;

    public function register(
        string $name,
        ChannelInterface $channel,
    ): void;

    public function hasChannel(
        string $name,
    ): bool;
}
```

### NotificationSender
```php
class NotificationSender
{
    public function __construct(
        private NotificationManager $manager,
        private ?QueueInterface $queue = null,
    ) {}

    /**
     * Send a notification to the given notifiable(s).
     *
     * @param NotifiableInterface|array<NotifiableInterface> $notifiables
     * @throws NotificationException|ChannelException
     */
    public function send(
        NotifiableInterface|array $notifiables,
        NotificationInterface $notification,
    ): void;

    /**
     * Queue a notification for later sending.
     *
     * @param NotifiableInterface|array<NotifiableInterface> $notifiables
     * @throws NotificationException When queue is not available
     */
    public function queue(
        NotifiableInterface|array $notifiables,
        NotificationInterface $notification,
    ): void;
}
```

### MailChannel
```php
class MailChannel implements ChannelInterface
{
    public function __construct(
        private MailerInterface $mailer,
    ) {}

    public function send(
        NotifiableInterface $notifiable,
        NotificationInterface $notification,
    ): void {
        $message = $notification->toMail($notifiable);
        $route = $notifiable->routeNotificationFor('mail');

        if ($message->to === []) {
            $message->to($route);
        }

        $this->mailer->send($message);
    }
}
```

### DatabaseChannel
```php
class DatabaseChannel implements ChannelInterface
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function send(
        NotifiableInterface $notifiable,
        NotificationInterface $notification,
    ): void {
        $data = $notification->toDatabase($notifiable);

        $this->connection->execute(
            'INSERT INTO notifications (id, type, notifiable_type, notifiable_id, data, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [uuid(), $notification::class, $notifiable->getNotifiableType(), $notifiable->getNotifiableId(), json_encode($data), date('Y-m-d H:i:s')],
        );
    }
}
```

### DatabaseNotification Entity
```php
#[Table(name: 'notifications')]
class DatabaseNotification extends Entity
{
    #[Column(type: 'varchar', length: 36)]
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

### Notifications Table Schema
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
```php
interface NotificationRepositoryInterface
{
    public function forNotifiable(
        NotifiableInterface $notifiable,
    ): array;

    public function unread(
        NotifiableInterface $notifiable,
    ): array;

    public function markAsRead(
        string $notificationId,
    ): void;

    public function markAllAsRead(
        NotifiableInterface $notifiable,
    ): void;

    public function delete(
        string $notificationId,
    ): void;

    public function deleteAll(
        NotifiableInterface $notifiable,
    ): void;

    public function unreadCount(
        NotifiableInterface $notifiable,
    ): int;
}
```

### Exception Hierarchy
```php
class NotificationException extends MarkoException
{
    public static function unknownChannel(string $channel): self;
    public static function noQueueAvailable(): self;
    public static function sendFailed(string $channel, string $reason): self;
}

class ChannelException extends NotificationException
{
    public static function routeMissing(string $channel, string $notifiableType): self;
    public static function deliveryFailed(string $channel, string $error): self;
}
```

### Module Bindings

**notification/module.php**
```php
return [
    'enabled' => true,
    'bindings' => [
        NotificationConfig::class => NotificationConfig::class,
        NotificationManager::class => NotificationManager::class,
        NotificationSender::class => NotificationSender::class,
    ],
    'boot' => function ($container) {
        $manager = $container->get(NotificationManager::class);

        // Register mail channel if mailer is available
        if ($container->has(MailerInterface::class)) {
            $manager->register('mail', $container->get(MailChannel::class));
        }

        // Register database channel if connection is available
        if ($container->has(ConnectionInterface::class)) {
            $manager->register('database', $container->get(DatabaseChannel::class));
        }
    },
];
```

**notification-database/module.php**
```php
return [
    'enabled' => true,
    'bindings' => [
        NotificationRepositoryInterface::class => DatabaseNotificationRepository::class,
    ],
];
```

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| **Mail package not installed** | MailChannel only registered when MailerInterface is available; loud error if notification declares 'mail' channel but MailerInterface not bound |
| **Database package not installed** | DatabaseChannel only registered when ConnectionInterface is available; loud error if notification declares 'database' channel but connection not bound |
| **Queue package not installed** | NotificationSender::queue() throws NotificationException with actionable message; send() works synchronously without queue |
| **Notification missing channel method** | ChannelException with clear message when toMail()/toDatabase() not implemented for declared channel |
| **Notifiable route missing** | ChannelException::routeMissing() with helpful suggestion to implement routeNotificationFor() |
| **Database notification data serialization** | Data stored as JSON; document that notification data must be JSON-serializable |
| **Multiple notifiables performance** | send() iterates notifiables; for large batches, recommend queue() for background processing |
