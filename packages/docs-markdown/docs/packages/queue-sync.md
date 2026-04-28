---
title: marko/queue-sync
description: Synchronous queue driver — executes jobs immediately during the current request, ideal for development and testing.
---

Synchronous queue driver --- executes jobs immediately during the current request, ideal for development and testing. The sync driver runs jobs inline when they are pushed, with no external dependencies or background processes. Delayed jobs execute immediately. Failed jobs throw `JobFailedException` so errors surface instantly during development. Use `marko/queue-database` or `marko/queue-rabbitmq` for production workloads.

Implements `QueueInterface` from [marko/queue](/docs/packages/queue/).

## Installation

```bash
composer require marko/queue-sync
```

## Usage

### Automatic Operation

Bind `SyncQueue` as the `QueueInterface` implementation in your module:

```php title="module.php"
use Marko\Queue\QueueInterface;
use Marko\Queue\Sync\SyncQueue;

return [
    'bindings' => [
        QueueInterface::class => SyncQueue::class,
    ],
];
```

Then dispatch jobs normally --- they execute synchronously:

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

```php title="module.php"
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

Implements all methods from `QueueInterface`. See [marko/queue](/docs/packages/queue/) for the full contract.

| Method | Description |
|---|---|
| `push(JobInterface $job, ?string $queue = null): string` | Execute the job immediately and return its ID. Throws `JobFailedException` on failure. |
| `later(int $delay, JobInterface $job, ?string $queue = null): string` | Ignores the delay and executes immediately via `push()`. |
| `pop(?string $queue = null): ?JobInterface` | Always returns `null` --- no jobs are ever queued. |
| `size(?string $queue = null): int` | Always returns `0`. |
| `clear(?string $queue = null): int` | Always returns `0`. |
| `delete(string $jobId): bool` | Always returns `true`. |
| `release(string $jobId, int $delay = 0): bool` | Always returns `true`. |

### NullFailedJobRepository

A no-op implementation of `FailedJobRepositoryInterface` --- since the sync driver throws on failure, there are no failed jobs to store.

| Method | Description |
|---|---|
| `store(FailedJob $failedJob): void` | No-op. |
| `all(): array` | Always returns `[]`. |
| `find(string $id): ?FailedJob` | Always returns `null`. |
| `delete(string $id): bool` | Always returns `false`. |
| `clear(): int` | Always returns `0`. |
| `count(): int` | Always returns `0`. |
