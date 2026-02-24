# Marko Queue Sync

Synchronous queue driver--executes jobs immediately during the current request, ideal for development and testing.

## Overview

The sync driver runs jobs inline when they are pushed, with no external dependencies or background processes. Delayed jobs execute immediately. Failed jobs throw `JobFailedException` so errors surface instantly during development. Use `marko/queue-database` or `marko/queue-rabbitmq` for production workloads.

## Installation

```bash
composer require marko/queue-sync
```

## Usage

### Automatic Operation

Bind `SyncQueue` as the `QueueInterface` implementation in your module:

```php
use Marko\Queue\QueueInterface;
use Marko\Queue\Sync\SyncQueue;

return [
    'bindings' => [
        QueueInterface::class => SyncQueue::class,
    ],
];
```

Then dispatch jobs normally--they execute synchronously:

```php
use Marko\Queue\QueueInterface;

public function __construct(
    private readonly QueueInterface $queue,
) {}

public function process(): void
{
    // Executes immediately, throws on failure
    $this->queue->push(new SendWelcomeEmail('user@example.com'));
}
```

### Failed Job Repository

The sync driver includes `NullFailedJobRepository` since jobs either succeed or throw immediately:

```php
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\Sync\NullFailedJobRepository;

return [
    'bindings' => [
        FailedJobRepositoryInterface::class => NullFailedJobRepository::class,
    ],
];
```

## API Reference

### SyncQueue

```php
public function push(JobInterface $job, ?string $queue = null): string;
public function later(int $delay, JobInterface $job, ?string $queue = null): string;
public function pop(?string $queue = null): ?JobInterface;
public function size(?string $queue = null): int;
public function clear(?string $queue = null): int;
public function delete(string $jobId): bool;
public function release(string $jobId, int $delay = 0): bool;
```

### NullFailedJobRepository

```php
public function store(FailedJob $failedJob): void;
public function all(): array;
public function find(string $id): ?FailedJob;
public function delete(string $id): bool;
public function clear(): int;
public function count(): int;
```
