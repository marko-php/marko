# marko/mail-log

Log-based mail driver--writes emails to the log instead of sending them, ideal for development and testing.

## Installation

```bash
composer require marko/mail-log
```

## Quick Example

```php
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Log\LogMailer;

return [
    'bindings' => [
        MailerInterface::class => LogMailer::class,
    ],
];
```

## Documentation

Full usage, API reference, and examples: [marko/mail-log](https://marko.build/docs/packages/mail-log/)
