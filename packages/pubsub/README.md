# marko/pubsub

Real-time publish/subscribe messaging contracts — type-hint against a stable interface and swap drivers without changing application code.

## Installation

```bash
composer require marko/pubsub
```

## Quick Example

```php
use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;

$publisher->publish(
    channel: 'orders',
    message: new Message(
        channel: 'orders',
        payload: json_encode(['id' => $order->id, 'status' => 'placed']),
    ),
);
```

## Documentation

Full usage, API reference, and examples: [marko/pubsub](https://marko.build/docs/packages/pubsub/)
