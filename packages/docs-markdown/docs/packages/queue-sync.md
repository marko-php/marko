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

### Container-Aware Jobs and Async Observers

`SyncQueue` is constructed with the `JobRunnerInterface` from [`marko/queue`](/docs/packages/queue/#running-one-attempt), which the container autowires. It runs each job through that runner, exactly as the worker does: the attempt is counted, a job that implements `ContainerAwareJobInterface` gets `setContainer()` and `setJobEnvelope()` before it runs and `releaseContainer()` once `handle()` returns or throws, and an exception is counted on the job before `JobFailedException` wraps it. This is how `#[Observer(async: true)]` observers run under the sync driver. The observer is queued as an `AsyncObserverJob`, and `push()` runs it straight away against a copy of the event unwrapped from the signed envelope. If the observer throws, `dispatch()` throws a `JobFailedException`.

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
| `__construct(JobRunnerInterface $jobRunner)` | Autowired. Every job runs through the runner. |
| `push(JobInterface $job, ?string $queue = null): PushResult` | Execute the job immediately through the `JobRunner` and return a pushed `PushResult` with its ID. Container-aware jobs get the container and job envelope first, and release them afterwards. Throws `JobFailedException` on failure. |
| `later(int $delay, JobInterface $job, ?string $queue = null): PushResult` | Ignores the delay and executes immediately via `push()`. |
| `pop(?string $queue = null): ?JobInterface` | Always returns `null` --- no jobs are ever queued. |
| `size(?string $queue = null): int` | Always returns `0`. |
| `clear(?string $queue = null): int` | Always returns `0`. |
| `delete(string $jobId): bool` | Always returns `true`. |
| `release(JobInterface $job, int $delay = 0): bool` | Always returns `true`. |

`module.php` binds `QueueInterface` straight to `SyncQueue`; there is no factory.

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
