---
title: marko/pubsub
description: Real-time publish/subscribe messaging contracts — type-hint against a stable interface and swap drivers without changing application code.
---

Real-time publish/subscribe messaging contracts --- type-hint against a stable interface and swap drivers without changing application code. PubSub defines the `PublisherInterface` and `SubscriberInterface` contracts and the value objects they operate on. It has no concrete implementation --- install a driver package to get a working pub/sub system. Your modules type-hint against the interfaces here, staying decoupled from any particular transport.

**This package defines contracts only.** Install a driver for implementation:

- `marko/pubsub-redis` --- Redis-backed pub/sub
- `marko/pubsub-pgsql` --- PostgreSQL-backed pub/sub

## Installation

```bash
composer require marko/pubsub
```

Install a driver alongside it:

```bash
composer require marko/pubsub-redis
# or
composer require marko/pubsub-pgsql
```

## Usage

### Publishing Messages

Inject `PublisherInterface` and call `publish()` with a channel name and a `Message`:

```php
use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;

class OrderService
{
    public function __construct(
        private PublisherInterface $publisher,
    ) {}

    public function placeOrder(Order $order): void
    {
        // ... persist the order ...

        $this->publisher->publish(
            channel: 'orders',
            message: new Message(
                channel: 'orders',
                payload: json_encode(['id' => $order->id, 'status' => 'placed']),
            ),
        );
    }
}
```

### Subscribing to a Channel

Inject `SubscriberInterface`, call `subscribe()`, and iterate the returned `Subscription`:

```php
use Marko\PubSub\SubscriberInterface;

class OrderListener
{
    public function __construct(
        private SubscriberInterface $subscriber,
    ) {}

    public function listen(): void
    {
        $subscription = $this->subscriber->subscribe('orders');

        foreach ($subscription as $message) {
            $data = json_decode($message->payload, true);
            // handle the message ...
        }
    }
}
```

### Pattern Subscriptions

Use `psubscribe()` to subscribe to channels matching a glob pattern. The matched channel is available on the message. Note: not all drivers support pattern subscriptions --- see driver documentation.

```php
use Marko\PubSub\SubscriberInterface;

$subscription = $this->subscriber->psubscribe('orders.*');

foreach ($subscription as $message) {
    // $message->pattern === 'orders.*'
    // $message->channel is the matched channel name
}
```

### Cancelling a Subscription

Call `cancel()` on the `Subscription` to unsubscribe:

```php
use Marko\PubSub\SubscriberInterface;

$subscription = $this->subscriber->subscribe('orders');

foreach ($subscription as $message) {
    if ($this->shouldStop($message)) {
        $subscription->cancel();
        break;
    }
}
```

## API Reference

### PublisherInterface

```php
use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;

public function publish(string $channel, Message $message): void;
```

### SubscriberInterface

```php
use Marko\PubSub\Subscription;
use Marko\PubSub\SubscriberInterface;

public function subscribe(string ...$channels): Subscription;
public function psubscribe(string ...$patterns): Subscription;
```

### Subscription

`Subscription` extends `IteratorAggregate<int, Message>` and yields `Message` instances:

```php
use Generator;
use Marko\PubSub\Message;
use Marko\PubSub\Subscription;

public function getIterator(): Generator; // yields Message instances
public function cancel(): void;
```

### Message

A `readonly` value object representing a pub/sub message:

```php
use Marko\PubSub\Message;

public function __construct(
    public string $channel,
    public string $payload,
    public ?string $pattern = null,
)
```

### PubSubConfig

Typed access to pub/sub configuration values:

```php
use Marko\PubSub\PubSubConfig;

public function driver(): string;
public function prefix(): string;
```

### Exceptions

| Exception | Description |
|-----------|-------------|
| `PubSubException` | Base exception for all pub/sub errors --- extends `MarkoException` with `getContext()` and `getSuggestion()` methods |

`PubSubException` provides named constructors for common failure scenarios:

```php
use Marko\PubSub\Exceptions\PubSubException;

PubSubException::connectionFailed(string $driver, string $reason): self;
PubSubException::subscriptionFailed(string $channel, string $reason): self;
PubSubException::publishFailed(string $channel, string $reason): self;
PubSubException::patternSubscriptionNotSupported(string $driver): self;
```
