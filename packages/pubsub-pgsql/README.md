# marko/pubsub-pgsql

Zero-infrastructure pub/sub via PostgreSQL LISTEN/NOTIFY -- real-time messaging using the database you already have, no Redis required.

## Overview

`marko/pubsub-pgsql` implements the `marko/pubsub` contracts using PostgreSQL's native LISTEN/NOTIFY mechanism. `PgSqlPublisher` issues `NOTIFY` and `PgSqlSubscriber` issues `LISTEN` over a persistent async connection powered by `amphp/postgres`. Because PostgreSQL is already a common dependency, this driver lets you add real-time messaging with no additional infrastructure.

Note: PostgreSQL does not support pattern-based subscriptions. Calling `psubscribe()` throws `PubSubException`. Use `marko/pubsub-redis` if you need pattern matching.

## Installation

```bash
composer require marko/pubsub-pgsql
```

This automatically installs `marko/pubsub` and `marko/amphp`.

## Usage

The module binding wires `PublisherInterface` → `PgSqlPublisher` and `SubscriberInterface` → `PgSqlSubscriber` automatically. Type-hint against the interfaces in your services.

```php
use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;
use Marko\PubSub\SubscriberInterface;

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

$subscription->cancel();
```

Publish configuration in `config/pubsub-pgsql.php`:

```php
return [
    'host'     => '127.0.0.1',
    'port'     => 5432,
    'user'     => 'postgres',
    'password' => '',
    'database' => 'app',
];
```

## API Reference

### `PgSqlPublisher`

Implements `PublisherInterface`. Applies the configured channel prefix and issues a PostgreSQL `NOTIFY`.

```php
public function publish(string $channel, Message $message): void;
```

### `PgSqlSubscriber`

Implements `SubscriberInterface`. Applies the configured channel prefix and issues a PostgreSQL `LISTEN` for each channel.

```php
public function subscribe(string ...$channels): Subscription;
public function psubscribe(string ...$patterns): Subscription; // throws PubSubException
```

### `PgSqlSubscription`

Iterates incoming `NOTIFY` payloads as `Message` instances. Implements `Subscription` (`IteratorAggregate<int, Message>`).

```php
public function getIterator(): Generator; // yields Message
public function cancel(): void;
```

### `PgSqlPubSubConnection`

Manages the persistent `amphp/postgres` connection used by both the publisher and subscriber.

## Documentation

Full usage, API reference, and examples: [marko/pubsub-pgsql](https://marko.build/docs/packages/pubsub-pgsql/)
