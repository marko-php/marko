# Task 003: MailChannel

**Status**: done
**Depends on**: 001
**Retry count**: 0

## Description
Implement the MailChannel that sends notifications via marko/mail's MailerInterface. The channel calls `toMail()` on the notification to get a Message, resolves the recipient email from the notifiable, and sends via the mailer.

## Context
- Namespace: `Marko\Notification\Channel\`
- Location: `src/Channel/MailChannel.php`
- Dependencies: `Marko\Mail\Contracts\MailerInterface`, `Marko\Mail\Message`
- Reference: packages/mail/src/Contracts/MailerInterface.php for the send contract
- marko/mail is a `suggest` dependency in composer.json (not `require`) since MailChannel is only used when mail is available

### Behavior
1. Call `$notification->toMail($notifiable)` to get a `Message` instance
2. If the Message has no `to` recipients, resolve via `$notifiable->routeNotificationFor('mail')`
3. If route is null/empty, throw `ChannelException::routeMissing('mail', ...)`
4. Send the message via `$this->mailer->send($message)`
5. Wrap any `TransportException` in a `ChannelException::deliveryFailed()`

### Class Design
```php
class MailChannel implements ChannelInterface
{
    public function __construct(
        private MailerInterface $mailer,
    ) {}

    public function send(
        NotifiableInterface $notifiable,
        NotificationInterface $notification,
    ): void;
}
```

## Requirements (Test Descriptions)
- [ ] `it implements ChannelInterface`
- [ ] `it sends notification mail message via mailer`
- [ ] `it resolves recipient from notifiable when message has no to address`
- [ ] `it uses message to address when already set`
- [ ] `it throws ChannelException when notifiable has no mail route`
- [ ] `it throws ChannelException when mailer transport fails`

## Acceptance Criteria
- MailChannel implements ChannelInterface
- Constructor accepts MailerInterface via injection
- Calls toMail() on the notification to get the Message
- Adds notifiable's mail route as recipient when Message has no `to` set
- Throws ChannelException::routeMissing() when routeNotificationFor('mail') returns null/empty
- Wraps TransportException in ChannelException::deliveryFailed()
- Strict types, @throws PHPDoc tags

## Implementation Notes
(Left blank)
