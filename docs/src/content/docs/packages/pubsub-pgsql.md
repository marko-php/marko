---
title: marko/pubsub-pgsql
description: PostgreSQL pub/sub driver — real-time messaging via LISTEN/NOTIFY using the database you already have.
---

Zero-infrastructure pub/sub via PostgreSQL LISTEN/NOTIFY --- real-time messaging using the database you already have, no Redis required. Provides `PgSqlPublisher` and `PgSqlSubscriber`, implementing the `PublisherInterface` and `SubscriberInterface` contracts from [`marko/pubsub`](/docs/packages/pubsub/). Uses PostgreSQL's built-in `NOTIFY`/`LISTEN` commands, delivered over an async connection via `amphp/postgres`. No additional infrastructure is required beyond your existing database.

Installing this package binds `PublisherInterface` and `SubscriberInterface` to the PostgreSQL driver automatically.

:::note
Pattern subscriptions are not supported by the PostgreSQL driver. Use `marko/pubsub-redis` if you need glob-style channel matching.
:::

## Installation

```bash
composer require marko/pubsub-pgsql
```

This automatically installs `marko/pubsub` and `marko/amphp`.

## Configuration

Set environment variables or publish the config file:

```bash
PUBSUB_PGSQL_HOST=127.0.0.1
PUBSUB_PGSQL_PORT=5432
PUBSUB_PGSQL_USER=app
PUBSUB_PGSQL_PASSWORD=secret
PUBSUB_PGSQL_DATABASE=app
PUBSUB_DRIVER=pgsql
PUBSUB_PREFIX=marko_
```

## Usage

### Publishing

Inject `PublisherInterface` --- the PostgreSQL driver issues `NOTIFY` automatically:

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

### Subscribing

Inject `SubscriberInterface` and iterate the `Subscription`. Run the subscriber loop via the `pubsub:listen` command:

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
            // handle order ...
        }
    }
}
```

Start the listener process:

```bash
marko pubsub:listen
```

### Subscribing to Multiple Channels

Pass multiple channel names to subscribe to all of them in a single call:

```php
$subscription = $this->subscriber->subscribe('orders', 'shipments', 'returns');

foreach ($subscription as $message) {
    // $message->channel tells you which channel delivered the message
}
```

### SSE Integration

Combine with `marko/sse` to push database notifications to the browser:

```php
use Marko\PubSub\SubscriberInterface;
use Marko\Routing\Attributes\Get;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;

#[Get('/orders/stream')]
public function stream(): StreamingResponse
{
    $subscription = $this->subscriber->subscribe('orders');

    $stream = new SseStream(
        subscription: $subscription,
        timeout: 300,
    );

    return new StreamingResponse($stream);
}
```

## Customization

Override the PostgreSQL connection by extending `PgSqlPubSubConnection` via a Preference:

```php
use Marko\PubSub\PgSql\PgSqlPubSubConnection;
use Amp\Postgres\PostgresConfig;

class SslPgSqlPubSubConnection extends PgSqlPubSubConnection
{
    protected function createConfig(): PostgresConfig
    {
        return new PostgresConfig(
            host: $this->host,
            port: $this->port,
            user: $this->user,
            password: $this->password,
            database: $this->database,
        );
    }
}
```

Register it in your module:

```php title="module.php"
return [
    'bindings' => [
        \Marko\PubSub\PgSql\PgSqlPubSubConnection::class => SslPgSqlPubSubConnection::class,
    ],
];
```

## API Reference

### PgSqlPublisher

Implements `PublisherInterface`. Sends messages via PostgreSQL `NOTIFY`.

| Method | Description |
|---|---|
| `__construct(PgSqlPubSubConnection $pgSqlPubSubConnection, PubSubConfig $pubSubConfig)` | Create a publisher with a PostgreSQL connection and pub/sub configuration |
| `publish(string $channel, Message $message): void` | Publish a message to the given channel --- issues a `NOTIFY` on the prefixed channel |

### PgSqlSubscriber

Implements `SubscriberInterface`. Listens for messages via PostgreSQL `LISTEN`.

| Method | Description |
|---|---|
| `__construct(PgSqlPubSubConnection $pgSqlPubSubConnection, PubSubConfig $pubSubConfig)` | Create a subscriber with a PostgreSQL connection and pub/sub configuration |
| `subscribe(string ...$channels): Subscription` | Subscribe to one or more channels --- issues a `LISTEN` for each prefixed channel |
| `psubscribe(string ...$patterns): Subscription` | Always throws `PubSubException` --- pattern subscriptions are not supported by PostgreSQL |

### PgSqlSubscription

Implements `Subscription`. Wraps PostgreSQL listener(s) and yields `Message` instances.

| Method | Description |
|---|---|
| `__construct(array $listeners, string $prefix)` | Create a subscription from an array of `PostgresListener` instances and the channel prefix |
| `getIterator(): Generator` | Yields `Message` instances as notifications arrive --- strips the prefix from channel names |
| `cancel(): void` | Unlisten from all channels and stop receiving notifications |

### PgSqlPubSubConnection

Manages the async PostgreSQL connection used for pub/sub. Lazily connects on first use.

| Method | Description |
|---|---|
| `__construct(string $host, int $port, ?string $user, ?string $password, ?string $database, string $prefix)` | Create a connection with host (`127.0.0.1`), port (`5432`), optional user/password/database, and channel prefix (`marko_`) |
| `connection(): PostgresConnection` | Get the async PostgreSQL connection --- lazily connected on first call |
| `disconnect(): void` | Disconnect and release the connection instance |
| `isConnected(): bool` | Check whether a connection instance is currently active |
