# Marko PubSub

Real-time publish/subscribe messaging contracts for Marko -- type-hint against a stable interface and swap drivers without changing application code.

## Overview

`marko/pubsub` defines the `PublisherInterface` and `SubscriberInterface` contracts and the value objects they operate on. It has no concrete implementation. Install a driver package (`marko/pubsub-redis` or `marko/pubsub-pgsql`) to get a working pub/sub system. Your modules type-hint against the interfaces here, staying decoupled from any particular transport.

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

### Publishing messages

Inject `PublisherInterface` and call `publish()` with a channel name and a `Message`.

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

### Subscribing to a channel

Inject `SubscriberInterface`, call `subscribe()`, and iterate the returned `Subscription`.

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

### Pattern subscriptions

Use `psubscribe()` to subscribe to channels matching a glob pattern. The matched channel is available on the message. Note: not all drivers support pattern subscriptions (see driver documentation).

```php
$subscription = $this->subscriber->psubscribe('orders.*');

foreach ($subscription as $message) {
    // $message->pattern === 'orders.*'
    // $message->channel is the matched channel name
}
```

### Cancelling a subscription

Call `cancel()` on the `Subscription` to unsubscribe:

```php
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
public function publish(string $channel, Message $message): void;
```

### SubscriberInterface

```php
public function subscribe(string ...$channels): Subscription;
public function psubscribe(string ...$patterns): Subscription;
```

### Subscription

```php
public function getIterator(): Generator; // yields Message instances
public function cancel(): void;
```

### Message

```php
public function __construct(
    public string $channel,
    public string $payload,
    public ?string $pattern = null,
)
```

### PubSubConfig

```php
public function driver(): string;
public function prefix(): string;
```

### PubSubException

```php
public static function connectionFailed(string $driver, string $reason): self;
public static function subscriptionFailed(string $channel, string $reason): self;
public static function publishFailed(string $channel, string $reason): self;
public static function patternSubscriptionNotSupported(string $driver): self;
```
