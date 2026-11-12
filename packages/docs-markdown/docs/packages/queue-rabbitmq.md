---
title: marko/queue-rabbitmq
description: RabbitMQ queue driver — processes jobs through AMQP with persistent messages, exchange routing, and delayed delivery.
---

RabbitMQ queue driver --- processes jobs through AMQP with persistent messages, exchange routing, and delayed delivery. Jobs are published as persistent AMQP messages through configurable exchanges (direct, fanout, topic, or headers). Delayed jobs use dead-letter exchanges for timed redelivery. Failed jobs are stored in a dedicated RabbitMQ queue for inspection and retry. Requires a running RabbitMQ server and the `php-amqplib/php-amqplib` package.

Implements `QueueInterface` and `FailedJobRepositoryInterface` from [`marko/queue`](/docs/packages/queue/).

## Installation

```bash
composer require marko/queue-rabbitmq
```

This automatically installs `marko/queue` and `php-amqplib/php-amqplib`. A non-empty `encryption.key` (via [`marko/encryption`](/docs/packages/encryption/)) is also required because job payloads are HMAC-signed --- see [`marko/queue`](/docs/packages/queue/) for details.

## Configuration

Installing the package binds `QueueInterface`, `FailedJobRepositoryInterface`, a shared `RabbitmqConnection` and the `ExchangeConfig` from `config/queue-rabbitmq.php`. No hand-written bindings are needed. Set the environment variables, or override the file in your app:

```php title="config/queue-rabbitmq.php"
use Marko\Config\Env;

return [
    'host' => Env::string('RABBITMQ_HOST', 'localhost'),
    'port' => Env::int('RABBITMQ_PORT', 5672, min: 1, max: 65535),
    'user' => Env::string('RABBITMQ_USER', 'guest'),
    'password' => Env::string('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => Env::string('RABBITMQ_VHOST', '/'),
    // SSL stream context options (e.g. ['cafile' => '/path/ca.pem', 'verify_peer' => true]), or null for plain TCP
    'tls' => null,
    'exchange' => [
        'name' => Env::string('RABBITMQ_EXCHANGE', 'marko'),
        'type' => Env::string('RABBITMQ_EXCHANGE_TYPE', 'direct'),
        'durable' => true,
        'auto_delete' => false,
    ],
];
```

| Key | Env var | Default | Description |
|---|---|---|---|
| `host` | `RABBITMQ_HOST` | `localhost` | RabbitMQ server host |
| `port` | `RABBITMQ_PORT` | `5672` | AMQP port (usually `5671` with TLS) |
| `user` | `RABBITMQ_USER` | `guest` | AMQP user |
| `password` | `RABBITMQ_PASSWORD` | `guest` | AMQP password |
| `vhost` | `RABBITMQ_VHOST` | `/` | Virtual host |
| `tls` | --- | `null` | SSL stream context options as an array, or `null` for plain TCP |
| `exchange.name` | `RABBITMQ_EXCHANGE` | `marko` | Exchange that jobs are published to |
| `exchange.type` | `RABBITMQ_EXCHANGE_TYPE` | `direct` | One of `direct`, `fanout`, `topic`, `headers` |
| `exchange.durable` | --- | `true` | Whether the exchange survives a broker restart |
| `exchange.auto_delete` | --- | `false` | Whether the exchange is deleted when no queues are bound |

The default queue name comes from `queue.queue` in [`marko/queue`](/docs/packages/queue/).

To enable TLS, override `tls` with SSL context options:

```php title="config/queue-rabbitmq.php"
return [
    // ...
    'port' => 5671,
    'tls' => [
        'verify_peer' => true,
        'cafile' => '/path/to/ca.pem',
    ],
];
```

### Credentials

The shipped `guest`/`guest` credentials only work against a broker on this machine. `RabbitmqConnection` throws a `RabbitmqException` when it is built with `guest`/`guest` and a host that is not loopback (`localhost`, `127.0.0.0/8` or `::1`). That includes a Docker Compose service name such as `rabbitmq`. RabbitMQ itself refuses `guest` from remote clients by default, so a broker that accepts it has had that protection switched off. Create a dedicated user and set `RABBITMQ_USER` and `RABBITMQ_PASSWORD`:

```bash
rabbitmqctl add_user app 'a-long-random-password'
rabbitmqctl set_permissions -p / app '.*' '.*' '.*'
```

Use `tls` for any broker reached over a network.

For `direct` and `topic` exchanges, the queue name is used as the routing key. `fanout` and `headers` exchanges use an empty routing key.

The connection opens on the first call to `channel()`. Credentials are checked earlier, when the connection is built. If the broker refuses it, a `RabbitmqException` names the host and port and points back to this config file. An unknown `exchange.type` also throws `RabbitmqException`, listing the valid types.

## Usage

### Dispatching Jobs

Use `QueueInterface` as usual --- the RabbitMQ driver handles persistent message publishing and dead-letter routing transparently:

```php
use Marko\Queue\QueueInterface;

readonly class OrderProcessor
{
    public function __construct(
        private QueueInterface $queue,
    ) {}

    public function dispatch(): void
    {
        $this->queue->push(new ProcessPayment($orderId));

        // Delay by 30 seconds using dead-letter exchange
        $this->queue->later(
            30,
            new SendReceipt($orderId),
        );
    }
}
```

## API Reference

### RabbitmqQueue

Job ids come from the injected [`JobIdGenerator`](/docs/packages/queue/#jobidgenerator) (an optional last constructor argument; the module passes the container's).

| Method | Description |
|---|---|
| `push(JobInterface $job, ?string $queue = null): PushResult` | Publish a job as a persistent AMQP message, returning a pushed `PushResult` with the job ID |
| `later(int $delay, JobInterface $job, ?string $queue = null): PushResult` | Publish a delayed job via a dead-letter exchange --- `$delay` is in seconds |
| `pop(?string $queue = null, bool $block = false): ?JobInterface` | Consume the next valid message from the queue, or `null` if empty (never blocks; `$block` is ignored) --- rejects messages it can't decode (see [Malformed Messages](#malformed-messages)) |
| `size(?string $queue = null): int` | Return the number of messages in the queue |
| `clear(?string $queue = null): int` | Purge all messages from the queue, returning the count removed |
| `delete(string $jobId): bool` | Acknowledge a consumed message by job ID |
| `release(JobInterface $job, int $delay = 0): bool` | Republish the given job with its current state (attempts, exceptions, retry deadline), optionally delayed via dead-letter exchange, wait for the broker to confirm it, then acknowledge the original message. Returns `false` when the job was not popped by this queue |

#### Malformed Messages

`pop()` rejects a message without requeueing it (`basic_reject`, `requeue: false`) when its HMAC signature doesn't match, its `job_id` header is missing, or its payload isn't a serialized job. The rejection is written to PHP's error log (STDERR for a CLI worker) with the queue, delivery tag and reason, and `pop()` moves on to the next message. One bad message --- a tampered payload, or jobs left queued across an `encryption.key` rotation --- can't crash every worker in turn.

A rejected message is dropped unless the queue has a dead-letter exchange. To keep rejected messages for inspection, add a RabbitMQ policy that dead-letters them, for example:

```bash
rabbitmqctl set_policy marko-dlx "^default$" '{"dead-letter-exchange":"marko-dead-letter"}' --apply-to queues
```

An empty `encryption.key` is a configuration error, not a bad message: `pop()` requeues the message and throws `SerializationException`, so no job is dropped because the key is missing.

#### Release and Publisher Confirms

`release()` puts the channel into publisher-confirm mode the first time it runs, publishes the retry, and waits up to 10 seconds for the broker to confirm it before it acknowledges the original message. A crash between the two steps can deliver the job twice, but never loses it. If the broker nacks the retry, the original message is requeued and `release()` throws `RabbitmqException`.

Constructor (the container autowires the connection and exchange config, and the module's `arguments` entry supplies `defaultQueue` from `queue.queue`):

```php
use Marko\Queue\Rabbitmq\RabbitmqQueue;

$rabbitmqQueue = new RabbitmqQueue(
    connection: $rabbitmqConnection,
    exchangeConfig: $exchangeConfig,
    jobEnvelope: $jobEnvelope,
    defaultQueue: 'default',
);
```

### RabbitmqConnection

| Method | Description |
|---|---|
| `__construct(string $host, int $port, string $user, string $password, string $vhost, ?array $tlsOptions)` | Create a connection --- defaults: host `localhost`, port `5672`, user `guest`, password `guest`, vhost `/`, no TLS. Throws `RabbitmqException` for `guest`/`guest` on a non-loopback host |
| `channel(): AMQPChannel` | Get the AMQP channel --- connected on first call; throws `RabbitmqException` if the connection fails |
| `disconnect(): void` | Disconnect and release the channel and connection |
| `isConnected(): bool` | Check whether the connection is currently active |

### RabbitmqFailedJobRepository

Stores failed jobs in a dedicated `failed_jobs` RabbitMQ queue as JSON messages with persistent delivery.

Reads fetch every message without acknowledging it, then requeue them all with `basic_nack(requeue: true)`. Nothing is acknowledged until the read is done, so an error partway through (such as a corrupt record) or a crashed process leaves every failed job on the queue.

| Method | Description |
|---|---|
| `store(FailedJob $failedJob): void` | Publish a failed job record to the failed jobs queue |
| `all(): array` | Retrieve all failed jobs without removing them |
| `find(string $id): ?FailedJob` | Find a specific failed job by ID |
| `delete(string $id): bool` | Acknowledge and remove the matching failed job, requeueing the rest |
| `clear(): int` | Purge all failed jobs, returning the count removed |
| `count(): int` | Return the number of failed jobs |

### ExchangeConfig

```php
use Marko\Queue\Rabbitmq\Exchange\ExchangeConfig;
use Marko\Queue\Rabbitmq\Exchange\ExchangeType;

readonly class ExchangeConfig
{
    public function __construct(
        public string $name,
        public ExchangeType $type,
        public bool $durable = true,
        public bool $autoDelete = false,
        /** @var array<string, mixed> */
        public array $arguments = [],
    ) {}
}
```

### ExchangeType

```php
enum ExchangeType: string
{
    case Direct = 'direct';
    case Fanout = 'fanout';
    case Topic = 'topic';
    case Headers = 'headers';
}
```
