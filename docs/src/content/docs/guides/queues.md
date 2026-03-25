---
title: Queues
description: Process jobs in the background with pluggable queue backends.
---

Marko's queue system lets you defer work to background processes. Jobs are dispatched through `QueueInterface` and processed by a worker, with pluggable backends for different environments.

## Setup

```bash
# Database-backed queue
composer require marko/queue marko/queue-database

# RabbitMQ queue
composer require marko/queue marko/queue-rabbitmq
```

## Creating Jobs

```php title="app/blog/Job/SendWelcomeEmail.php"
<?php

declare(strict_types=1);

namespace App\Blog\Job;

use Marko\Queue\Job;

class SendWelcomeEmail extends Job
{
    public function __construct(
        private readonly string $email,
        private readonly string $name,
    ) {}

    public function handle(): void
    {
        // Send the email — this runs in the background
    }
}
```

## Dispatching Jobs

```php title="app/blog/Service/RegistrationService.php"
<?php

declare(strict_types=1);

use Marko\Queue\QueueInterface;
use App\Blog\Job\SendWelcomeEmail;

class RegistrationService
{
    public function __construct(
        private readonly QueueInterface $queue,
    ) {}

    public function register(string $email, string $name): void
    {
        // ... create user

        $this->queue->push(new SendWelcomeEmail(
            email: $email,
            name: $name,
        ));
    }
}
```

## Processing Jobs

```bash
marko queue:work
```

## Available Backends

| Package | Backend | Best For |
|---|---|---|
| `marko/queue-sync` | Synchronous (inline) | Development, testing |
| `marko/queue-database` | Database table | Small apps, getting started |
| `marko/queue-rabbitmq` | RabbitMQ | Production, high throughput |

## Next Steps

- [Mail](/docs/guides/mail/) — queue email sending
- [Error Handling](/docs/guides/error-handling/) — handle failed jobs
- [Queue package reference](/docs/packages/queue/) — full API details
