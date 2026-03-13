---
title: Mail
description: Send emails with pluggable transports — SMTP, log, or custom.
---

Marko's mail system provides a clean interface for sending emails with pluggable transports. Code against `MailerInterface` and swap between SMTP, log, or custom transports by changing a single binding.

## Setup

```bash
composer require marko/mail marko/mail-smtp
```

## Sending Mail

```php title="app/blog/Service/NotificationService.php"
<?php

declare(strict_types=1);

namespace App\Blog\Service;

use Marko\Mail\MailerInterface;
use Marko\Mail\Message;

class NotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {}

    public function notifyAuthor(string $email, string $postTitle): void
    {
        $message = new Message(
            to: $email,
            subject: "Your post '{$postTitle}' was published",
            body: "Congratulations! Your post is now live.",
        );

        $this->mailer->send($message);
    }
}
```

## Available Transports

| Package | Transport | Best For |
|---|---|---|
| `marko/mail-smtp` | SMTP server | Production |
| `marko/mail-log` | Log file | Development, testing |

## Switching Transports

```php title="module.php"
use Marko\Mail\TransportInterface;
use Marko\Mail\Log\LogTransport;

return [
    'bindings' => [
        TransportInterface::class => LogTransport::class,
    ],
];
```

## Next Steps

- [Queues](/docs/guides/queues/) — send mail in the background
- [Events](/docs/concepts/events/) — trigger mail from events
- [Mail package reference](/docs/packages/mail/) — full API details
