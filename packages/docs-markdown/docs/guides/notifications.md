---
title: Notifications
description: Send notifications to users via mail, database, or custom channels --- and manage stored notifications.
---

Marko's notification system lets you send messages to users through multiple channels --- mail, database, or custom --- from a single, unified API. Define a notification once and deliver it to whichever channels each recipient needs. This guide covers setup, sending, database storage, custom channels, and testing.

## Setup

Install the core notification package:

```bash
composer require marko/notification
```

To persist notifications in the database, also install the database storage package:

```bash
composer require marko/notification-database
```

For background delivery, install a queue driver such as [marko/queue-database](/docs/packages/queue-database/) or [marko/queue-sync](/docs/packages/queue-sync/).

## Creating Notifications

A notification implements `NotificationInterface`, declaring which channels it supports and how to format the message for each:

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

The `channels()` method receives the notifiable, so you can vary channels per recipient --- for example, only sending mail to users who have opted in.

## Making Entities Notifiable

Any entity that receives notifications implements `NotifiableInterface`:

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

The `routeNotificationFor()` method returns routing information for each channel --- an email address for the mail channel, or `null` for channels that don't need explicit routing (like database).

## Sending Notifications

Inject `NotificationSenderInterface` and call `send()`:

```php
use Marko\Notification\Contracts\NotificationSenderInterface;

class OrderService
{
    public function __construct(
        private NotificationSenderInterface $notificationSender,
    ) {}

    public function shipOrder(
        User $user,
        string $trackingNumber,
    ): void {
        $this->notificationSender->send(
            $user,
            new OrderShippedNotification($trackingNumber),
        );
    }
}
```

### Multiple Recipients

Pass an array of notifiables to send the same notification to several users at once:

```php
$this->notificationSender->send(
    [$user1, $user2],
    new OrderShippedNotification($trackingNumber),
);
```

### Queued Delivery

Queue notifications for background processing instead of sending inline. This requires a [queue driver](/docs/packages/queue/):

```php
$this->notificationSender->queue(
    $user,
    new OrderShippedNotification($trackingNumber),
);
```

If no queue implementation is available, `queue()` throws a `NotificationException` with a suggestion to install a queue driver.

## Database Notifications

When the `database` channel is used, notifications are persisted to a `notifications` table. The `marko/notification-database` package provides a repository for querying and managing them.

### Querying Notifications

Inject `NotificationRepositoryInterface` to fetch notifications for a user:

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

### Reading Notification Data

Each `DatabaseNotification` stores its payload as JSON. Decode it to access the original data from `toDatabase()`:

```php
use Marko\Notification\Database\Repository\NotificationRepositoryInterface;

$unread = $this->notificationRepository->unread($user);

foreach ($unread as $notification) {
    $data = json_decode($notification->data, true);
    // $data['title'], $data['tracking_number'], etc.
}
```

### Marking as Read

Mark individual notifications or all at once:

```php
// Mark one of the user's notifications as read (false: not found or not theirs)
$this->notificationRepository->markAsReadFor($user, $notificationId);

// Mark all notifications as read for a user
$this->notificationRepository->markAllAsRead($user);
```

### Deleting Notifications

```php
// Delete one of the user's notifications (false: not found or not theirs)
$this->notificationRepository->deleteFor($user, $notificationId);

// Delete all notifications for a user
$this->notificationRepository->deleteAll($user);
```

When the notification ID comes from a request, always use the owner-scoped `markAsReadFor()` and `deleteFor()`. The unscoped `markAsRead($id)` and `delete($id)` act on any notification by ID and are meant for admin and internal code. See the [notification-database docs](/docs/packages/notification-database/) for details.

## Custom Channels

Create a custom channel by implementing `ChannelInterface`, then register it with `NotificationManager`:

```php
use Marko\Notification\Contracts\ChannelInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Notification\Exceptions\ChannelException;

class SmsChannel implements ChannelInterface
{
    public function __construct(
        private SmsClient $smsClient,
    ) {}

    public function send(
        NotifiableInterface $notifiable,
        NotificationInterface $notification,
    ): void {
        $phone = $notifiable->routeNotificationFor('sms');

        if ($phone === null || $phone === '') {
            throw ChannelException::routeMissing('sms', $notifiable->getNotifiableType());
        }

        // Send via your SMS provider
        $this->smsClient->send($phone, $notification->toSms($notifiable));
    }
}
```

Register the channel during module boot:

```php
use Marko\Notification\NotificationManager;

$notificationManager->register('sms', new SmsChannel($smsClient));
```

Then reference `'sms'` in any notification's `channels()` method.

## Testing

To check which notifications your code sends without delivering them, bind `FakeNotificationSender` from [marko/testing](/docs/packages/testing/) to `NotificationSenderInterface`. It records the channels a real send would use, keeps sent and queued apart, and matches notifiables by type and id:

```php
use Marko\Notification\Contracts\NotificationSenderInterface;
use Marko\Testing\Fake\FakeNotificationSender;

test('it notifies the customer when an order ships', function (): void {
    $notifications = new FakeNotificationSender();
    $container->instance(NotificationSenderInterface::class, $notifications);

    $container->get(OrderService::class)->shipOrder($user, 'TRACK-123');

    $notifications->assertSentTo(
        $user,
        OrderShippedNotification::class,
        fn (OrderShippedNotification $n, array $channels) => in_array('mail', $channels, true),
    );
});
```

To test the delivery itself, mock `ChannelInterface` and wire a real `NotificationManager`:

```php
use Marko\Notification\Contracts\ChannelInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Notification\NotificationManager;
use Marko\Notification\NotificationSender;

test('it sends order shipped notification via mail', function (): void {
    $notifiable = $this->createMock(NotifiableInterface::class);

    $mailChannel = $this->createMock(ChannelInterface::class);
    $mailChannel->expects($this->once())
        ->method('send')
        ->with($notifiable, $this->isInstanceOf(NotificationInterface::class));

    $manager = new NotificationManager();
    $manager->register('mail', $mailChannel);

    $sender = new NotificationSender($manager);
    $sender->send($notifiable, new OrderShippedNotification('TRACK-123'));
});
```

To test queued notifications, mock `QueueInterface` and verify the job is pushed:

```php
use Marko\Notification\Job\SendNotificationJob;
use Marko\Notification\NotificationManager;
use Marko\Notification\NotificationSender;
use Marko\Queue\QueueInterface;

test('it queues notification for background delivery', function (): void {
    $notifiable = $this->createMock(NotifiableInterface::class);

    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->once())
        ->method('push')
        ->with($this->isInstanceOf(SendNotificationJob::class));

    $manager = new NotificationManager();
    $sender = new NotificationSender($manager, $queue);

    $sender->queue($notifiable, new OrderShippedNotification('TRACK-456'));
});
```

## Related Links

- [marko/notification](/docs/packages/notification/) --- full API reference for the core notification package
- [marko/notification-database](/docs/packages/notification-database/) --- database storage API and entity reference
- [Mail guide](/docs/guides/mail/) --- sending standalone emails
- [Queues guide](/docs/guides/queues/) --- background job processing
