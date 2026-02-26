# Marko PubSub Redis

Non-blocking Redis pub/sub for Marko -- publish and subscribe over Redis with pattern support, powered by amphp for async I/O.

## Overview

`marko/pubsub-redis` provides `RedisPublisher` and `RedisSubscriber`, implementing the `PublisherInterface` and `SubscriberInterface` contracts from `marko/pubsub`. It uses `amphp/redis` for non-blocking Redis connections so the subscriber loop never stalls. Pattern subscriptions (glob-style channel matching) are fully supported.

Installing this package binds `PublisherInterface` and `SubscriberInterface` to the Redis driver automatically -- no manual wiring required.

## Installation

```bash
composer require marko/pubsub-redis
```

This automatically installs `marko/pubsub` and `marko/amphp`.

## Usage

### Configuration

Set environment variables or publish the config file:

```bash
PUBSUB_REDIS_HOST=127.0.0.1
PUBSUB_REDIS_PORT=6379
PUBSUB_REDIS_PASSWORD=
PUBSUB_REDIS_DATABASE=0
PUBSUB_DRIVER=redis
PUBSUB_PREFIX=marko:
```

### Publishing

Inject `PublisherInterface` -- the Redis driver is used automatically:

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
php marko pubsub:listen
```

### Pattern subscriptions

Use `psubscribe()` to receive messages from all channels matching a glob pattern:

```php
$subscription = $this->subscriber->psubscribe('user.*');

foreach ($subscription as $message) {
    // $message->pattern === 'user.*'
    // $message->channel is the matched channel, e.g. 'user.42'
    $data = json_decode($message->payload, true);
}
```

### SSE integration

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
use Marko\PubSub\Redis\RedisPubSubConnection;

class TlsRedisPubSubConnection extends RedisPubSubConnection
{
    protected function createClient(): \Amp\Redis\RedisClient
    {
        return \Amp\Redis\createRedisClient("rediss://$this->host:$this->port");
    }
}
```

Register it in your module:

```php
// module.php
return [
    'bindings' => [
        \Marko\PubSub\Redis\RedisPubSubConnection::class => TlsRedisPubSubConnection::class,
    ],
];
```

## API Reference

### RedisPublisher

```php
public function __construct(private RedisPubSubConnection $connection, private PubSubConfig $config)
public function publish(string $channel, Message $message): void;
```

### RedisSubscriber

```php
public function __construct(private RedisPubSubConnection $connection, private PubSubConfig $config)
public function subscribe(string ...$channels): Subscription;
public function psubscribe(string ...$patterns): Subscription;
```

### RedisSubscription

```php
public function __construct(AmphpRedisSubscription $amphpSubscription, string $prefix, ?string $channel = null, ?string $pattern = null)
public function getIterator(): Generator; // yields Message instances
public function cancel(): void;
```

### RedisPubSubConnection

```php
public function __construct(string $host = '127.0.0.1', int $port = 6379, ?string $password = null, int $database = 0, string $prefix = 'marko:')
public function client(): RedisClient;
public function connector(): RedisConnector;
public function disconnect(): void;
public function isConnected(): bool;
```
