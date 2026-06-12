---
title: marko/queue
description: Queue interfaces and worker infrastructure — defines how jobs are dispatched and processed, not which backend stores them.
---

Queue interfaces and worker infrastructure --- defines how jobs are dispatched and processed, not which backend stores them. This package provides the contracts (`QueueInterface`, `JobInterface`, `WorkerInterface`) and the worker loop that processes jobs with automatic retries and failed job tracking. Install a driver package for the actual backend.

**This package defines contracts only.** Install a driver for implementation:

- `marko/queue-sync` --- Synchronous (development/testing)
- `marko/queue-database` --- Database-backed
- `marko/queue-rabbitmq` --- RabbitMQ (production)

## Installation

```bash
composer require marko/queue
```

Note: You typically install a driver package (like `marko/queue-database`) which requires this automatically.

`marko/queue` requires [`marko/encryption`](/docs/packages/encryption/) and a non-empty `encryption.key` in your config. Job payloads are HMAC-signed when enqueued and verified before deserialization; if the key is empty or the signature does not match, a `SerializationException` is thrown loudly.

```php title="config/encryption.php"
return [
    'key' => $_ENV['APP_KEY'] ?? '',
];
```

## Usage

### Creating Jobs

Extend the `Job` base class and implement `handle()`:

```php
use Marko\Queue\Job;

readonly class SendWelcomeEmail extends Job
{
    public function __construct(
        private string $email,
    ) {}

    public function handle(): void
    {
        // Send the email...
    }
}
```

Set `maxAttempts` to control retry behavior (defaults to 3):

```php
use Marko\Queue\Job;

class ImportProducts extends Job
{
    protected(set) int $maxAttempts = 5;

    public function handle(): void
    {
        // Import logic...
    }
}
```

When a job fails and has remaining attempts, the worker releases it back to the queue with exponential backoff (`2^attempts * 10` seconds). Once all attempts are exhausted, the job is stored in the failed job repository.

### Jobs That Need Container Services

If your job needs to resolve services (like a mailer or repository) from the container at handle-time rather than serializing them, implement `ContainerAwareJobInterface`. The worker automatically injects the container before calling `handle()`:

```php
use Marko\Queue\Job;
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\JobEnvelope;
use Marko\Core\Container\ContainerInterface;

class SendOrderEmail extends Job implements ContainerAwareJobInterface
{
    private ContainerInterface $container;

    public function __construct(
        private readonly int $orderId,
        private readonly string $email,
    ) {}

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function setJobEnvelope(JobEnvelope $jobEnvelope): void {}

    public function handle(): void
    {
        $mailer = $this->container->get(MailerInterface::class);
        $mailer->send($this->email, "Order #{$this->orderId} confirmed");
    }
}
```

Store only scalar values (IDs, strings) in the job's constructor — resolve heavy services from the container in `handle()`. This avoids serializing objects that may not survive across queue backends.

### Dispatching Jobs

Inject `QueueInterface` and push jobs:

```php
use Marko\Queue\QueueInterface;

readonly class RegistrationService
{
    public function __construct(
        private QueueInterface $queue,
    ) {}

    public function register(): void
    {
        // Push for immediate processing
        $this->queue->push(new SendWelcomeEmail('user@example.com'));

        // Delay by 60 seconds
        $this->queue->later(
            60,
            new SendWelcomeEmail('user@example.com'),
        );
    }
}
```

### Named Queues

Route jobs to specific queues:

```php
use Marko\Queue\QueueInterface;

$this->queue->push(
    new SendWelcomeEmail('user@example.com'),
    'emails',
);
```

### Running the Worker

Use the CLI command to process jobs:

```bash
marko queue:work
marko queue:work --queue=emails
marko queue:work --once
```

### Managing Failed Jobs

| Command | Description |
|---------|-------------|
| `marko queue:failed` | List failed jobs |
| `marko queue:retry <id>` | Retry a failed job (resets attempt counter so the job gets its full `maxAttempts`) |
| `marko queue:clear` | Clear all jobs from a queue |
| `marko queue:status` | Show queue size |

### Configuration

Queue behavior is controlled by `config/queue.php`:

```php title="config/queue.php"
return [
    'driver'       => 'database',   // 'sync', 'database', or 'rabbitmq'
    'connection'   => 'default',
    'queue'        => 'default',
    'retry_after'  => 90,           // seconds before a reserved-but-unfinished job is reclaimed
    'max_attempts' => 3,
];
```

| Key | Default | Description |
|-----|---------|-------------|
| `driver` | `sync` | Queue backend: `sync`, `database`, or `rabbitmq` |
| `connection` | `default` | Named connection passed to the driver |
| `queue` | `default` | Default queue name |
| `retry_after` | `90` | Seconds after which a reserved job that has not been deleted or released is considered crashed and becomes eligible for re-reservation |
| `max_attempts` | `3` | How many times a job is attempted before it is moved to the failed-job store |

The `QueueConfig` class provides typed access to these values:

```php
use Marko\Queue\QueueConfig;

class MyService
{
    public function __construct(
        private QueueConfig $queueConfig,
    ) {}

    public function setup(): void
    {
        $driver = $this->queueConfig->driver();
        $connection = $this->queueConfig->connection();
        $defaultQueue = $this->queueConfig->queue();
        $retryAfter = $this->queueConfig->retryAfter();
        $maxAttempts = $this->queueConfig->maxAttempts();
    }
}
```

## API Reference

### QueueInterface

```php
use Marko\Queue\QueueInterface;
use Marko\Queue\JobInterface;

public function push(JobInterface $job, ?string $queue = null): string;
public function later(int $delay, JobInterface $job, ?string $queue = null): string;
public function pop(?string $queue = null): ?JobInterface;
public function size(?string $queue = null): int;
public function clear(?string $queue = null): int;
public function delete(string $jobId): bool;
public function release(string $jobId, int $delay = 0): bool;
```

### JobInterface

```php
use Marko\Queue\JobInterface;

public ?string $id { get; }
public int $attempts { get; }
public int $maxAttempts { get; }
public function handle(): void;
public function setId(string $id): void;
public function incrementAttempts(): void;
public function resetAttempts(): void;
public function serialize(): string;
public static function unserialize(string $data): static;
```

### WorkerInterface

```php
use Marko\Queue\WorkerInterface;

public function work(?string $queue = null, bool $once = false, int $sleep = 3): void;
public function stop(): void;
```

### FailedJobRepositoryInterface

```php
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\FailedJob;

public function store(FailedJob $failedJob): void;
public function all(): array;
public function find(string $id): ?FailedJob;
public function delete(string $id): bool;
public function clear(): int;
public function count(): int;
```

### QueueConfig

```php
use Marko\Queue\QueueConfig;

public function driver(): string;
public function connection(): string;
public function queue(): string;
public function retryAfter(): int;
public function maxAttempts(): int;
```

### ContainerAwareJobInterface

```php
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\JobEnvelope;
use Marko\Core\Container\ContainerInterface;

public function setContainer(ContainerInterface $container): void;
public function setJobEnvelope(JobEnvelope $jobEnvelope): void;
```

Implement this interface on any job class that needs to resolve services from the container when `handle()` runs. The `Worker` detects the interface and calls both setters before invoking `handle()`. Keep job constructor arguments to scalars and IDs only --- resolve services inside `handle()`.

### Exceptions

| Exception | Description |
|-----------|-------------|
| `QueueException` | Base exception for all queue errors --- includes `getContext()` and `getSuggestion()` methods |
| `JobFailedException` | Thrown when a job fails during execution |
| `SerializationException` | Thrown when a job payload cannot be serialized or deserialized, when `encryption.key` is empty, or when an HMAC signature does not match (tampered payload) |
