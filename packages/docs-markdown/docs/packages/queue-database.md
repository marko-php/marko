---
title: marko/queue-database
description: Database queue driver — stores and processes jobs in SQL tables with transaction-safe polling and failed job persistence.
---

Database queue driver --- stores and processes jobs in SQL tables with atomic reservation and failed job persistence. Jobs are stored in a `jobs` table and polled by the worker process. Each job is claimed inside a transaction with the query builder's `lockForUpdate()->skipLocked()` (`FOR UPDATE SKIP LOCKED` on MySQL and PostgreSQL), so concurrent workers skip a job another worker is claiming instead of processing it twice. Crashed reservations (jobs reserved but neither deleted nor released within `queue.retry_after` seconds) are automatically reclaimed on the next poll cycle. Failed jobs are persisted to a `failed_jobs` table for later inspection and retry. The package ships entities for both tables, so `marko db:migrate` creates them.

Implements `QueueInterface` from [`marko/queue`](/docs/packages/queue/) and requires [`marko/database`](/docs/packages/database/) for the database connection.

## Installation

```bash
composer require marko/queue-database
```

Requires [`marko/database`](/docs/packages/database/) for the database connection. Also requires a non-empty `encryption.key` (via [`marko/encryption`](/docs/packages/encryption/)) because job payloads are HMAC-signed --- see [`marko/queue`](/docs/packages/queue/) for details.

## Usage

### Wiring

Installing the package and a database driver is enough. Its `module.php` binds `QueueInterface` to a `DatabaseQueue` built from your queue config and the driver's `QueryBuilderFactoryInterface`, and binds `FailedJobRepositoryInterface` to `DatabaseFailedJobRepository`. `marko/queue` binds `WorkerInterface`, so `marko queue:work` runs with no extra bindings.

The factory reads these keys from `config/queue.php`:

| Key | Effect on the database driver |
|---|---|
| `queue.queue` | Queue name used by `push()`, `later()` and `pop()` when none is given |
| `queue.retry_after` | Seconds before a reserved job that was neither deleted nor released is reclaimed. A queue can set its own with `queue.queues.<name>.retry_after` (see [Per-Queue Retry After](/docs/packages/queue/#per-queue-retry-after)) |
| `queue.max_attempts` | Default attempt limit for jobs that don't set their own `maxAttempts` |

Jobs are always stored in the `jobs` table (see [Creating the Tables](#creating-the-tables)).

### Creating the Tables

The package ships two entities, `DatabaseJob` and `DatabaseFailedJob` in `Marko\Queue\Database\Entity`, that own the table schemas. Create the tables with [`marko db:migrate`](/docs/packages/database/):

```bash
marko db:migrate
```

In development, `db:migrate` generates a migration for the new tables in `database/migrations/` and applies it. Commit that migration; `db:migrate` on staging and production applies it, because generation only runs in development. This creates:

| Table | Columns | Purpose |
|---|---|---|
| `jobs` | `id VARCHAR(36)` primary key, `queue VARCHAR(255)` (default `'default'`), `payload TEXT`, `attempts INT` (default `0`), `reserved_at TIMESTAMP NULL`, `available_at TIMESTAMP`, `created_at TIMESTAMP` (default `CURRENT_TIMESTAMP`); index `idx_queue_available (queue, available_at)` | Pending and reserved jobs |
| `failed_jobs` | `id VARCHAR(36)` primary key, `queue VARCHAR(255)`, `payload TEXT`, `exception TEXT`, `failed_at TIMESTAMP` (default `CURRENT_TIMESTAMP`) | Jobs that exceeded their max attempts |

The same DDL is generated on MySQL, MariaDB and PostgreSQL. The entities only own the schema: `DatabaseQueue` and `DatabaseFailedJobRepository` read and write the tables with their own SQL. Because the tables belong to entities, `db:diff` reports drift in them, and [`TruncateDatabase`](/docs/packages/testing/#truncatedatabase) empties them between tests.

#### Upgrading from the migration classes

Earlier versions shipped `CreateJobsTable` and `CreateFailedJobsTable` migration classes, which `db:migrate` never ran. They are removed. An application migration that returns one of them (`return new CreateJobsTable();`) now fails to load. Rewrite it as a migration that creates the table with the columns above. Tables created with the previously documented DDL already match the entities, so `db:migrate` generates nothing for them.

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

### Retries and Attempt Counting

The `jobs.attempts` column is the authoritative attempt count. Every reservation increments it, so it counts attempts that were *started*:

- **A job that throws** is released by the worker. `release()` stores the job the worker hands back in place of the payload it was reserved with, so its attempt count, exception count and retry deadline survive the retry. The job is retried until it has been attempted `maxAttempts` times, then moved to `failed_jobs` and deleted from `jobs`.
- **A job whose worker dies** (fatal error, OOM, `SIGKILL`) is never released. Once its reservation is older than `queue.retry_after`, the next `pop()` reclaims it, and the lost run still counts as an attempt. If the reclaimed job has already used all its attempts, `pop()` hands it to the [failed-job handler](/docs/packages/queue/#when-a-job-fails-for-good) with a `JobAttemptsExhaustedException` ("exceeded max attempts after worker crash or timeout"), so it is stored in `failed_jobs`, its `failed()` hook runs and `JobFailedEvent` is dispatched. Then `pop()` deletes it and moves on to the next job. This happens after the reserving transaction commits, so `failed()` never runs inside it. A job that always crashes its worker can't loop forever. If `failed_jobs` can't store the job, `pop()` throws a `FailedJobNotStoredException` and leaves the row reserved, so it is failed again once the reservation expires (see [When a Failed Job Cannot Be Stored](/docs/packages/queue/#when-a-failed-job-cannot-be-stored)).
- **A job that releases itself** (calls `release()` inside `handle()`) is stored back by the worker like a job that threw, but its exception count doesn't grow. Its reservation still increments `attempts`.
- **A job with a [retry deadline](/docs/packages/queue/#retry-deadlines-exception-limits-and-self-release)** is not limited by attempts, so a polling job that releases itself hundreds of times is never failed at pop time for its attempt count. `pop()` fails it with a `JobRetryDeadlinePassedException` once the deadline has passed instead.

The pop-time check asks the same `RetryPolicy` as the worker, so the precedence (deadline, then `maxExceptions`, then `maxAttempts`) is identical.

`marko queue:retry` resets the attempt and exception counts and clears the retry deadline, so a retried job gets its full `maxAttempts` again and a fresh deadline.

### Time and Testing

`DatabaseQueue` reads the current time from the injected `Psr\Clock\ClockInterface` ([`marko/clock`](/docs/packages/clock/)) for every time-dependent step: `created_at` and `available_at` on push, the `retry_after` reclaim cutoff and `reserved_at` on pop, the availability check in `size()`, the delay on `release()`, and `failedAt` for jobs that exhaust their attempts through crashed reservations.

The clock decides *when*; the database timezone decides *how it is written*. Every time the queue stores (`created_at`, `available_at`, `reserved_at`, `failed_at`) and every cutoff it compares them with is converted to `database.timezone` (UTC unless you set it, see [Datetimes and Timezones](/docs/packages/database/#datetimes-and-timezones)) before it is formatted as `Y-m-d H:i:s`. `DatabaseFailedJobRepository` reads `failed_at` back in the same zone, so `FailedJob::$failedAt` is the instant that was stored. This is the same rule entity datetimes follow, and it means:

- a web process and a worker that load different `php.ini` files (and so different PHP default timezones) agree on when a delayed job is due
- with the default UTC zone, the repeated hour of a DST fall-back never reorders jobs or delays a reclaim by an hour
- delays and `retry_after` are elapsed seconds, never wall-clock arithmetic

The clock's own timezone doesn't matter, so there is no need to bind `new SystemClock('UTC')` for the queue.

Because nothing reads the system time directly, delays and reservation expiry can be tested by moving a [`FakeClock`](/docs/packages/testing/#fakeclock) instead of sleeping:

```php
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Queue\Database\DatabaseQueue;
use Marko\Queue\RetryPolicy;
use Marko\Testing\Fake\FakeClock;

it('holds a delayed job until it is due', function (): void {
    $clock = new FakeClock('2026-10-05 12:00:00');
    $queue = new DatabaseQueue(
        $connection,
        $envelope,
        $failedJobHandler,
        $queryBuilderFactory,
        $clock,
        DatabaseTimezoneConfig::fromName('UTC'),
        new RetryPolicy($queueConfig, $clock),
    );
    $queue->later(60, new SendReport());

    $clock->travel('+59 seconds');
    expect($queue->pop())->toBeNull();

    $clock->travel('+1 second');
    expect($queue->pop())->toBeInstanceOf(SendReport::class);
});
```

A reservation becomes reclaimable once `retry_after` seconds have passed since `reserved_at`, inclusive of the boundary second.

### Upgrading: Rows Written in the Old Timezone

> **Behaviour change (breaking):** before this release the queue wrote times in the clock's timezone, which for `SystemClock` is PHP's default timezone. If that wasn't your database timezone (UTC by default), rows already in `jobs` and `failed_jobs` hold local wall-clock times that are now read as database-zone times.

Only apps whose PHP default timezone differs from `database.timezone` are affected. For them, existing rows shift by the offset between the two zones:

- Delayed jobs (`available_at`): west of UTC they become due early by the offset, east of UTC late by the offset.
- In-flight reservations (`reserved_at`): west of UTC they look older than they are and can be reclaimed while the worker is still running (double execution); east of UTC a crashed job is reclaimed late.
- `failed_at`: `queue:failed` shows old rows shifted by the offset, and ordering by `failed_at` or `created_at` mixes old and new rows until they are converted. `queue:retry` is unaffected.

Pick one before deploying:

1. **Drain the queue.** Stop the workers, let `jobs` empty (no reserved rows and no future `available_at`), deploy, then start the workers.
2. **Convert the rows.** With the workers stopped, convert the stored times from the zone they were written in (here `America/New_York`) to the database timezone:

```sql title="MySQL (needs the time zone tables loaded)"
UPDATE jobs SET
    available_at = CONVERT_TZ(available_at, 'America/New_York', '+00:00'),
    created_at = CONVERT_TZ(created_at, 'America/New_York', '+00:00'),
    reserved_at = CONVERT_TZ(reserved_at, 'America/New_York', '+00:00');
UPDATE failed_jobs SET failed_at = CONVERT_TZ(failed_at, 'America/New_York', '+00:00');
```

```sql title="PostgreSQL"
UPDATE jobs SET
    available_at = available_at AT TIME ZONE 'America/New_York' AT TIME ZONE 'UTC',
    created_at = created_at AT TIME ZONE 'America/New_York' AT TIME ZONE 'UTC',
    reserved_at = reserved_at AT TIME ZONE 'America/New_York' AT TIME ZONE 'UTC';
UPDATE failed_jobs SET failed_at = failed_at AT TIME ZONE 'America/New_York' AT TIME ZONE 'UTC';
```

If you set `database.timezone` to a zone other than UTC, use it as the target instead of `'+00:00'` / `'UTC'`.

> **MySQL note:** the jobs columns are `TIMESTAMP`, which MySQL converts from the session `time_zone` to UTC on write and back on read. The driver [pins the session to `database.timezone`](/docs/packages/database-mysql/#session-time-zone) on connect, so with the default UTC the values are stored as written, whatever the server's own zone is. If the server wasn't on UTC before that change, existing `jobs` and `failed_jobs` rows need converting; see [Upgrading from a non-UTC server](/docs/packages/database/#upgrading-from-a-non-utc-server).

### PostgreSQL and Payload Encoding

Payloads use the base64 [envelope format](/docs/packages/queue/#payload-envelope-format). Jobs with private or protected properties therefore store safely in PostgreSQL `TEXT` columns, which reject the NUL bytes that `serialize()` emits. Rows written by a release before HKDF signing subkeys no longer verify, so [drain the queue before upgrading](/docs/packages/queue/#upgrading-drain-the-queue-before-deploying).

## API Reference

### DatabaseQueue

Implements `QueueInterface`. The constructor accepts:

- a `ConnectionInterface` connection
- a `JobEnvelope`
- a `FailedJobHandlerInterface`, used to fail jobs that exhaust their attempts through crashed reservations or pass their retry deadline
- a `QueryBuilderFactoryInterface`, used to build the locking reservation query. Its builders must use the same connection as the queue, so the lock is taken inside the queue's transaction. The driver bindings already do this.
- a `Psr\Clock\ClockInterface`, the source of every timestamp the queue reads or writes
- a `DatabaseTimezoneConfig` (`database.timezone`), the zone every stored time and cutoff is converted to before it is formatted
- a `RetryPolicy`, which decides at pop time whether a reclaimed job has reached its retry deadline or used its attempts (the default `maxAttempts` comes from `queue.max_attempts`)
- an optional table name (`jobs`)
- an optional default queue name
- an optional `retryAfter` timeout in seconds
- an optional `queueRetryAfters` map of queue name to seconds, which overrides `retryAfter` for those queues
- an optional `JobIdGenerator`, which gives each pushed job its id (see [`marko/queue`](/docs/packages/queue/#jobidgenerator))

The module factory passes the container's bound clock, `DatabaseTimezoneConfig`, `RetryPolicy` and `JobIdGenerator`, and sets the default queue, `retryAfter` and `queueRetryAfters` from `queue.queue`, `queue.retry_after` and `QueueConfig::queueRetryAfters()` (the queues in `queue.queues` that set a `retry_after`).

`pop()` selects the next available job with `lockForUpdate()->skipLocked()` inside a transaction, then reserves it with an `UPDATE` guarded on `reserved_at`. A job locked by another worker is skipped rather than waited for, so multiple workers can run concurrently without claiming the same job. The connection must implement `TransactionInterface` (the MySQL and PostgreSQL drivers do). Otherwise `pop()` throws `LockException`, because the lock would be released as soon as the `SELECT` finished. Jobs whose `reserved_at` timestamp is older than `retry_after` seconds are treated as crashed and become eligible for re-reservation.

| Method | Description |
|---|---|
| `push(JobInterface $job, ?string $queue = null): PushResult` | Insert a job for immediate processing. Returns a pushed `PushResult` with the job ID. |
| `later(int $delay, JobInterface $job, ?string $queue = null): PushResult` | Insert a job with a delay in seconds. Returns a pushed `PushResult` with the job ID. |
| `pop(?string $queue = null, bool $block = false): ?JobInterface` | Retrieve and reserve the next available job, or `null` if empty. Increments the `attempts` column, syncs the job's attempt count with earlier unreleased reservations, and fails crash-exhausted jobs through the failed-job handler. Runs in a transaction (a savepoint inside a caller's transaction). Throws `LockException` on a connection without transactions. Never blocks: `$block` is ignored. |
| `size(?string $queue = null): int` | Count pending (unreserved, available) jobs. |
| `clear(?string $queue = null): int` | Delete all jobs in a queue. Returns the number of deleted rows. |
| `delete(string $jobId): bool` | Delete a specific job by ID. |
| `release(JobInterface $job, int $delay = 0): bool` | Release a reserved job back to the queue with an optional delay. Stores the given job (attempts, exceptions, retry deadline) as the new payload and its attempts in the `attempts` column. Returns `false` when no row has the job's ID. |

### DatabaseFailedJobRepository

Implements `FailedJobRepositoryInterface`. Accepts a `ConnectionInterface` connection and a `DatabaseTimezoneConfig`; `failed_at` is written and read in the database timezone.

| Method | Description |
|---|---|
| `store(FailedJob $failedJob): void` | Persist a failed job record. |
| `all(): array` | Retrieve all failed jobs, most recent first. |
| `find(string $id): ?FailedJob` | Find a failed job by ID, or `null` if not found. |
| `delete(string $id): bool` | Delete a single failed job record. |
| `clear(): int` | Delete all failed job records. Returns the number of deleted rows. |
| `count(): int` | Count total failed jobs. |
