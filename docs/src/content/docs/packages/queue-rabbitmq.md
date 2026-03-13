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

This automatically installs `marko/queue` and `php-amqplib/php-amqplib`.

## Usage

### Binding the Driver

Register the RabbitMQ queue in your module bindings:

```php title="module.php"
use Marko\Queue\QueueInterface;
use Marko\Queue\Rabbitmq\RabbitmqQueue;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\Rabbitmq\RabbitmqFailedJobRepository;

return [
    'bindings' => [
        QueueInterface::class => RabbitmqQueue::class,
        FailedJobRepositoryInterface::class => RabbitmqFailedJobRepository::class,
    ],
];
```

### Configuring the Connection

`RabbitmqConnection` manages the AMQP connection. It lazily connects on the first call to `channel()`:

```php
use Marko\Queue\Rabbitmq\RabbitmqConnection;

$rabbitmqConnection = new RabbitmqConnection(
    host: 'localhost',
    port: 5672,
    user: 'guest',
    password: 'guest',
    vhost: '/',
);
```

TLS is supported via the `tlsOptions` parameter:

```php
use Marko\Queue\Rabbitmq\RabbitmqConnection;

$rabbitmqConnection = new RabbitmqConnection(
    host: 'rabbitmq.example.com',
    port: 5671,
    user: 'app',
    password: 'secret',
    tlsOptions: [
        'verify_peer' => true,
        'cafile' => '/path/to/ca.pem',
    ],
);
```

### Configuring the Exchange

Set up the exchange type and behavior:

```php
use Marko\Queue\Rabbitmq\Exchange\ExchangeConfig;
use Marko\Queue\Rabbitmq\Exchange\ExchangeType;

$exchangeConfig = new ExchangeConfig(
    name: 'marko_jobs',
    type: ExchangeType::Direct,
);
```

Available exchange types: `Direct`, `Fanout`, `Topic`, `Headers`.

For `Direct` and `Topic` exchanges, the queue name is used as the routing key. `Fanout` and `Headers` exchanges use an empty routing key.

### Dispatching Jobs

Use `QueueInterface` as usual --- the RabbitMQ driver handles persistent message publishing and dead-letter routing transparently:

```php
use Marko\Queue\QueueInterface;

class OrderProcessor
{
    public function __construct(
        private readonly QueueInterface $queue,
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

| Method | Description |
|---|---|
| `push(JobInterface $job, ?string $queue = null): string` | Publish a job as a persistent AMQP message, returning the job ID |
| `later(int $delay, JobInterface $job, ?string $queue = null): string` | Publish a delayed job via a dead-letter exchange --- `$delay` is in seconds |
| `pop(?string $queue = null): ?JobInterface` | Consume the next message from the queue, or `null` if empty |
| `size(?string $queue = null): int` | Return the number of messages in the queue |
| `clear(?string $queue = null): int` | Purge all messages from the queue, returning the count removed |
| `delete(string $jobId): bool` | Acknowledge a consumed message by job ID |
| `release(string $jobId, int $delay = 0): bool` | Reject and requeue a message --- with optional delay via dead-letter exchange |

Constructor:

```php
use Marko\Queue\Rabbitmq\RabbitmqQueue;
use Marko\Queue\Rabbitmq\RabbitmqConnection;
use Marko\Queue\Rabbitmq\Exchange\ExchangeConfig;

$rabbitmqQueue = new RabbitmqQueue(
    connection: $rabbitmqConnection,
    exchangeConfig: $exchangeConfig,
    defaultQueue: 'default',
);
```

### RabbitmqConnection

| Method | Description |
|---|---|
| `__construct(string $host, int $port, string $user, string $password, string $vhost, ?array $tlsOptions)` | Create a connection --- defaults: host `localhost`, port `5672`, user `guest`, password `guest`, vhost `/`, no TLS |
| `channel(): AMQPChannel` | Get the AMQP channel --- lazily connected on first call |
| `disconnect(): void` | Disconnect and release the channel and connection |
| `isConnected(): bool` | Check whether the connection is currently active |

### RabbitmqFailedJobRepository

Stores failed jobs in a dedicated `failed_jobs` RabbitMQ queue as JSON messages with persistent delivery.

| Method | Description |
|---|---|
| `store(FailedJob $failedJob): void` | Publish a failed job record to the failed jobs queue |
| `all(): array` | Retrieve all failed jobs without removing them |
| `find(string $id): ?FailedJob` | Find a specific failed job by ID |
| `delete(string $id): bool` | Acknowledge and remove a failed job by ID |
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
