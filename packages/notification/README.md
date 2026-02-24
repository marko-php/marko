# Marko Notification

Notification system contracts and channels--send notifications to users via mail, database, or custom channels from a single API.

## Overview

This package provides the core notification infrastructure: `NotificationInterface` for defining notifications, `NotifiableInterface` for entities that receive them, `ChannelInterface` for delivery mechanisms, and `NotificationSender` for dispatching. Built-in channels include mail and database. Notifications can also be queued for background delivery when `marko/queue` is installed.

## Installation

```bash
composer require marko/notification
```

## Usage

### Defining a Notification

Implement `NotificationInterface` to define what channels to use and how to format the notification for each:

```php
use Marko\Mail\Message;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;

class OrderShippedNotification implements NotificationInterface
{
    public function __construct(
        private string $trackingNumber,
    ) {}

    public function channels(
        NotifiableInterface $notifiable,
    ): array {
        return ['mail', 'database'];
    }

    public function toMail(
        NotifiableInterface $notifiable,
    ): Message {
        return Message::create()
            ->subject('Your order has shipped')
            ->html("<p>Tracking: $this->trackingNumber</p>");
    }

    public function toDatabase(
        NotifiableInterface $notifiable,
    ): array {
        return [
            'title' => 'Order Shipped',
            'tracking_number' => $this->trackingNumber,
        ];
    }
}
```

### Making an Entity Notifiable

Implement `NotifiableInterface` on any entity that should receive notifications:

```php
use Marko\Notification\Contracts\NotifiableInterface;

class User implements NotifiableInterface
{
    public function __construct(
        private int $id,
        private string $email,
    ) {}

    public function routeNotificationFor(
        string $channel,
    ): mixed {
        return match ($channel) {
            'mail' => $this->email,
            default => null,
        };
    }

    public function getNotifiableId(): string|int
    {
        return $this->id;
    }

    public function getNotifiableType(): string
    {
        return self::class;
    }
}
```

### Sending Notifications

Inject `NotificationSender` and dispatch:

```php
use Marko\Notification\NotificationSender;

class OrderService
{
    public function __construct(
        private NotificationSender $notifications,
    ) {}

    public function shipOrder(
        User $user,
        string $trackingNumber,
    ): void {
        $this->notifications->send(
            $user,
            new OrderShippedNotification($trackingNumber),
        );
    }
}
```

### Sending to Multiple Recipients

Pass an array of notifiables:

```php
$this->notifications->send(
    [$user1, $user2],
    new OrderShippedNotification($trackingNumber),
);
```

### Queuing Notifications

Queue for background delivery (requires `marko/queue`):

```php
$this->notifications->queue(
    $user,
    new OrderShippedNotification($trackingNumber),
);
```

### Registering Custom Channels

Register channels via `NotificationManager`:

```php
use Marko\Notification\NotificationManager;

$manager->register('sms', new SmsChannel($smsClient));
```

## Customization

Replace `NotificationSender` via Preference to add custom behavior like logging or rate limiting:

```php
use Marko\Core\Attributes\Preference;
use Marko\Notification\NotificationSender;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;

#[Preference(replaces: NotificationSender::class)]
class LoggingNotificationSender extends NotificationSender
{
    public function send(
        NotifiableInterface|array $notifiables,
        NotificationInterface $notification,
    ): void {
        // Custom pre-send logic
        parent::send($notifiables, $notification);
    }
}
```

## API Reference

### NotificationSender

```php
public function send(NotifiableInterface|array $notifiables, NotificationInterface $notification): void;
public function queue(NotifiableInterface|array $notifiables, NotificationInterface $notification): void;
```

### NotificationManager

```php
public function register(string $name, ChannelInterface $channel): void;
public function channel(string $name): ChannelInterface;
public function hasChannel(string $name): bool;
public function getRegisteredChannels(): array;
```

### NotificationInterface

```php
interface NotificationInterface
{
    public function channels(NotifiableInterface $notifiable): array;
    public function toMail(NotifiableInterface $notifiable): Message;
    public function toDatabase(NotifiableInterface $notifiable): array;
}
```

### NotifiableInterface

```php
interface NotifiableInterface
{
    public function routeNotificationFor(string $channel): mixed;
    public function getNotifiableId(): string|int;
    public function getNotifiableType(): string;
}
```

### ChannelInterface

```php
interface ChannelInterface
{
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void;
}
```
