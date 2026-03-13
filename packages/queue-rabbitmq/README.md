# marko/queue-rabbitmq

RabbitMQ queue driver--processes jobs through AMQP with persistent messages, exchange routing, and delayed delivery.

## Installation

```bash
composer require marko/queue-rabbitmq
```

## Quick Example

```php
use Marko\Queue\QueueInterface;

public function __construct(
    private readonly QueueInterface $queue,
) {}

public function dispatch(): void
{
    $this->queue->push(new ProcessPayment($orderId));

    // Delay by 30 seconds using dead-letter exchange
    $this->queue->later(30, new SendReceipt($orderId));
}
```

## Documentation

Full usage, API reference, and examples: [marko/queue-rabbitmq](https://marko.build/docs/packages/queue-rabbitmq/)
