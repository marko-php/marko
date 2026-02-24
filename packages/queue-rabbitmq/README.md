# Marko Queue RabbitMQ

RabbitMQ queue driver--processes jobs through AMQP with persistent messages, exchange routing, and delayed delivery.

## Overview

Jobs are published as persistent AMQP messages through configurable exchanges (direct, fanout, topic, or headers). Delayed jobs use dead-letter exchanges for timed redelivery. Failed jobs are stored in a dedicated RabbitMQ queue for inspection and retry. Requires a running RabbitMQ server and the `php-amqplib/php-amqplib` package.

## Installation

```bash
composer require marko/queue-rabbitmq
```

## Usage

### Binding the Driver

Register the RabbitMQ queue in your module bindings:

```php
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

`RabbitmqConnection` manages the AMQP connection:

```php
use Marko\Queue\Rabbitmq\RabbitmqConnection;

$connection = new RabbitmqConnection(
    host: 'localhost',
    port: 5672,
    user: 'guest',
    password: 'guest',
    vhost: '/',
);
```

TLS is supported via the `tlsOptions` parameter:

```php
$connection = new RabbitmqConnection(
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

$exchange = new ExchangeConfig(
    name: 'marko_jobs',
    type: ExchangeType::Direct,
);
```

Available exchange types: `Direct`, `Fanout`, `Topic`, `Headers`.

### Dispatching Jobs

Use `QueueInterface` as usual:

```php
use Marko\Queue\QueueInterface;

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
```

## API Reference

### RabbitmqQueue

```php
public function push(JobInterface $job, ?string $queue = null): string;
public function later(int $delay, JobInterface $job, ?string $queue = null): string;
public function pop(?string $queue = null): ?JobInterface;
public function size(?string $queue = null): int;
public function clear(?string $queue = null): int;
public function delete(string $jobId): bool;
public function release(string $jobId, int $delay = 0): bool;
```

### RabbitmqConnection

```php
public function channel(): AMQPChannel;
public function disconnect(): void;
public function isConnected(): bool;
```

### ExchangeConfig

```php
readonly class ExchangeConfig
{
    public string $name;
    public ExchangeType $type;
    public bool $durable;
    public bool $autoDelete;
    public array $arguments;
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
