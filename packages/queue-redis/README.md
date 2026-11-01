# marko/queue-redis

Redis queue driver --- reliable Lua-based pops, delayed jobs, crash recovery and a Redis failed-job store on the Redis you already run.

## Installation

```bash
composer require marko/queue-redis
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

Full usage, API reference, and examples: [marko/queue-redis](https://marko.build/docs/packages/queue-redis/)
