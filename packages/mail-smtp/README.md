# Marko Mail SMTP

SMTP mail driver--delivers emails over SMTP with TLS, authentication, and MIME encoding.

## Overview

This package implements `MailerInterface` by sending emails through an SMTP server. It handles TLS encryption, LOGIN/PLAIN authentication, multipart MIME messages (HTML + text + attachments), and inline images. Configuration comes from `config/mail.php` under the `smtp` key.

## Installation

```bash
composer require marko/mail-smtp
```

## Usage

### Automatic via Binding

Bind the mailer interface in your `module.php`:

```php
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

### Configuration

Configure SMTP settings in `config/mail.php`:

```php
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

Implements `MailerInterface`. See `marko/mail` for the interface contract.

```php
public function send(Message $message): bool;
public function sendRaw(string $to, string $raw): bool;
```

### SmtpTransport

```php
public function connect(string $host, int $port, ?string $encryption = null): void;
public function ehlo(string $hostname): array;
public function startTls(): void;
public function authenticate(string $username, string $password, string $mode = 'LOGIN'): void;
public function mailFrom(string $address): void;
public function rcptTo(string $address): void;
public function data(string $content): void;
public function quit(): void;
```
