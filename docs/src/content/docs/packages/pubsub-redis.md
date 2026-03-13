---
title: marko/pubsub-redis
description: Non-blocking Redis pub/sub driver — publish and subscribe over Redis with pattern support, powered by amphp for async I/O.
---

Non-blocking Redis pub/sub for Marko --- publish and subscribe over Redis with pattern support, powered by amphp for async I/O. Provides `RedisPublisher` and `RedisSubscriber`, implementing the `PublisherInterface` and `SubscriberInterface` contracts from [`marko/pubsub`](/docs/packages/pubsub/). Uses `amphp/redis` for non-blocking Redis connections so the subscriber loop never stalls. Pattern subscriptions (glob-style channel matching) are fully supported.

Installing this package binds `PublisherInterface` and `SubscriberInterface` to the Redis driver automatically --- no manual wiring required.

## Installation

```bash
composer require marko/pubsub-redis
```

This automatically installs `marko/pubsub` and `marko/amphp`.

## Configuration

Set environment variables or publish the config file:

```bash
PUBSUB_REDIS_HOST=127.0.0.1
PUBSUB_REDIS_PORT=6379
PUBSUB_REDIS_PASSWORD=
PUBSUB_REDIS_DATABASE=0
PUBSUB_DRIVER=redis
PUBSUB_PREFIX=marko:
```

## Usage

### Publishing

Inject `PublisherInterface` --- the Redis driver is used automatically:

```php
use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;

class NotificationService
{
    public function __construct(
        private PublisherInterface $publisher,
    ) {}

    public function notify(int $userId, string $text): void
    {
        $this->publisher->publish(
            channel: "user.$userId",
            message: new Message(
                channel: "user.$userId",
                payload: json_encode(['text' => $text]),
            ),
        );
    }
}
```

### Subscribing

Inject `SubscriberInterface` and iterate the `Subscription`. Run the subscriber loop via the `pubsub:listen` command:

```php
use Marko\PubSub\SubscriberInterface;

class NotificationListener
{
    public function __construct(
        private SubscriberInterface $subscriber,
    ) {}

    public function listen(int $userId): void
    {
        $subscription = $this->subscriber->subscribe("user.$userId");

        foreach ($subscription as $message) {
            $data = json_decode($message->payload, true);
            // handle notification ...
        }
    }
}
```

Start the listener process:

```bash
marko pubsub:listen
```

### Pattern Subscriptions

Use `psubscribe()` to receive messages from all channels matching a glob pattern:

```php
$subscription = $this->subscriber->psubscribe('user.*');

foreach ($subscription as $message) {
    // $message->pattern === 'user.*'
    // $message->channel is the matched channel, e.g. 'user.42'
    $data = json_decode($message->payload, true);
}
```

### SSE Integration

Combine with `marko/sse` to stream pub/sub messages to the browser:

```php
use Marko\PubSub\SubscriberInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Route\Get;
use Marko\Sse\SseEvent;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;

#[Get('/users/{userId}/notifications')]
public function stream(Request $request, int $userId): StreamingResponse
{
    $subscription = $this->subscriber->subscribe("user.$userId");

    $stream = new SseStream(
        dataProvider: function () use ($subscription): array {
            $events = [];

            foreach ($subscription as $message) {
                $events[] = new SseEvent(data: json_decode($message->payload, true));
                break; // yield one batch per poll
            }

            return $events;
        },
    );

    return new StreamingResponse($stream);
}
```

## Customization

Override the Redis connection by extending `RedisPubSubConnection` via a Preference:

```php
use Amp\Redis\RedisClient;
use Marko\PubSub\Redis\RedisPubSubConnection;

class TlsRedisPubSubConnection extends RedisPubSubConnection
{
    protected function createClient(): RedisClient
    {
        return \Amp\Redis\createRedisClient("rediss://$this->host:$this->port");
    }
}
```

Register it in your module:

```php title="module.php"
use Marko\PubSub\Redis\RedisPubSubConnection;

return [
    'bindings' => [
        RedisPubSubConnection::class => TlsRedisPubSubConnection::class,
    ],
];
```

## API Reference

### RedisPublisher

| Method | Description |
|---|---|
| `__construct(RedisPubSubConnection $redisPubSubConnection, PubSubConfig $pubSubConfig)` | Create a publisher with a Redis connection and pub/sub configuration |
| `publish(string $channel, Message $message): void` | Publish a message to the given channel |

### RedisSubscriber

| Method | Description |
|---|---|
| `__construct(RedisPubSubConnection $redisPubSubConnection, PubSubConfig $pubSubConfig)` | Create a subscriber with a Redis connection and pub/sub configuration |
| `subscribe(string ...$channels): Subscription` | Subscribe to one or more channels, returning an iterable `Subscription` |
| `psubscribe(string ...$patterns): Subscription` | Subscribe to channels matching glob patterns, returning an iterable `Subscription` |

### RedisSubscription

| Method | Description |
|---|---|
| `__construct(AmphpRedisSubscription $amphpSubscription, string $prefix, ?string $channel, ?string $pattern)` | Wrap an amphp subscription with prefix stripping and message conversion |
| `getIterator(): Generator` | Yield `Message` instances --- includes `pattern` and resolved `channel` for pattern subscriptions |
| `cancel(): void` | Unsubscribe and stop iteration |

### RedisPubSubConnection

| Method | Description |
|---|---|
| `__construct(string $host, int $port, ?string $password, int $database, string $prefix)` | Create a connection with host (`127.0.0.1`), port (`6379`), optional password, database index (`0`), and channel prefix (`marko:`) |
| `client(): RedisClient` | Get the Redis client instance --- lazily connected on first call |
| `connector(): RedisConnector` | Get the Redis connector instance --- lazily created on first call |
| `disconnect(): void` | Disconnect and release both client and connector instances |
| `isConnected(): bool` | Check whether a client instance is currently active |
