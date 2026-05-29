---
title: marko/mail-smtp
description: SMTP mail driver — delivers emails over SMTP with TLS, authentication, and MIME encoding.
---

SMTP mail driver --- delivers emails over SMTP with TLS, authentication, and MIME encoding. Handles TLS encryption, LOGIN/PLAIN authentication, multipart MIME messages (HTML + text + attachments), and inline images. Configuration comes from `config/mail.php` under the `smtp` key.

Implements `MailerInterface` from [`marko/mail`](/docs/packages/mail/).

## Installation

```bash
composer require marko/mail-smtp
```

This automatically installs `marko/mail`.

## Configuration

Set the mail driver to `smtp` in your config:

```php title="config/mail.php"
return [
    'driver' => 'smtp',
    'from' => [
        'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com',
        'name' => $_ENV['MAIL_FROM_NAME'] ?? 'My App',
    ],
    'smtp' => [
        'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
        'port' => (int) ($_ENV['MAIL_PORT'] ?? 587),
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'username' => $_ENV['MAIL_USERNAME'] ?? null,
        'password' => $_ENV['MAIL_PASSWORD'] ?? null,
        'auth_mode' => 'login', // 'login' or 'plain'
        'timeout' => 30,
    ],
];
```

| Option | Default | Description |
|---|---|---|
| `host` | `localhost` | SMTP server hostname |
| `port` | `587` | SMTP server port |
| `encryption` | `tls` | Encryption method --- `tls`, `ssl`, or `null` for none |
| `username` | `null` | SMTP authentication username |
| `password` | `null` | SMTP authentication password |
| `auth_mode` | `login` | Authentication mode --- `login` or `plain` |
| `timeout` | `30` | Connection timeout in seconds |

## Usage

### Automatic via Binding

Bind the mailer interface in your `module.php`:

```php title="module.php"
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Smtp\SmtpMailer;

return [
    'bindings' => [
        MailerInterface::class => SmtpMailer::class,
    ],
];
```

Then inject `MailerInterface` and send emails:

```php
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Message;

class OrderNotifier
{
    public function __construct(
        private MailerInterface $mailer,
    ) {}

    public function notifyShipment(
        string $email,
        string $trackingNumber,
    ): void {
        $message = Message::create()
            ->to($email)
            ->from('orders@example.com', 'Store')
            ->subject('Your order has shipped')
            ->html("<p>Tracking: $trackingNumber</p>");

        $this->mailer->send($message);
    }
}
```

## Customization

Extend `SmtpMailer` via Preference to customize message building:

```php
use Marko\Core\Attributes\Preference;
use Marko\Mail\Smtp\SmtpMailer;

#[Preference(replaces: SmtpMailer::class)]
class CustomSmtpMailer extends SmtpMailer
{
    // Custom SMTP behavior
}
```

## API Reference

### SmtpMailer

Implements `MailerInterface`. See [`marko/mail`](/docs/packages/mail/) for the full interface contract.

| Method | Description |
|---|---|
| `send(Message $message): bool` | Build and send a `Message` over SMTP --- handles headers, MIME encoding, and attachments |
| `sendRaw(string $to, string $raw): bool` | Send a pre-built raw message string to the given recipient |

### SmtpTransport

Low-level SMTP protocol transport --- manages the socket connection, TLS negotiation, authentication, and envelope commands.

| Method | Description |
|---|---|
| `connect(string $host, int $port, ?string $encryption = null): void` | Open a socket connection to the SMTP server |
| `ehlo(string $hostname): array` | Send the EHLO greeting and return server capabilities |
| `startTls(): void` | Upgrade the connection to TLS encryption |
| `authenticate(string $username, string $password, string $mode = 'LOGIN'): void` | Authenticate with the server using LOGIN or PLAIN |
| `mailFrom(string $address): void` | Set the envelope sender address |
| `rcptTo(string $address): void` | Add an envelope recipient address |
| `data(string $content): void` | Send the message content |
| `quit(): void` | Close the SMTP session and disconnect |

### SmtpConfig

Reads SMTP settings from the `smtp` key in `config/mail.php` via `MailConfig`.

| Method | Description |
|---|---|
| `host(): string` | SMTP server hostname (default: `localhost`) |
| `port(): int` | SMTP server port (default: `587`) |
| `encryption(): ?string` | Encryption method (default: `tls`) |
| `username(): ?string` | Authentication username |
| `password(): ?string` | Authentication password |
| `timeout(): int` | Connection timeout in seconds (default: `30`) |
| `authMode(): ?string` | Authentication mode (default: `login`) |
