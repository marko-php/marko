# marko/pubsub-pgsql

Zero-infrastructure pub/sub via PostgreSQL LISTEN/NOTIFY -- real-time messaging using the database you already have, no Redis required.

## Installation

```bash
composer require marko/pubsub-pgsql
```

This automatically installs `marko/pubsub` and `marko/amphp`.

## Quick Example

```php
use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;

// Publishing -- issues a PostgreSQL NOTIFY
$publisher->publish(
    channel: 'orders',
    message: new Message(
        channel: 'orders',
        payload: json_encode(['id' => $order->id, 'status' => 'placed']),
    ),
);

// Subscribing -- issues a PostgreSQL LISTEN
$subscription = $subscriber->subscribe('orders');

foreach ($subscription as $message) {
    $data = json_decode($message->payload, true);
}
```

## Documentation

Full usage, API reference, and examples: [marko/pubsub-pgsql](https://marko.build/docs/packages/pubsub-pgsql/)
