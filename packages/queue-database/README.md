# Marko Queue Database

Database queue driver--stores and processes jobs in SQL tables with transaction-safe polling and failed job persistence.

## Overview

Jobs are stored in a `jobs` table and polled by the worker process. The driver uses row-level locking (via transactions when available) to prevent duplicate processing. Failed jobs are persisted to a `failed_jobs` table for later inspection and retry. Includes migrations for both tables.

## Installation

```bash
composer require marko/queue-database
```

Requires `marko/database` for the database connection.

## Usage

### Binding the Driver

Register the database queue in your module bindings:

```php
use Marko\Queue\QueueInterface;
use Marko\Queue\Database\DatabaseQueue;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\Database\DatabaseFailedJobRepository;

return [
    'bindings' => [
        QueueInterface::class => DatabaseQueue::class,
        FailedJobRepositoryInterface::class => DatabaseFailedJobRepository::class,
    ],
];
```

### Running Migrations

Run the included migrations to create the required tables:

```bash
php marko migrate
```

This creates:
- `jobs` -- stores pending and reserved jobs
- `failed_jobs` -- stores jobs that exceeded max attempts

### Dispatching and Processing

Use `QueueInterface` as usual--the database driver handles persistence:

```php
use Marko\Queue\QueueInterface;

public function __construct(
    private readonly QueueInterface $queue,
) {}

public function enqueue(): void
{
    $this->queue->push(new ProcessOrder($orderId));

    // Delay by 5 minutes
    $this->queue->later(
        300,
        new SendFollowUp($orderId),
    );
}
```

Process jobs with the worker:

```bash
php marko queue:work
```

## API Reference

### DatabaseQueue

```php
public function push(JobInterface $job, ?string $queue = null): string;
public function later(int $delay, JobInterface $job, ?string $queue = null): string;
public function pop(?string $queue = null): ?JobInterface;
public function size(?string $queue = null): int;
public function clear(?string $queue = null): int;
public function delete(string $jobId): bool;
public function release(string $jobId, int $delay = 0): bool;
```

### DatabaseFailedJobRepository

```php
public function store(FailedJob $failedJob): void;
public function all(): array;
public function find(string $id): ?FailedJob;
public function delete(string $id): bool;
public function clear(): int;
public function count(): int;
```
