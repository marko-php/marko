# marko/pubsub

Real-time publish/subscribe messaging contracts — type-hint against a stable interface and swap drivers without changing application code.

## Overview

`marko/pubsub` provides the core contracts for publish/subscribe messaging in Marko. It defines `PublisherInterface`, `SubscriberInterface`, `Subscription`, and the `Message` value object. The package ships no driver of its own — install a driver package such as `marko/pubsub-pgsql` or `marko/pubsub-redis` to get a concrete implementation.

## Installation

```bash
composer require marko/pubsub
```

## Usage

Type-hint against the interfaces in your services and let the container inject the active driver.

```php
use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;
use Marko\PubSub\SubscriberInterface;

// Publishing
$publisher->publish(
    channel: 'orders',
    message: new Message(
        channel: 'orders',
        payload: json_encode(['id' => $order->id, 'status' => 'placed']),
    ),
);

// Subscribing
$subscription = $subscriber->subscribe('orders');

foreach ($subscription as $message) {
    $data = json_decode($message->payload, true);
}

// Cancel when done
$subscription->cancel();

// Pattern subscriptions (Redis driver only)
$subscription = $subscriber->psubscribe('orders.*');
```

Configuration keys used by driver packages:

```php
// config/pubsub.php
return [
    'driver' => 'redis',   // active driver
    'prefix' => '',        // optional channel prefix applied by all drivers
];
```

## API Reference

### `PublisherInterface`

```php
interface PublisherInterface
{
    public function publish(string $channel, Message $message): void;
}
```

### `SubscriberInterface`

```php
interface SubscriberInterface
{
    public function subscribe(string ...$channels): Subscription;
    public function psubscribe(string ...$patterns): Subscription;
}
```

### `Message`

```php
readonly class Message
{
    public function __construct(
        public string $channel,
        public string $payload,
        public ?string $pattern = null,
    ) {}
}
```

### `Subscription`

```php
interface Subscription extends IteratorAggregate
{
    /** @return Generator<int, Message> */
    public function getIterator(): Generator;
    public function cancel(): void;
}
```

### `PubSubException`

Named constructors for all failure scenarios:

| Factory method | When thrown |
|---|---|
| `connectionFailed(string $driver, string $reason)` | Driver cannot connect |
| `subscriptionFailed(string $channel, string $reason)` | Subscribe call fails |
| `publishFailed(string $channel, string $reason)` | Publish call fails |
| `patternSubscriptionNotSupported(string $driver)` | `psubscribe()` on a driver that does not support patterns |

## Documentation

Full usage, API reference, and examples: [marko/pubsub](https://marko.build/docs/packages/pubsub/)
