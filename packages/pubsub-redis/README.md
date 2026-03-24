# marko/pubsub-redis

Non-blocking Redis pub/sub for Marko -- publish and subscribe over Redis with pattern support, powered by amphp for async I/O.

## Overview

`marko/pubsub-redis` implements the `marko/pubsub` contracts using Redis PUB/SUB. `RedisPublisher` calls `PUBLISH` and `RedisSubscriber` supports both channel subscriptions (`SUBSCRIBE`) and pattern subscriptions (`PSUBSCRIBE`), making it the only built-in driver that supports `psubscribe()`. All I/O is non-blocking via `amphp/redis`.

## Installation

```bash
composer require marko/pubsub-redis
```

This automatically installs `marko/pubsub` and `marko/amphp`.

## Usage

The module binding wires `PublisherInterface` → `RedisPublisher` and `SubscriberInterface` → `RedisSubscriber` automatically. Type-hint against the interfaces in your services.

```php
use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;
use Marko\PubSub\SubscriberInterface;

// Publishing
$publisher->publish(
    channel: 'user.42',
    message: new Message(
        channel: 'user.42',
        payload: json_encode(['text' => 'Hello!']),
    ),
);

// Channel subscription
$subscription = $subscriber->subscribe('user.42');

foreach ($subscription as $message) {
    $data = json_decode($message->payload, true);
}

$subscription->cancel();

// Pattern subscription (Redis-only feature)
$subscription = $subscriber->psubscribe('user.*');

foreach ($subscription as $message) {
    // $message->pattern contains the matched pattern
    $data = json_decode($message->payload, true);
}
```

Configure the Redis connection in `config/pubsub-redis.php`:

```php
return [
    'host'     => '127.0.0.1',
    'port'     => 6379,
    'password' => null,
    'database' => 0,
];
```

## API Reference

### `RedisPublisher`

Implements `PublisherInterface`. Applies the configured channel prefix and calls Redis `PUBLISH`.

```php
public function publish(string $channel, Message $message): void;
```

### `RedisSubscriber`

Implements `SubscriberInterface`. Supports both exact channel and pattern subscriptions.

```php
public function subscribe(string ...$channels): Subscription;   // Redis SUBSCRIBE
public function psubscribe(string ...$patterns): Subscription;  // Redis PSUBSCRIBE
```

### `RedisSubscription`

Iterates incoming Redis messages as `Message` instances. Implements `Subscription` (`IteratorAggregate<int, Message>`).

```php
public function getIterator(): Generator; // yields Message
public function cancel(): void;
```

### `RedisPubSubConnection`

Manages the `amphp/redis` client used by the publisher and the connector used by the subscriber.

### `AmphpRedisSubscriberInterface` / `DefaultAmphpRedisSubscriber`

Internal abstraction over the `amphp/redis` subscriber. `DefaultAmphpRedisSubscriber` is the production implementation; the interface exists to allow substitution in tests.

## Documentation

Full usage, API reference, and examples: [marko/pubsub-redis](https://marko.build/docs/packages/pubsub-redis/)
