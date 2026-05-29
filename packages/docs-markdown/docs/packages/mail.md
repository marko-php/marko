---
title: marko/mail
description: Mail contracts and message building — compose emails with a fluent API and send them through any mail driver.
---

Mail contracts and message building --- compose emails with a fluent API and send them through any mail driver. This package provides the `MailerInterface`, the fluent `Message` builder, `Address` and `Attachment` value objects, and an optional `ViewMailer` decorator for template rendering. It contains no transport implementation; install a driver like `marko/mail-smtp` or `marko/mail-log` for actual delivery. Includes a `mail:test` CLI command for verifying configuration.

**This package defines contracts only.** Install a driver for implementation:

- `marko/mail-smtp` --- SMTP transport
- `marko/mail-log` --- Log-based transport (development/testing)

## Installation

```bash
composer require marko/mail
```

Note: You also need an implementation package such as `marko/mail-smtp` or `marko/mail-log`.

## Usage

### Building and Sending Messages

Inject the mailer and build messages with the fluent API:

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
        string $name,
    ): void {
        $message = Message::create()
            ->to($email, $name)
            ->from('hello@example.com', 'My App')
            ->subject('Welcome!')
            ->html('<h1>Welcome, ' . $name . '!</h1>')
            ->text('Welcome, ' . $name . '!');

        $this->mailer->send($message);
    }
}
```

### Attachments

Attach files from disk or embed inline images:

```php
use Marko\Mail\Message;

$message = Message::create()
    ->to('user@example.com')
    ->from('noreply@example.com')
    ->subject('Your Invoice')
    ->html('<p>Please find your invoice attached.</p>')
    ->attach('/path/to/invoice.pdf')
    ->embed('/path/to/logo.png', 'logo-id');
```

### Template-Based Emails

When `marko/view` is installed, use the `ViewMailer` to render templates:

```php
use Marko\Mail\Message;

$message = Message::create()
    ->to('user@example.com')
    ->from('noreply@example.com')
    ->subject('Order Confirmation')
    ->view('emails/order-confirmation')
    ->with(['order' => $order]);
```

### Configuration

The `MailConfig` class provides typed access to mail configuration values:

```php
use Marko\Mail\Config\MailConfig;

class MyService
{
    public function __construct(
        private MailConfig $mailConfig,
    ) {}

    public function setup(): void
    {
        $driver = $this->mailConfig->driver();
        $fromAddress = $this->mailConfig->fromAddress();
        $fromName = $this->mailConfig->fromName();
        $driverConfig = $this->mailConfig->driverConfig('smtp');
    }
}
```

## CLI Commands

| Command | Description |
|---------|-------------|
| `marko mail:test <email>` | Send a test email to verify mail configuration |
| `marko mail:test <email> --subject="Custom Subject"` | Send a test email with a custom subject |

## API Reference

### MailerInterface

```php
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Message;

public function send(Message $message): bool;
public function sendRaw(string $to, string $raw): bool;
```

Both methods throw `TransportException` on delivery failure.

### Message

```php
use Marko\Mail\Message;

public static function create(): self;
public function to(string $email, ?string $name = null): self;
public function cc(string $email, ?string $name = null): self;
public function bcc(string $email, ?string $name = null): self;
public function from(string $email, ?string $name = null): self;
public function replyTo(string $email, ?string $name = null): self;
public function subject(string $subject): self;
public function html(string $html): self;
public function text(string $text): self;
public function attach(string $path, ?string $name = null, ?string $mimeType = null): self;
public function embed(string $path, string $contentId, ?string $name = null, ?string $mimeType = null): self;
public function header(string $name, string $value): self;
public function priority(int $priority): self;
public function view(string $template): self;
public function with(array|string $key, mixed $value = null): self;
```

### Address

```php
use Marko\Mail\Address;

readonly class Address
{
    public string $email;
    public ?string $name;

    public function toString(): string;
}
```

### Attachment

```php
use Marko\Mail\Attachment;

public static function fromPath(string $path, ?string $name = null, ?string $mimeType = null): self;
public static function fromContent(string $content, string $name, string $mimeType = 'application/octet-stream'): self;
public static function inline(string $path, string $contentId, ?string $name = null, ?string $mimeType = null): self;
```

### MailConfig

```php
use Marko\Mail\Config\MailConfig;

public function ensureConfigExists(): void;
public function driver(): string;
public function fromAddress(): string;
public function fromName(): string;
public function driverConfig(string $driver): array;
```

### Exceptions

| Exception | Description |
|-----------|-------------|
| `MailException` | Base exception for all mail errors --- includes `getContext()` and `getSuggestion()` methods |
| `MessageException` | Thrown for invalid email addresses, missing attachments, or no recipients |
| `TransportException` | Thrown on delivery failures --- connection errors, TLS failures, authentication failures, unexpected SMTP responses |
