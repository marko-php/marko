---
title: marko/queue-database
description: Database queue driver — stores and processes jobs in SQL tables with transaction-safe polling and failed job persistence.
---

Database queue driver --- stores and processes jobs in SQL tables with transaction-safe polling and failed job persistence. Jobs are stored in a `jobs` table and polled by the worker process. The driver uses row-level locking (via transactions when available) to prevent duplicate processing. Failed jobs are persisted to a `failed_jobs` table for later inspection and retry. Includes migrations for both tables.

Implements `QueueInterface` from [`marko/queue`](/docs/packages/queue/) and requires [`marko/database`](/docs/packages/database/) for the database connection.

## Installation

```bash
composer require marko/queue-database
```

Requires [`marko/database`](/docs/packages/database/) for the database connection.

## Usage

### Binding the Driver

Register the database queue in your module bindings:

```php title="module.php"
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
marko migrate
```

This creates:

- `jobs` --- stores pending and reserved jobs
- `failed_jobs` --- stores jobs that exceeded max attempts

### Dispatching and Processing

Use `QueueInterface` as usual --- the database driver handles persistence:

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
marko queue:work
```

## API Reference

### DatabaseQueue

Implements `QueueInterface`. Accepts a `ConnectionInterface` connection, an optional table name (defaults to `jobs`), and an optional default queue name (defaults to `default`).

| Method | Description |
|---|---|
| `push(JobInterface $job, ?string $queue = null): string` | Insert a job for immediate processing. Returns the job ID. |
| `later(int $delay, JobInterface $job, ?string $queue = null): string` | Insert a job with a delay in seconds. Returns the job ID. |
| `pop(?string $queue = null): ?JobInterface` | Retrieve and reserve the next available job, or `null` if empty. Uses transactions when the connection supports `TransactionInterface`. |
| `size(?string $queue = null): int` | Count pending (unreserved, available) jobs. |
| `clear(?string $queue = null): int` | Delete all jobs in a queue. Returns the number of deleted rows. |
| `delete(string $jobId): bool` | Delete a specific job by ID. |
| `release(string $jobId, int $delay = 0): bool` | Release a reserved job back to the queue with an optional delay. |

### DatabaseFailedJobRepository

Implements `FailedJobRepositoryInterface`. Accepts a `ConnectionInterface` connection.

| Method | Description |
|---|---|
| `store(FailedJob $failedJob): void` | Persist a failed job record. |
| `all(): array` | Retrieve all failed jobs, most recent first. |
| `find(string $id): ?FailedJob` | Find a failed job by ID, or `null` if not found. |
| `delete(string $id): bool` | Delete a single failed job record. |
| `clear(): int` | Delete all failed job records. Returns the number of deleted rows. |
| `count(): int` | Count total failed jobs. |
