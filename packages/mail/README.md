# marko/mail

Mail contracts and message building -- compose emails with a fluent API and send them through any mail driver.

## Installation

```bash
composer require marko/mail
```

Note: You also need an implementation package such as `marko/mail-smtp` or `marko/mail-log`.

## Quick Example

```php
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Message;

$message = Message::create()
    ->to('user@example.com', 'Jane Doe')
    ->from('hello@example.com', 'My App')
    ->subject('Welcome!')
    ->html('<h1>Welcome!</h1>')
    ->text('Welcome!');

$mailer->send($message);
```

## Documentation

Full usage, API reference, and examples: [marko/mail](https://marko.build/docs/packages/mail/)
