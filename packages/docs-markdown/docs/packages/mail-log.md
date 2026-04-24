---
title: marko/mail-log
description: Log-based mail driver — writes emails to the log instead of sending them, ideal for development and testing.
---

Log-based mail driver --- writes emails to the log instead of sending them, ideal for development and testing. Implements `MailerInterface` from [`marko/mail`](/docs/packages/mail/) by writing email details to the logger rather than delivering them. Message metadata (from, to, subject, attachment count) is logged at `info` level; full HTML/text bodies are logged at `debug` level. Every `send()` call returns `true` since no delivery can fail.

## Installation

```bash
composer require marko/mail-log
```

This automatically installs [`marko/mail`](/docs/packages/mail/) and [`marko/log`](/docs/packages/log/).

## Usage

### Automatic via Binding

Bind the mailer interface in your `module.php` for development:

```php title="module.php"
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Log\LogMailer;

return [
    'bindings' => [
        MailerInterface::class => LogMailer::class,
    ],
];
```

Or conditionally bind for development only:

```php title="module.php"
use Marko\Container\Contracts\ContainerInterface;
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Log\LogMailer;
use Marko\Mail\Smtp\SmtpMailer;

return [
    'bindings' => [
        MailerInterface::class => SmtpMailer::class,
    ],
    'boot' => function (ContainerInterface $container): void {
        if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
            $container->bind(
                MailerInterface::class,
                LogMailer::class,
            );
        }
    },
];
```

### What Gets Logged

When you send a message, the log output includes:

```
[2025-01-15 10:30:00] app.INFO: Email sent {"from":"noreply@example.com","to":["user@example.com"],"subject":"Welcome!","has_html":true,"has_text":false,"attachment_count":0}
[2025-01-15 10:30:00] app.DEBUG: Email body (html) {"body":"<h1>Welcome!</h1>"}
```

The `info`-level entry captures metadata --- from address, recipients, subject, whether HTML/text bodies are present, and attachment count. If the message includes CC, BCC, or attachments, those details are included as well (attachment name, size, and MIME type). The `debug`-level entries log the full message body for each content type (text and/or HTML).

For `sendRaw()`, the `info` entry logs the recipient and raw content length, while the `debug` entry logs the full raw content.

Your code stays the same regardless of driver:

```php
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Message;

class WelcomeMailer
{
    public function __construct(
        private MailerInterface $mailer,
    ) {}

    public function sendWelcome(
        string $email,
    ): void {
        $message = Message::create()
            ->to($email)
            ->from('noreply@example.com')
            ->subject('Welcome!')
            ->html('<h1>Welcome!</h1>');

        $this->mailer->send($message);
    }
}
```

## API Reference

### LogMailer

Implements `MailerInterface`. See [`marko/mail`](/docs/packages/mail/) for the full interface contract.

| Method | Description |
|---|---|
| `send(Message $message): bool` | Log message metadata at `info` level and body at `debug` level; always returns `true` |
| `sendRaw(string $to, string $raw): bool` | Log recipient and raw content length at `info` level, full content at `debug` level; always returns `true` |

The constructor accepts a single dependency:

```php
use Marko\Log\Contracts\LoggerInterface;

public function __construct(
    private LoggerInterface $logger,
) {}
```
