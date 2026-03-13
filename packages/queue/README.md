# marko/queue

Queue interfaces and worker infrastructure -- defines how jobs are dispatched and processed, not which backend stores them.

## Installation

```bash
composer require marko/queue
```

Note: You also need a driver package. See `marko/queue-sync`, `marko/queue-database`, or `marko/queue-rabbitmq`.

## Quick Example

```php
use Marko\Queue\Job;
use Marko\Queue\QueueInterface;

class SendWelcomeEmail extends Job
{
    public function __construct(
        private readonly string $email,
    ) {}

    public function handle(): void
    {
        // Send the email...
    }
}

// Dispatch via QueueInterface
$queue->push(new SendWelcomeEmail('user@example.com'));
```

## Documentation

Full usage, API reference, and examples: [marko/queue](https://marko.build/docs/packages/queue/)
