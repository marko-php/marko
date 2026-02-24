# Task 003: FakeMailer

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create a `FakeMailer` that implements `MailerInterface` from `marko/mail`. It captures all sent messages in memory and provides assertion methods for verifying emails were sent in tests.

## Context
- Related files:
  - `packages/mail/src/Contracts/MailerInterface.php` - interface to implement (2 methods: `send(Message $message): bool`, `sendRaw(string $to, string $raw): bool`)
  - `packages/mail/src/Message.php` - Message class with `$to`, `$subject`, `$from` etc.
- Location: `packages/testing/src/Fake/FakeMailer.php`

## Requirements (Test Descriptions)
- [ ] `it implements MailerInterface`
- [ ] `it captures sent messages in memory and returns true`
- [ ] `it captures raw messages in memory and returns true`
- [ ] `it returns all sent messages`
- [ ] `it asserts message was sent`
- [ ] `it asserts message was sent matching a callback filter`
- [ ] `it throws AssertionFailedException when asserting sent message that was not sent`
- [ ] `it asserts no messages were sent`
- [ ] `it asserts sent count`
- [ ] `it clears all captured messages`

## Acceptance Criteria
- All requirements have passing tests
- Implements `MailerInterface` from `marko/mail`
- Captures both `send()` and `sendRaw()` calls
- Callback filter allows flexible matching (e.g., by recipient, subject)
- Code follows all code standards

## Implementation Notes
### Public API
```php
class FakeMailer implements MailerInterface
{
    /** @var array<Message> */
    public private(set) array $sent = [];

    /** @var array<array{to: string, raw: string}> */
    public private(set) array $sentRaw = [];

    public function send(Message $message): bool;
    public function sendRaw(string $to, string $raw): bool;
    public function assertSent(?callable $callback = null): void;
    public function assertNotSent(?callable $callback = null): void;
    public function assertSentCount(int $expected): void;
    public function assertNothingSent(): void;
    public function clear(): void;
}
```

The callback filter receives a `Message` and returns bool:
```php
$mailer->assertSent(fn (Message $m) => $m->subject === 'Welcome');
```
