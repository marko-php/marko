# marko/mail-smtp

SMTP mail driver --- delivers emails over SMTP with TLS, authentication, and MIME encoding.

## Installation

```bash
composer require marko/mail-smtp
```

## Quick Example

```php
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Message;

class OrderNotifier
{
    public function __construct(
        private MailerInterface $mailer,
    ) {}

    public function notifyShipment(string $email, string $trackingNumber): void
    {
        $message = Message::create()
            ->to($email)
            ->from('orders@example.com', 'Store')
            ->subject('Your order has shipped')
            ->html("<p>Tracking: $trackingNumber</p>");

        $this->mailer->send($message);
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/mail-smtp](https://marko.build/docs/packages/mail-smtp/)
