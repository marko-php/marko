---
title: marko/queue-redis
description: Redis queue driver --- reliable Lua-based pops, delayed jobs, crash recovery and a Redis failed-job store on the Redis you already run.
---

Redis queue driver --- stores and processes jobs in Redis, on the same server you already run for [`marko/cache-redis`](/docs/packages/cache-redis/). Every pop runs as one Lua script that moves due delayed jobs and expired reservations back to the ready list, pops a job and records its reservation, atomically, so a worker crash never loses a job and two workers never get the same one. Failed jobs are stored in Redis too, so `queue:failed` and `queue:retry` work without a database.

Implements `QueueInterface` and `FailedJobRepositoryInterface` from [`marko/queue`](/docs/packages/queue/) and gets its connection from [`marko/redis`](/docs/packages/redis/). Retries, retry deadlines, exception limits, unique jobs and the failed-job hooks all come from `marko/queue`; the driver contains only the Redis storage.

## Installation

```bash
composer require marko/queue-redis
```

This installs [`marko/redis`](/docs/packages/redis/) and `predis/predis`. Job payloads are HMAC-signed, so a non-empty `encryption.key` (via [`marko/encryption`](/docs/packages/encryption/)) is required --- see [`marko/queue`](/docs/packages/queue/) for details. Install only one queue driver: each one binds `QueueInterface`.

## Configuration

The package ships `config/queue-redis.php`:

```php title="config/queue-redis.php"
use Marko\Config\Env;

return [
    'connection' => 'default',
    'prefix' => Env::string('QUEUE_REDIS_PREFIX', 'marko:'),
    'block_for' => Env::int('QUEUE_REDIS_BLOCK_FOR', 0, min: 0),
];
```

| Key | Env var | Default | Description |
|---|---|---|---|
| `connection` | --- | `default` | A connection name from [`config/redis.php`](/docs/packages/redis/#named-connections) |
| `prefix` | `QUEUE_REDIS_PREFIX` | `marko:` | Prepended to every key the driver writes |
| `block_for` | `QUEUE_REDIS_BLOCK_FOR` | `0` | Seconds a single-queue worker waits on Redis for a job to arrive; `0` turns it off. See [Blocking Pop](#blocking-pop) |

The driver also reads `config/queue.php`:

| Key | Effect on the Redis driver |
|---|---|
| `queue.queue` | Queue name used by `push()`, `later()`, `pop()`, `size()` and `clear()` when none is given |
| `queue.retry_after` | Seconds a reservation lasts. A job reserved by a worker that neither deleted nor released it is put back once it expires |
| `queue.max_attempts` | Default attempt limit for jobs that don't set their own `maxAttempts` |

To keep queue load off your cache database, add a `queue` connection to `config/redis.php` and set `'connection' => 'queue'`.

## Usage

### Dispatching and Processing

Use `QueueInterface` as with any driver:

```php
use Marko\Queue\QueueInterface;

public function __construct(
    private readonly QueueInterface $queue,
) {}

public function enqueue(): void
{
    $this->queue->push(new ProcessOrder($orderId));

    // Delay by 5 minutes
    $this->queue->later(300, new SendFollowUp($orderId));
}
```

Process jobs with the worker:

```bash
marko queue:work
```

### Data Layout

For each queue name the driver keeps:

| Key | Type | Holds |
|---|---|---|
| `{prefix}queues:{name}` | list | Ids of jobs that are ready to run, in order |
| `{prefix}queues:{name}:delayed` | sorted set | Ids of delayed and backed-off jobs, scored by the Unix time they become available |
| `{prefix}queues:{name}:reserved` | sorted set | Ids of reserved jobs, scored by the Unix time the reservation expires |
| `{prefix}queues:{name}:notify` | list | One token per ready job, which a blocking pop waits on |

Each job is a hash at `{prefix}jobs:{id}` with its `queue`, signed `payload` and `attempts`. The lists hold ids rather than payloads, so `delete()` and `release()` find a job by id alone and `release()` can replace its payload.

Failed jobs are a hash at `{prefix}failed_jobs` (id to JSON) and a sorted set at `{prefix}failed_jobs:index` (id scored by the time it failed).

The scripts build job keys from the prefix, so the driver needs a single Redis server, not Redis Cluster.

### Reliable Pop and Crash Recovery

`pop()` runs one Lua script that, atomically:

1. moves every delayed job that is due to the ready list
2. moves every reservation that has expired back to the ready list
3. pops the first ready job, adds it to the reserved set until `now + queue.retry_after`, and increments its `attempts`

A worker that dies mid-job (fatal error, OOM, `SIGKILL`) leaves the job reserved. Once `queue.retry_after` seconds have passed, the next `pop()` puts it back and another worker runs it. Keep `queue.timeout` below `retry_after` --- `queue:work` refuses to start otherwise --- so a job that is still running is never handed to a second worker.

`LMOVE` plus a separate reservation write is not atomic, which is why the driver uses a script instead.

### Retries and Attempt Counting

Attempts are counted the way [`marko/queue-database`](/docs/packages/queue-database/#retries-and-attempt-counting) counts them. Every reservation increments the job hash's `attempts`, so a run lost to a crashed worker still counts. `pop()` hands the worker the job with the attempts of the reservations before this one; the worker counts the current run.

- **A job that throws** is released by the worker. `release()` takes it off the reserved set, stores the job the worker hands back (its attempts, exception count and retry deadline), and puts it on the ready list or, with a backoff, the delayed set.
- **A job reclaimed after a crash** that has already used all its attempts is failed by `pop()` through the [failed-job handler](/docs/packages/queue/#when-a-job-fails-for-good) with a `JobAttemptsExhaustedException` ("exceeded max attempts after worker crash or timeout"), then deleted. A job past its [retry deadline](/docs/packages/queue/#retry-deadlines-exception-limits-and-self-release) is failed with the deadline error. If the failed-job store can't store the job, `pop()` throws a `FailedJobNotStoredException` and leaves it reserved, so it is failed again once the reservation expires (see [When a Failed Job Cannot Be Stored](/docs/packages/queue/#when-a-failed-job-cannot-be-stored)).

The pop-time check uses `RetryPolicy::limitReachedOnReservation()`, the same check `DatabaseQueue` uses.

### Blocking Pop

With `block_for` above `0`, a pop that finds no job waits on the queue's notify list (`BLPOP`) for up to `block_for` seconds, and takes the job as soon as one is pushed or released to it. That gives near-instant pickup without polling Redis every `--sleep` seconds.

The worker lets the driver block only when it works a single queue:

```bash
# Blocks on Redis between jobs
marko queue:work --queue=emails

# Never blocks: waiting on `high` would hold back jobs arriving on `low`
marko queue:work --queue=high,low
```

Notes:

- A delayed job that becomes due, or a reservation that expires, doesn't wake a waiting pop. The next pop picks it up, at most `block_for` seconds later.
- After a pop that blocked and found nothing, the worker still sleeps for `--sleep` seconds. With `block_for` set, `--sleep=0` is safe for a single-queue worker, because the blocking pop already waits.
- Keep `block_for` below the connection's read timeout (PHP's `default_socket_timeout`, 60 seconds by default), or the wait fails with a read error.

### Size and Clearing

`size()` counts ready jobs plus delayed jobs that are due, like `DatabaseQueue::size()`; reserved jobs are not counted. `queue:status` shows that number. `clear()` (`queue:clear`) deletes the queue's ready, delayed and reserved jobs with their job hashes and returns how many it removed.

### Time and Testing

Every time the driver stores or compares (when a delayed job is due, when a reservation expires, when a job failed) comes from the injected `Psr\Clock\ClockInterface` ([`marko/clock`](/docs/packages/clock/)), in whole Unix seconds, so every process that shares a queue needs a synchronised clock. Delays and reservation expiry can be tested by moving a [`FakeClock`](/docs/packages/testing/#fakeclock) instead of sleeping:

```php
use Marko\Testing\Fake\FakeClock;

$clock = new FakeClock('2026-10-31 12:00:00');
// ... build RedisQueue with $clock ...
$queue->later(60, new SendReport());

$clock->travel('+59 seconds');
expect($queue->pop())->toBeNull();

$clock->travel('+1 second');
expect($queue->pop())->toBeInstanceOf(SendReport::class);
```

## Errors

| Exception | When |
|---|---|
| `RedisQueueException` | `queue-redis.block_for` is negative |
| `RedisConnectionException` (marko/redis) | The configured connection name is unknown, or Redis can't be reached |
| `SerializationException` (marko/queue) | A stored payload's signature doesn't verify, for example after an `encryption.key` change |

## API Reference

### RedisQueue

Implements `QueueInterface`. Constructed with a `RedisConnectionFactoryInterface`, `RedisQueueConfig`, `QueueConfig`, `JobEnvelope`, `FailedJobHandlerInterface`, `RetryPolicy`, `Psr\Clock\ClockInterface` and [`JobIdGenerator`](/docs/packages/queue/#jobidgenerator) (job ids); the module binds it, so you don't build it yourself.

| Method | Description |
|---|---|
| `push(JobInterface $job, ?string $queue = null): PushResult` | Store a job on the ready list. Returns a pushed `PushResult` with the job ID. |
| `later(int $delay, JobInterface $job, ?string $queue = null): PushResult` | Store a job on the delayed set, available after `$delay` seconds. |
| `pop(?string $queue = null, bool $block = false): ?JobInterface` | Reserve the next ready job, or `null`. With `$block` and `block_for` above 0, waits up to `block_for` seconds for one. Fails jobs that reached a limit before this attempt. |
| `size(?string $queue = null): int` | Count ready jobs and due delayed jobs. |
| `clear(?string $queue = null): int` | Delete every ready, delayed and reserved job of the queue. Returns how many. |
| `delete(string $jobId): bool` | Delete a job wherever it is. `false` when no job has the ID. |
| `release(JobInterface $job, int $delay = 0): bool` | Store the given job and put it back as ready or delayed. `false` when the job isn't reserved. |

### RedisFailedJobRepository

Implements `FailedJobRepositoryInterface`. Constructed with a `RedisConnectionFactoryInterface` and `RedisQueueConfig`.

| Method | Description |
|---|---|
| `store(FailedJob $failedJob): void` | Store a failed job. |
| `all(): array` | All failed jobs, most recent first. |
| `find(string $id): ?FailedJob` | A failed job by ID, or `null`. |
| `delete(string $id): bool` | Delete one failed job. |
| `clear(): int` | Delete every failed job. Returns how many. |
| `count(): int` | Count failed jobs. |

### RedisQueueConfig

| Method | Description |
|---|---|
| `connection(): string` | `queue-redis.connection` |
| `prefix(): string` | `queue-redis.prefix` |
| `blockFor(): int` | `queue-redis.block_for`; throws `RedisQueueException` when negative |

## Related Packages

- [`marko/queue`](/docs/packages/queue/) --- queue contracts, worker, retries and commands
- [`marko/redis`](/docs/packages/redis/) --- the shared Redis connections this driver uses
- [`marko/queue-database`](/docs/packages/queue-database/) --- database queue driver
- [`marko/queue-rabbitmq`](/docs/packages/queue-rabbitmq/) --- RabbitMQ queue driver
