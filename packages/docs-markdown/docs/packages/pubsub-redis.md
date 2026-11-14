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

The package ships `config/pubsub-redis.php`, and its module binding builds a single shared `RedisPubSubConnection` from it. Set the environment variables, or override the file in your app:

```php title="config/pubsub-redis.php"
use Marko\Config\Env;

return [
    'host' => Env::string('PUBSUB_REDIS_HOST', '127.0.0.1'),
    'port' => Env::int('PUBSUB_REDIS_PORT', 6379, min: 1, max: 65535),
    'password' => Env::nullableString('PUBSUB_REDIS_PASSWORD'),
    'database' => Env::int('PUBSUB_REDIS_DATABASE', 0, min: 0),
    'scheme' => Env::string('PUBSUB_REDIS_SCHEME', 'tcp'),
];
```

| Key | Env var | Default | Description |
|---|---|---|---|
| `host` | `PUBSUB_REDIS_HOST` | `127.0.0.1` | Redis server host |
| `port` | `PUBSUB_REDIS_PORT` | `6379` | Redis server port |
| `password` | `PUBSUB_REDIS_PASSWORD` | `null` | Password for `AUTH`; `null` or empty means no authentication |
| `database` | `PUBSUB_REDIS_DATABASE` | `0` | Redis database index |
| `scheme` | `PUBSUB_REDIS_SCHEME` | `tcp` | `tcp` for plain text, or `tls` to encrypt the connection and verify the server certificate against `host`. Any other value throws `PubSubException` |

Use `tls` for any Redis reached over a network you do not control (managed Redis services usually require it). With `tcp`, the `AUTH` password and every message cross the network in plain text. With `tls`, the server certificate must be valid for `host` and signed by a CA in the system trust store; amphp/redis has no `rediss://` URI, so the scheme is a separate key.

The channel prefix is not a Redis setting. It comes from `pubsub.prefix` in [`marko/pubsub`](/docs/packages/pubsub/) (`PUBSUB_PREFIX`, default `marko:`), which is the single source of truth: the publisher, the subscriber and `RedisPubSubConnection::$prefix` all use it.

```bash
PUBSUB_DRIVER=redis
PUBSUB_PREFIX=marko:
```

## Connections

The publisher and the subscriber use separate Redis connections, because a Redis connection in subscribe mode cannot run other commands.

- **Publishing** goes through one `RedisClient`, shared by the singleton `RedisPubSubConnection`.
- **Subscribing** goes through one connection per `RedisSubscriber`. The module binding shares `SubscriberInterface` as a singleton, so a process has **one subscriber connection**, however many channels or patterns it subscribes to. 500 subscriptions are 500 `SUBSCRIBE`s on that one connection, not 500 connections.

How the shared subscriber connection behaves:

- It opens on the first `subscribe()` or `psubscribe()` and stays open for the life of the process. After the last subscription is cancelled the connection is reopened with no subscriptions, ready for the next one.
- `cancel()` on a `Subscription` unsubscribes only that subscription's channels and patterns. Other subscriptions on the connection keep receiving. A channel that two subscriptions share stays subscribed until both are cancelled.
- If the connection drops, it reconnects and re-subscribes every open channel and pattern. Messages published while it was disconnected are lost: Redis pub/sub delivers at most once.
- If the reconnect fails (for example, Redis is down), every open subscription ends with an error. The next `subscribe()` opens a new connection.

:::caution[Keep reading every subscription]
Delivery uses backpressure: the connection waits until a message has been read before it reads the next one. A subscription you stop iterating therefore stalls delivery for **every** subscription in the process. Iterate each subscription in its own fiber (as `marko/broadcasting-amphp` does), and `cancel()` a subscription as soon as you no longer read it. A subscription to several channels reads all of them at once, so a quiet first channel never holds up the others.
:::

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

    public function notify(
        int $userId,
        string $text,
    ): void {
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

Inject `SubscriberInterface` and iterate the `Subscription`. Pass multiple channel names to receive from all of them in a single subscription; its channels are read at the same time, and each channel's messages arrive in order. Run the subscriber loop via the `pubsub:listen` command:

```php
use Marko\PubSub\SubscriberInterface;

class NotificationListener
{
    public function __construct(
        private SubscriberInterface $subscriber,
    ) {}

    public function listen(int $userId): void
    {
        // Single channel
        $subscription = $this->subscriber->subscribe("user.$userId");

        foreach ($subscription as $message) {
            $data = json_decode($message->payload, true);
            // handle notification ...
        }
    }

    public function listenAll(): void
    {
        // Multiple channels — delivers from all of them
        $subscription = $this->subscriber->subscribe('orders', 'shipments', 'returns');

        foreach ($subscription as $message) {
            // $message->channel tells you which channel delivered the message
            $data = json_decode($message->payload, true);
        }
    }
}
```

Start the listener process:

```bash
marko pubsub:listen
```

### Pattern Subscriptions

Use `psubscribe()` to receive messages from all channels matching a glob pattern (Redis `PSUBSCRIBE`). `marko/pubsub-redis` is the only built-in driver that supports pattern subscriptions:

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
use Marko\Routing\Attributes\Get;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;

#[Get('/users/{userId}/notifications')]
public function stream(int $userId): StreamingResponse
{
    $subscription = $this->subscriber->subscribe("user.$userId");

    $stream = new SseStream(
        subscription: $subscription,
        timeout: 300,
    );

    return new StreamingResponse($stream);
}
```

## Customization

To change how the connection is built (for example, to connect over a Unix socket), extend `RedisPubSubConnection` and override `redisConfig()`, which both the client and the connector use:

```php
use Amp\Redis\RedisConfig;
use Marko\PubSub\Redis\RedisPubSubConnection;

class SocketRedisPubSubConnection extends RedisPubSubConnection
{
    protected function redisConfig(): RedisConfig
    {
        return RedisConfig::fromUri("unix://$this->host")
            ->withDatabase($this->database)
            ->withPassword($this->password ?? '');
    }
}
```

Bind it in your app module with the same config keys the package binding reads:

```php title="app/mymodule/module.php"
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\PubSub\Redis\RedisPubSubConnection;

return [
    'bindings' => [
        RedisPubSubConnection::class => static function (ContainerInterface $container): RedisPubSubConnection {
            $config = $container->get(ConfigRepositoryInterface::class);

            return new SocketRedisPubSubConnection(
                host: $config->getString(key: 'pubsub-redis.host'),
                port: $config->getInt(key: 'pubsub-redis.port'),
                password: $config->get(key: 'pubsub-redis.password'),
                database: $config->getInt(key: 'pubsub-redis.database'),
                prefix: $config->getString(key: 'pubsub.prefix'),
            );
        },
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
| `subscribe(string ...$channels): Subscription` | Subscribe to one or more channels (Redis `SUBSCRIBE`), returning an iterable `Subscription`. Every call shares this subscriber's one Redis connection |
| `psubscribe(string ...$patterns): Subscription` | Subscribe to channels matching glob patterns (Redis `PSUBSCRIBE`), returning an iterable `Subscription`. Shares the same connection |
| `createAmphpSubscriber(): AmphpRedisSubscriberInterface` (protected) | Build the amphp subscriber behind the shared connection. Called once, on the first subscription; override it to substitute the subscriber in tests |

### RedisSubscription

| Method | Description |
|---|---|
| `__construct(AmphpRedisSubscription[] $amphpSubscriptions, string $prefix, string[] $channels, string[] $patterns)` | Wrap one or more amphp subscriptions with prefix stripping and message conversion. Pass channel names for channel subscriptions, pattern names for pattern subscriptions. |
| `getIterator(): Generator` | Yield `Message` instances from all subscriptions, reading every channel at once --- includes `pattern` and resolved `channel` for pattern subscriptions |
| `cancel(): void` | Unsubscribe from this subscription's channels/patterns and stop iteration. Other subscriptions on the shared connection are not affected |

### AmphpRedisSubscriberInterface, DefaultAmphpRedisSubscriber and SharedAmphpRedisSubscriber

Internal abstraction over the `amphp/redis` subscriber, which multiplexes channels and patterns over one connection. `DefaultAmphpRedisSubscriber` is the production implementation. `SharedAmphpRedisSubscriber` creates it lazily, once, and is how `RedisSubscriber` sends every subscription over the same connection. The interface exists to allow substitution in tests.

| Method | Description |
|---|---|
| `subscribe(string $channel): AmphpRedisSubscription` | Subscribe to one prefixed channel |
| `subscribeToPattern(string $pattern): AmphpRedisSubscription` | Subscribe to one prefixed glob pattern |

### RedisPubSubConnection

| Method | Description |
|---|---|
| `__construct(string $host, int $port, ?string $password, int $database, string $prefix, string $scheme)` | Create a connection with host (`127.0.0.1`), port (`6379`), optional password, database index (`0`), channel prefix (`marko:`) and scheme (`tcp` or `tls`, default `tcp`). The module binding fills these from `config/pubsub-redis.php` and `pubsub.prefix`. Throws `PubSubException` for an unknown scheme |
| `client(): RedisClient` | Get the Redis client instance --- lazily connected on first call |
| `connector(): RedisConnector` | Get the Redis connector instance --- lazily created on first call |
| `redisConfig(): RedisConfig` (protected) | Build the amphp `RedisConfig` (host, port, password, database) used by the client and connector |
| `socketRedisConnector(): ?RedisConnector` (protected) | The connector that opens the socket: `null` for `tcp`, or one that completes a verified TLS handshake (through `TlsSocketConnector`) for `tls` |
| `disconnect(): void` | Release both client and connector instances. A subscriber that already connected keeps its own connection open |
| `isConnected(): bool` | Check whether a client instance is currently active |
