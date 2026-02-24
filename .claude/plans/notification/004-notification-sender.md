# Task 004: NotificationSender

**Status**: done
**Depends on**: 001, 002
**Retry count**: 0

## Description
Implement the NotificationSender that orchestrates sending notifications across multiple channels. It resolves the channels each notification should be sent on (via `channels()` on the notification), dispatches to each channel through the NotificationManager, and optionally supports queued sending via marko/queue.

## Context
- Namespace: `Marko\Notification\`
- Location: `src/NotificationSender.php`
- Dependencies: NotificationManager (required), QueueInterface (optional)
- The QueueInterface is nullable -- when not available, `queue()` throws a loud error and `send()` always runs synchronously

### Behavior: send()
1. Normalize notifiables to array (accept single or array)
2. For each notifiable, call `$notification->channels($notifiable)` to get channel list
3. For each channel name, resolve via NotificationManager::channel()
4. Call `$channel->send($notifiable, $notification)` for each

### Behavior: queue()
1. If QueueInterface is null, throw NotificationException::noQueueAvailable()
2. Create a `SendNotificationJob` wrapping the notifiable(s) and notification
3. Push job to queue

### SendNotificationJob
An inner job class (or nested in the notification package) that wraps the notification send operation for queue processing. This is a simple Job subclass that calls NotificationSender::send() in its handle() method.

Location: `src/Job/SendNotificationJob.php`

### Class Design
```php
class NotificationSender
{
    public function __construct(
        private NotificationManager $manager,
        private ?QueueInterface $queue = null,
    ) {}

    public function send(
        NotifiableInterface|array $notifiables,
        NotificationInterface $notification,
    ): void;

    public function queue(
        NotifiableInterface|array $notifiables,
        NotificationInterface $notification,
    ): void;
}
```

## Requirements (Test Descriptions)
- [ ] `it sends notification to single notifiable across declared channels`
- [ ] `it sends notification to multiple notifiables`
- [ ] `it resolves channels from notification for each notifiable`
- [ ] `it throws NotificationException when notification declares unknown channel`
- [ ] `it throws NotificationException when queue is not available and queue() is called`
- [ ] `it queues notification via QueueInterface when available`
- [ ] `it wraps channel delivery failures in NotificationException`

## Acceptance Criteria
- NotificationSender accepts single NotifiableInterface or array of them
- For each notifiable, calls notification->channels() to determine which channels to use
- Resolves each channel from NotificationManager
- Calls channel->send() for each channel/notifiable pair
- queue() pushes a SendNotificationJob when QueueInterface is available
- queue() throws NotificationException::noQueueAvailable() when QueueInterface is null
- ChannelException from channel delivery is allowed to propagate (or wrapped in NotificationException::sendFailed())
- SendNotificationJob exists and is serializable
- Strict types, @throws PHPDoc tags

## Implementation Notes
(Left blank)
