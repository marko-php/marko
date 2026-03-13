# marko/queue-database

Database queue driver — stores and processes jobs in SQL tables with transaction-safe polling and failed job persistence.

## Installation

```bash
composer require marko/queue-database
```

## Quick Example

```php
use Marko\Queue\QueueInterface;

public function __construct(
    private readonly QueueInterface $queue,
) {}

public function enqueue(): void
{
    $this->queue->push(new ProcessOrder($orderId));

    // Delay by 5 minutes
    $this->queue->later(300, new SendFollowUp($orderId));
}
```

## Documentation

Full usage, API reference, and examples: [marko/queue-database](https://marko.build/docs/packages/queue-database/)
