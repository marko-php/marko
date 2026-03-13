# marko/queue-sync

Synchronous queue driver — executes jobs immediately during the current request, ideal for development and testing.

## Installation

```bash
composer require marko/queue-sync
```

## Quick Example

```php
use Marko\Queue\QueueInterface;

public function __construct(
    private readonly QueueInterface $queue,
) {}

public function process(): void
{
    // Executes immediately, throws on failure
    $this->queue->push(new SendWelcomeEmail('user@example.com'));
}
```

## Documentation

Full usage, API reference, and examples: [marko/queue-sync](https://marko.build/docs/packages/queue-sync/)
