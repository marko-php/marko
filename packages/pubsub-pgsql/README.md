# Marko PubSub PostgreSQL

Zero-infrastructure pub/sub via PostgreSQL LISTEN/NOTIFY -- real-time messaging using the database you already have, no Redis required.

## Overview

`marko/pubsub-pgsql` provides `PgSqlPublisher` and `PgSqlSubscriber`, implementing the `PublisherInterface` and `SubscriberInterface` contracts from `marko/pubsub`. It uses PostgreSQL's built-in `NOTIFY`/`LISTEN` commands, delivered over an async connection via `amphp/postgres`. No additional infrastructure is required beyond your existing database.

Installing this package binds `PublisherInterface` and `SubscriberInterface` to the PostgreSQL driver automatically.

> **Note:** Pattern subscriptions are not supported by the PostgreSQL driver. Use `marko/pubsub-redis` if you need glob-style channel matching.

## Installation

```bash
composer require marko/pubsub-pgsql
```

This automatically installs `marko/pubsub` and `marko/amphp`.

## Usage

### Configuration

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

### Publishing

Inject `PublisherInterface` -- the PostgreSQL driver issues `NOTIFY` automatically:

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
php marko pubsub:listen
```

### Subscribing to multiple channels

Pass multiple channel names to subscribe to all of them in a single call:

```php
$subscription = $this->subscriber->subscribe('orders', 'shipments', 'returns');

foreach ($subscription as $message) {
    // $message->channel tells you which channel delivered the message
}
```

### SSE integration

Combine with `marko/sse` to push database notifications to the browser:

```php
use Marko\PubSub\SubscriberInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Route\Get;
use Marko\Sse\SseEvent;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;

#[Get('/orders/stream')]
public function stream(Request $request): StreamingResponse
{
    $subscription = $this->subscriber->subscribe('orders');

    $stream = new SseStream(
        dataProvider: function () use ($subscription): array {
            $events = [];

            foreach ($subscription as $message) {
                $events[] = new SseEvent(data: json_decode($message->payload, true));
                break;
            }

            return $events;
        },
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

```php
// module.php
return [
    'bindings' => [
        \Marko\PubSub\PgSql\PgSqlPubSubConnection::class => SslPgSqlPubSubConnection::class,
    ],
];
```

## API Reference

### PgSqlPublisher

```php
public function __construct(private PgSqlPubSubConnection $connection, private PubSubConfig $config)
public function publish(string $channel, Message $message): void;
```

### PgSqlSubscriber

```php
public function __construct(private PgSqlPubSubConnection $connection, private PubSubConfig $config)
public function subscribe(string ...$channels): Subscription;
/** @throws PubSubException -- pattern subscriptions are not supported */
public function psubscribe(string ...$patterns): Subscription;
```

### PgSqlSubscription

```php
public function __construct(array $listeners, string $prefix)
public function getIterator(): Generator; // yields Message instances
public function cancel(): void;
```

### PgSqlPubSubConnection

```php
public function __construct(string $host = '127.0.0.1', int $port = 5432, ?string $user = null, ?string $password = null, ?string $database = null, string $prefix = 'marko_')
public function connection(): PostgresConnection;
public function disconnect(): void;
public function isConnected(): bool;
```
