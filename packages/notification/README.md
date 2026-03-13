# marko/notification

Notification system contracts and channels--send notifications to users via mail, database, or custom channels from a single API.

## Installation

```bash
composer require marko/notification
```

## Quick Example

```php
use Marko\Notification\NotificationSender;

// Inject the sender and dispatch a notification
$notificationSender->send($user, new OrderShippedNotification($trackingNumber));

// Or queue for background delivery
$notificationSender->queue($user, new OrderShippedNotification($trackingNumber));
```

## Documentation

Full usage, API reference, and examples: [marko/notification](https://marko.build/docs/packages/notification/)
