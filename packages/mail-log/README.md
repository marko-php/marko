# Marko Mail Log

Log-based mail driver--writes emails to the log instead of sending them, ideal for development and testing.

## Overview

This package implements `MailerInterface` by writing email details to the logger rather than delivering them. Message metadata (from, to, subject, attachment count) is logged at `info` level; full HTML/text bodies are logged at `debug` level. Every `send()` call returns `true` since no delivery can fail.

## Installation

```bash
composer require marko/mail-log
```

## Usage

### Automatic via Binding

Bind the mailer interface in your `module.php` for development:

```php
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Log\LogMailer;

return [
    'bindings' => [
        MailerInterface::class => LogMailer::class,
    ],
];
```

Or conditionally bind for development only:

```php
return [
    'bindings' => [
        MailerInterface::class => SmtpMailer::class,
    ],
    'boot' => function ($container) {
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

Implements `MailerInterface`. See `marko/mail` for the interface contract.

```php
public function send(Message $message): bool;
public function sendRaw(string $to, string $raw): bool;
```
