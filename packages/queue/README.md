# Marko Queue

Queue interfaces and worker infrastructure--defines how jobs are dispatched and processed, not which backend stores them.

## Overview

This package provides the contracts (`QueueInterface`, `JobInterface`, `WorkerInterface`) and the worker loop that processes jobs with automatic retries and failed job tracking. Install a driver package (`marko/queue-sync`, `marko/queue-database`, `marko/queue-rabbitmq`) for the actual backend.

## Installation

```bash
composer require marko/queue
```

Note: You also need a driver package. See `marko/queue-sync`, `marko/queue-database`, or `marko/queue-rabbitmq`.

## Usage

### Creating Jobs

Extend the `Job` base class and implement `handle()`:

```php
use Marko\Queue\Job;

class SendWelcomeEmail extends Job
{
    public function __construct(
        private readonly string $email,
    ) {}

    public function handle(): void
    {
        // Send the email...
    }
}
```

Set `maxAttempts` to control retry behavior:

```php
class ImportProducts extends Job
{
    protected(set) int $maxAttempts = 5;

    public function handle(): void
    {
        // Import logic...
    }
}
```

### Dispatching Jobs

Inject `QueueInterface` and push jobs:

```php
use Marko\Queue\QueueInterface;

public function __construct(
    private readonly QueueInterface $queue,
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
```

### Named Queues

Route jobs to specific queues:

```php
$this->queue->push(
    new SendWelcomeEmail('user@example.com'),
    'emails',
);
```

### Running the Worker

Use the CLI command to process jobs:

```bash
php marko queue:work
php marko queue:work --queue=emails
php marko queue:work --once
```

### Managing Failed Jobs

```bash
php marko queue:failed           # List failed jobs
php marko queue:retry <id>       # Retry a failed job
php marko queue:clear            # Clear all jobs from a queue
php marko queue:status           # Show queue size
```

## API Reference

### QueueInterface

```php
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
public ?string $id { get; }
public int $attempts { get; }
public int $maxAttempts { get; }
public function handle(): void;
public function setId(string $id): void;
public function incrementAttempts(): void;
public function serialize(): string;
public static function unserialize(string $data): static;
```

### WorkerInterface

```php
public function work(?string $queue = null, bool $once = false, int $sleep = 3): void;
public function stop(): void;
```

### FailedJobRepositoryInterface

```php
public function store(FailedJob $failedJob): void;
public function all(): array;
public function find(string $id): ?FailedJob;
public function delete(string $id): bool;
public function clear(): int;
public function count(): int;
```
