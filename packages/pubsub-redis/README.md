# marko/pubsub-redis

Non-blocking Redis pub/sub for Marko -- publish and subscribe over Redis with pattern support, powered by amphp for async I/O.

## Installation

```bash
composer require marko/pubsub-redis
```

This automatically installs `marko/pubsub` and `marko/amphp`.

## Quick Example

```php
use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;

// Publishing
$this->publisher->publish(
    channel: 'user.42',
    message: new Message(
        channel: 'user.42',
        payload: json_encode(['text' => 'Hello!']),
    ),
);

// Subscribing
$subscription = $this->subscriber->subscribe('user.42');

foreach ($subscription as $message) {
    $data = json_decode($message->payload, true);
}
```

## Documentation

Full usage, API reference, and examples: [marko/pubsub-redis](https://marko.build/docs/packages/pubsub-redis/)
