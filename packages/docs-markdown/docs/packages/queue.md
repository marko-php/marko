---
title: marko/queue
description: Queue interfaces and worker infrastructure — defines how jobs are dispatched and processed, not which backend stores them.
---

Queue interfaces and worker infrastructure --- defines how jobs are dispatched and processed, not which backend stores them. This package provides the contracts (`QueueInterface`, `JobInterface`, `WorkerInterface`) and the worker loop that processes jobs with automatic retries and failed job tracking. Install a driver package for the actual backend.

**This package defines contracts only.** Install a driver for implementation:

- `marko/queue-sync` --- Synchronous (development/testing)
- `marko/queue-database` --- Database-backed
- `marko/queue-redis` --- Redis (production)
- `marko/queue-rabbitmq` --- RabbitMQ (production)

## Installation

```bash
composer require marko/queue
```

Note: You typically install a driver package (like `marko/queue-database`) which requires this automatically.

[Unique jobs](#unique-jobs) need [`marko/cache`](/docs/packages/cache/) (for `LockFactoryInterface`) plus a cache driver that provides the lock store. These are jobs you opt into with `UniqueJobInterface` so that a duplicate push is skipped; ordinary jobs don't need it. `marko/queue` only suggests `marko/cache`, so install it yourself if you use unique jobs:

```bash
composer require marko/cache marko/cache-redis
```

Without it, every other job works as usual, and pushing a unique job throws a `QueueException` telling you what to install. The [cache restart signal](#workers-on-several-hosts), for workers on several hosts, needs `marko/cache` too.

`marko/queue` requires [`marko/log`](/docs/packages/log/): the worker writes its stop lines, timeouts and failures through `LoggerInterface`, so install a log handler such as [`marko/log-file`](/docs/packages/log-file/) or [`marko/log-stream`](/docs/packages/log-stream/). See [Worker Logs](#worker-logs).

`marko/queue` requires [`marko/encryption`](/docs/packages/encryption/) and a non-empty `encryption.key` in your config. Job payloads are HMAC-signed when enqueued and verified before deserialization; if the key is empty or the signature does not match, a `SerializationException` is thrown loudly.

```php title="config/encryption.php"
use Marko\Config\Env;

return [
    'key' => Env::string('APP_KEY', ''),
];
```

### Payload Envelope Format

`JobEnvelope` wraps every stored payload as `{hmac}.b64:{base64(serialize(job))}`. The 64-character hex HMAC-SHA256 covers everything after the `.` separator, including the `b64:` marker. PHP's `serialize()` writes NUL bytes for private and protected properties. Base64 keeps the payload 7-bit clean, so it fits in a PostgreSQL `TEXT` column, which can't store `\0`.

The HMAC key is not `encryption.key` itself. `JobEnvelope` derives a separate subkey with `hash_hkdf('sha256', $key, 32, 'marko-queue-envelope')`, so the key that encrypts data is never reused to sign queue payloads, and the queue subkey differs from the [cache signer's](/docs/packages/cache/#cachevaluesigner). The same envelope is used for queued jobs, failed-job payloads and `AsyncObserverJob` event data, and by every driver. A body without the `b64:` marker is rejected.

#### Upgrading: drain the queue before deploying

Releases before HKDF subkeys signed envelopes with the raw `encryption.key`, and the older `{hmac}.{raw serialized bytes}` format is no longer read. A worker on the new release rejects those payloads with `SerializationException` (signature mismatch), so the job fails instead of running. Before deploying the upgrade:

1. Stop dispatching new jobs (or put the app in maintenance mode).
2. Let the old workers run until the queue is empty.
3. Deploy, then restart the workers.

Failed jobs stored by the old release also no longer verify, so `queue:retry` can't replay them. Retry or clear them before upgrading.

#### Upgrading: per-job state, `PushResult` and `release(JobInterface)`

This release changes the queue contracts once for the per-job features built on them:

1. **Code that reads the return value of `push()` or `later()`** gets a [`PushResult`](#pushresult) instead of a string. Replace `$id = $queue->push($job);` with `$id = $queue->push($job)->id;`, and check `$result->pushed` where a skipped push matters. `WebhookQueue::push()` returns the `PushResult` too.
2. **Code that calls `QueueInterface::release()`** passes the job instead of its ID: `$queue->release($job, $delay)`. The driver stores that job as it is, so pass the job you popped and ran, not a fresh copy.
3. **Custom `QueueInterface` implementations** (drivers and test doubles) change `push()` and `later()` to return `PushResult` (`PushResult::pushed($id)`) and `release()` to take `JobInterface $job` and store it.
4. **Jobs that implement `JobInterface` directly**, without extending `Job`, add the [per-job state](#per-job-state) properties and methods, including `resetExceptions()` and `clearRetryDeadline()`. Jobs that extend `Job` need no change, and jobs already queued by the old release unserialize with the new fields at their defaults.
5. **Code that constructs `Worker`, `SyncQueue`, `DatabaseQueue` or `JobRunner` by hand** wires in the new [failed-job handler](#when-a-job-fails-for-good), an event dispatcher and the [`RetryPolicy`](#retrypolicy). `JobRunner` takes an `EventDispatcherInterface` as its third argument. `Worker` takes a `FailedJobHandlerInterface` in place of the failed-job repository, no longer takes the envelope or the clock, and takes a `RetryPolicy` in place of the optional `BackoffValidator`: `new Worker($queue, $failedJobHandler, $config, $jobRunner, new RetryPolicy($config, $clock))`. `SyncQueue` takes `($jobRunner, $failedJobHandler, $queueConfig)`, and `DatabaseQueue` takes `failedJobHandler:` in place of `failedJobRepository:` and `retryPolicy:` in place of `maxAttempts:` (the default comes from `queue.max_attempts` through the policy). Build the handler with `new FailedJobHandler($failedJobs, $envelope, $jobRunner, $eventDispatcher, $clock)`. Containers that autowire them need no change.
6. **Code that called `Worker::backoffFor()`** calls `RetryPolicy::backoffFor()` instead. It takes the same job and returns the same delay.
7. **A job that calls `release()` inside `handle()`** is now released by the worker after the delay it asked for instead of being deleted. See [Retry Deadlines, Exception Limits and Self-Release](#retry-deadlines-exception-limits-and-self-release).

#### Upgrading: worker stop reasons and per-job timeouts

1. **Custom `WorkerInterface` implementations** (and test doubles) return a [`WorkerStopReason`](#stop-reasons-and-exit-codes) from `work()` instead of `void`. `queue:work` exits with that reason's `exitCode()`.
2. **Code that constructs `Worker` by hand** passes a PSR-20 `ClockInterface` after the `RetryPolicy`: `new Worker($queue, $failedJobHandler, $config, $jobRunner, $retryPolicy, $clock)`. The worker reads `--max-time` and the start time for stop conditions from it. Containers that autowire the worker need no change.
3. **Jobs that set `$timeout`** now get it: the worker uses the job's `$timeout` instead of `--timeout` / `queue.timeout`, whether it is shorter or longer. A job whose `$timeout` is not below `queue.retry_after` is failed without running. Raise `retry_after` before deploying if any job needs a longer timeout. See [Per-Job Timeouts](#per-job-timeouts).

#### Upgrading: worker logs go through `LoggerInterface`

1. **Apps using `marko/queue`** now need a [`marko/log`](/docs/packages/log/) handler package, because the worker resolves `LoggerInterface`. Install [`marko/log-file`](/docs/packages/log-file/) (the default `file` handler) or [`marko/log-stream`](/docs/packages/log-stream/) with `LOG_HANDLERS=stream` to keep worker lines on STDERR as before. Without a handler, resolving the worker throws `NoDriverException`. See [Worker Logs](#worker-logs).
2. **Code that constructs `Worker` by hand** passes a `LoggerInterface` after the clock: `new Worker($queue, $failedJobHandler, $config, $jobRunner, $retryPolicy, $clock, $logger)`. **Code that constructs `FailedJobHandler` by hand** passes it last: `new FailedJobHandler($failedJobs, $envelope, $jobRunner, $eventDispatcher, $clock, $logger)`. Containers that autowire them need no change.

#### Upgrading: `queue:restart`

**Code that constructs `WorkCommand` by hand** passes a `RestartSignalInterface` last: `new WorkCommand($worker, $config, $processControl, $restartSignal)`. Containers that autowire the command need no change; `marko/queue` binds the interface to `FileRestartSignal` unless `queue.restart_signal_driver` is `cache`. See [Restarting Workers After a Deploy](#restarting-workers-after-a-deploy).

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

By default a job is attempted `queue.max_attempts` times (see [Configuration](#configuration)). Override `maxAttempts` on a job to give it its own limit. The property is `?int`: `null` (the default on `Job`) means "use the config value".

```php
use Marko\Queue\Job;

class ImportProducts extends Job
{
    public protected(set) ?int $maxAttempts = 5;

    public function handle(): void
    {
        // Import logic...
    }
}
```

When a job fails and has remaining attempts, the worker releases it back to the queue after a backoff delay (see [Retry Backoff](#retry-backoff)). Once all attempts are exhausted, the job is stored in the failed job repository and removed from the queue.

Attempts persist across releases: the worker hands the job it ran back to `release()`, and the driver stores that job, not the payload it was popped from. Its attempt count, exception count, retry deadline and any other state the job holds survive the retry, so a job that always throws is attempted exactly `maxAttempts` times. Drivers that track reservations (such as [`marko/queue-database`](/docs/packages/queue-database/)) also count an attempt whose worker died mid-run, so a job that crashes its worker cannot be retried forever.

### Retry Backoff

The backoff is how many seconds the worker waits before a failed job runs again. Set it per job with the `backoff` property, or for every job with `queue.backoff` in [Configuration](#configuration). The worker uses the job's value first, then the config value, then the built-in curve.

| Value | Meaning |
|-------|---------|
| `int` | A fixed delay for every retry, e.g. `30` |
| `list<int>` | The delay per attempt: the first entry after attempt 1 fails, the second after attempt 2, and so on. The last value repeats once the list runs out |
| `null` | Fall through: a job with `null` uses `queue.backoff`, and `queue.backoff => null` uses `2^attempts * 10` seconds (20, 40, 80, ...) |

```php
use Marko\Queue\Job;

class DeliverPartnerWebhook extends Job
{
    public protected(set) ?int $maxAttempts = 5;

    // 5s after attempt 1, 30s after attempt 2, 120s after attempts 3 and 4
    public protected(set) array|int|null $backoff = [5, 30, 120];

    public function handle(): void
    {
        // Call a slow partner API...
    }
}
```

Declare the property with exactly the type `array|int|null`, since PHP requires a redeclared property to keep its parent's type.

#### Invalid Backoff Values

A negative delay, an empty or keyed array, or a list with a negative or non-int entry is invalid. What happens depends on where it is set:

- **`queue.backoff` config**: `queue:work` checks it before it starts. It refuses to start, prints the `Invalid queue backoff in config queue.backoff.` error with the reason, and exits with code `1`, so the mistake shows up at deploy rather than when the first job fails.
- **A job's `backoff` property**: the worker only reads it when that job fails with attempts left. It can't release the job without a valid delay, so it fails the job instead: the job goes to the failed job repository with both its own exception and the `Invalid queue backoff in job ...` error, is deleted from the queue, and the worker moves on to the next job. One bad job class can't stop your workers, and the job's real error isn't lost.

The failed job's payload keeps the `backoff` value it was dispatched with. `marko queue:retry <id>` runs it again with that same value: fine if it succeeds, but if it throws again it goes straight back to the failed jobs. After fixing the property, dispatch the job again so the new value applies.

On a job's last attempt the backoff isn't read, so an invalid value there doesn't add a backoff error to the failed job. `RetryPolicy::backoffFor()` itself still throws `QueueException` for an invalid value.

### Per-Job State

Besides `maxAttempts` and `backoff`, every job carries this state. `Job` gives each one a default, so a job that extends it only overrides what it needs:

| Member | Default on `Job` | Meaning |
|--------|------------------|---------|
| `?int $timeout` | `null` | Seconds one attempt of this job may run, or `null` for the worker's timeout. Override with `public protected(set) ?int $timeout = 120;` |
| `?int $maxExceptions` | `null` | Attempts that may end in an exception before the job fails, or `null` for no exception limit. Override with `public protected(set) ?int $maxExceptions = 1;` |
| `int $exceptions` | `0` | Attempts that ended in an exception, counted by `incrementExceptions()`. `resetExceptions()` sets it back to `0` |
| `?DateTimeImmutable $retryDeadline` | `null` | The retry deadline captured when the job is dispatched, stored with `setRetryDeadline()`. It is not re-evaluated on later attempts. `clearRetryDeadline()` removes it |
| `?int $releaseDelay` | `null` | Seconds to wait before running again, set when `handle()` calls `release()`. `clearRelease()` resets it to `null` |

`release(int $delaySeconds = 0)` records a release request without throwing; a negative delay throws `QueueException`. `JobRunner` counts exceptions and clears a leftover release request at the start of each attempt. The worker acts on `maxExceptions`, `retryDeadline` and `releaseDelay` as described in the next section, and on `timeout` as described in [Per-Job Timeouts](#per-job-timeouts).

### Retry Deadlines, Exception Limits and Self-Release

Counting attempts is the wrong measure for a job that polls or waits on something. A job that checks an external render engine every 30 seconds may run hundreds of times while the engine is busy, and that is expected. One real exception, on the other hand, means it is broken. Three features express that.

**Self-release.** Call `release($seconds)` inside `handle()` to run the job again later without throwing. When `handle()` returns, the worker releases the job after that delay instead of deleting it. A release is not an exception, so it doesn't count toward `maxExceptions`. It is still an attempt, so without a retry deadline the job fails once it runs out of attempts.

**Retry deadline.** Implement `RetryUntilInterface` to retry until a time instead of for a number of attempts. While the deadline is ahead, the attempt limit is ignored, so the job may release itself as often as it needs:

```php
use DateTimeImmutable;
use Marko\Queue\Job;
use Marko\Queue\RetryUntilInterface;

class WaitForRender extends Job implements RetryUntilInterface
{
    // The first real exception fails the job; releases don't count
    public protected(set) ?int $maxExceptions = 1;

    public function __construct(
        private string $renderId,
    ) {}

    public function retryUntil(): DateTimeImmutable
    {
        return new DateTimeImmutable('+6 hours');
    }

    public function handle(): void
    {
        if ($this->renderIsBusy()) {
            $this->release(30);

            return;
        }

        // Store the finished render...
    }

    private function renderIsBusy(): bool
    {
        // Ask the render engine about $this->renderId...
        return false;
    }
}
```

`retryUntil()` is called **once**, when the job is pushed, and the result is stored on the job with `setRetryDeadline()`. A queue serializes the whole job, so calling it on every attempt would push "6 hours from now" forward each time and the deadline would never arrive. The capture is a `#[Before]` plugin on `QueueInterface::push()` and `later()` (`Marko\Queue\Plugin\RetryDeadlinePlugin`), so it works the same on every driver. A job that already has a deadline keeps it.

**Exception limit.** `maxExceptions` caps the attempts that end in an exception, separately from releases. `JobRunner` counts them in `exceptions`, and the count survives retries because the driver stores the job the worker hands back. A timed-out attempt counts as an exception too. With `maxExceptions = 1`, the first exception fails the job.

**Precedence.** `RetryPolicy` (see [RetryPolicy](#retrypolicy)) decides whether a job that threw or released itself runs again. The worker and the database driver's pop-time check both ask it, so the order is the same everywhere:

1. The retry deadline has passed (`now >= retryDeadline`): fail with `JobRetryDeadlinePassedException`.
2. `exceptions >= maxExceptions`: fail with `JobExceptionsExhaustedException`.
3. The job has no retry deadline and `attempts >= maxAttempts` (or `queue.max_attempts`): fail with `JobAttemptsExhaustedException`.
4. Otherwise release it after the delay it asked for with `release()`, or else its [backoff](#retry-backoff).

A job that threw is failed with its own exception, and the reason it wasn't retried is appended to the stored exception text. A job that released itself has no exception of its own, so it is failed with the limit exception. Either way it goes through [the failed-job handler](#when-a-job-fails-for-good), so `failed()` runs and `JobFailedEvent` is dispatched. The worker reads the time through the injected `Psr\Clock\ClockInterface`, so a [`FakeClock`](/docs/packages/testing/#fakeclock) controls deadlines in tests.

`queue:retry` resets the attempt and exception counts and clears the retry deadline and any release request, so a retried `RetryUntilInterface` job gets a fresh deadline when it is pushed again. The [sync driver](/docs/packages/queue-sync/) runs a job once, right away, so it ignores a release request.

#### Implementing `JobInterface` Directly

Extending `Job` is the recommended way to write a job. A class that implements `JobInterface` itself must declare every property and method of the interface (see [JobInterface](#jobinterface)), including the per-job state above:

```php
use DateTimeImmutable;
use Marko\Queue\JobInterface;

class CustomJob implements JobInterface
{
    public private(set) ?string $id = null;
    public private(set) int $attempts = 0;
    public private(set) ?int $maxAttempts = null;
    public private(set) array|int|null $backoff = null;
    public private(set) ?int $timeout = null;
    public private(set) ?int $maxExceptions = null;
    public private(set) int $exceptions = 0;
    public private(set) ?DateTimeImmutable $retryDeadline = null;
    public private(set) ?int $releaseDelay = null;

    public function handle(): void
    {
        // ...
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function incrementAttempts(): void
    {
        $this->attempts++;
    }

    public function resetAttempts(): void
    {
        $this->attempts = 0;
    }

    public function incrementExceptions(): void
    {
        $this->exceptions++;
    }

    public function resetExceptions(): void
    {
        $this->exceptions = 0;
    }

    public function setRetryDeadline(DateTimeImmutable $deadline): void
    {
        $this->retryDeadline = $deadline;
    }

    public function clearRetryDeadline(): void
    {
        $this->retryDeadline = null;
    }

    public function release(int $delaySeconds = 0): void
    {
        $this->releaseDelay = $delaySeconds;
    }

    public function clearRelease(): void
    {
        $this->releaseDelay = null;
    }

    public function serialize(): string
    {
        return serialize($this);
    }

    public static function unserialize(string $data): static
    {
        return unserialize($data);
    }
}
```

### Jobs That Need Container Services

If your job needs to resolve services (like a mailer or repository) from the container at handle-time rather than serializing them, implement `ContainerAwareJobInterface`. The worker injects the container before calling `handle()`, and calls `releaseContainer()` afterwards:

```php
use Marko\Queue\Job;
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\JobEnvelope;
use Marko\Core\Container\ContainerInterface;

class SendOrderEmail extends Job implements ContainerAwareJobInterface
{
    private ?ContainerInterface $container = null;

    public function __construct(
        private readonly int $orderId,
        private readonly string $email,
    ) {}

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function setJobEnvelope(JobEnvelope $jobEnvelope): void {}

    public function releaseContainer(): void
    {
        $this->container = null;
    }

    public function handle(): void
    {
        $mailer = $this->container->get(MailerInterface::class);
        $mailer->send($this->email, "Order #{$this->orderId} confirmed");
    }
}
```

Store only scalar values (IDs, strings) in the job's constructor — resolve heavy services from the container in `handle()`. This avoids serializing objects that may not survive across queue backends.

`releaseContainer()` must drop everything `setContainer()` and `setJobEnvelope()` stored, so the job holds only serializable data again. `JobRunner` (see [Running One Attempt](#running-one-attempt)) calls it in a `finally` block around `handle()`, so it runs when the job succeeds and when it throws. That matters on the job's last attempt: the worker serializes the failed job into the failed-job store, and the container holds closures that cannot be serialized. Make `releaseContainer()` safe to call more than once, and before anything was injected.

### When a Failed Job Cannot Be Serialized

If a job that has used up its attempts still can't be serialized (for example, it holds a closure, a resource or a live connection), the worker records it anyway and keeps running:

- The failed job's payload is a placeholder, `['class' => <job class>, 'serialization_error' => <message>]`, signed with the same envelope as every other payload. `queue:failed` shows the job class from it.
- The failed job's `exception` text holds the job's own exception and trace, followed by `Job payload could not be serialized, so this failed job cannot be retried.` with the job class and the serialization error.
- The job is deleted from the queue, so the next worker doesn't pick it up again.

`queue:retry` refuses a placeholder (and any payload that isn't a queue job): it prints why, leaves the row in the failed-job store, and returns exit code 1. Fix the job so it holds only serializable data, then dispatch it again.

### Running One Attempt

`JobRunnerInterface` (bound to `JobRunner`) runs one attempt of a job. The worker and the [sync driver](/docs/packages/queue-sync/) both run jobs through it, so the attempt lifecycle is written once:

1. A `ContainerAwareJobInterface` job gets the container and the `JobEnvelope`.
2. Any release request left over from an earlier attempt is cleared, and `attempts` is incremented.
3. `JobProcessingEvent` is dispatched.
4. `handle()` runs. If it throws, `incrementExceptions()` is called and the exception is rethrown. If it returns, `JobProcessedEvent` is dispatched.
5. A container-aware job's `releaseContainer()` runs, whether `handle()` returned or threw.

`withContainer(JobInterface $job, callable $callback): mixed` does steps 1 and 5 around any callback and returns its result. Use it to call something else on a container-aware job with the container in place.

### When a Job Fails for Good

A job fails for good when its last attempt throws or times out, when it reaches its retry deadline or `maxExceptions` (see [Retry Deadlines, Exception Limits and Self-Release](#retry-deadlines-exception-limits-and-self-release)), when its backoff is invalid so it can't be retried, when the [database driver](/docs/packages/queue-database/#retries-and-attempt-counting) finds its attempts used up by workers that crashed, and when it throws under the [sync driver](/docs/packages/queue-sync/). Every one of those paths goes through `FailedJobHandlerInterface` (bound to `FailedJobHandler`), which in order:

1. stores the job in the failed-job store, with its exception and an [argument-free trace](#failed-job-traces)
2. calls `failed()` if the job implements `FailableJobInterface`
3. dispatches `JobFailedEvent`

The caller then deletes the job from its queue.

#### When a Failed Job Cannot Be Stored

If the failed-job repository throws while storing the job (the `failed_jobs` table is missing, the database is down), the failure isn't recorded anywhere, so `FailedJobHandler` throws a `FailedJobNotStoredException` (a `QueueException`) instead of logging and carrying on. Its message names the job class and id. Its context names the queue, the job's own exception and the storage error, and the storage error is its `getPrevious()`. Then:

- `failed()` isn't called and `JobFailedEvent` isn't dispatched, so `failed()` never runs twice for one job.
- The caller doesn't delete the job. It stays in its queue (still reserved, for drivers that reserve), so once its reservation expires (`queue.retry_after`) it is popped again and failed again, this time stored if the store works.

How each caller reacts:

| Caller | What happens |
|--------|--------------|
| Worker (last attempt, timeout limits, invalid backoff, retry limits) | The worker stops. It [logs](#worker-logs) the exception at `critical` level (`Worker stopping: ...`, with the job, the cause and the suggestion) and throws it out of `work()`, so `queue:work` prints the error and exits with status `1`. It doesn't pull more jobs while the store is broken: every later final failure would fail the same way. Your supervisor restarts the worker; fix the store and the job is failed again on its own. |
| Worker, job timeout | The exception is logged at `critical` level instead of thrown, because the timeout runs inside the alarm signal handler, and the worker exits with status `1` as it does after every timeout. |
| Database and Redis drivers, failing a job at pop time | `pop()` throws the exception and leaves the job reserved. The worker treats it like the case above: it stops instead of logging a [pop failure](#running-the-worker) and polling again. |
| Sync driver | `push()` throws the `FailedJobNotStoredException` instead of `JobFailedException`. `NullFailedJobRepository` never throws, so this only happens when you bind your own repository. |

Implement `FailableJobInterface` to put domain state back when a job gives up, so records don't stay stuck in an in-progress state:

```php
use Marko\Queue\FailableJobInterface;
use Marko\Queue\Job;
use Throwable;

class RegenerateTranscript extends Job implements FailableJobInterface
{
    public function __construct(
        private int $videoId,
    ) {}

    public function handle(): void
    {
        // ... regenerate the transcript
    }

    public function failed(Throwable $exception): void
    {
        // Put the video back to "needs review" so it doesn't stay "regenerating" forever
    }
}
```

- `failed()` runs once, on the final failure only. It doesn't run on an attempt that is released for a retry, and `queue:retry` doesn't call it.
- A `ContainerAwareJobInterface` job has its container while `failed()` runs, through `JobRunner::withContainer()`, so it can resolve the same services as `handle()`.
- If `failed()` throws, the error is [logged](#worker-logs) at `error` level with the job class and id, `JobFailedEvent` is still dispatched, and the worker keeps running.
- On a [timeout](#worker-limits-and-shutdown), `failed()` runs inside the alarm signal handler, just before the worker process exits. Keep it short.
- Under the database driver, `failed()` gets a `JobAttemptsExhaustedException`, because no exception was thrown: the workers died.

#### Job Lifecycle Events

| Event | Dispatched by | When | Properties |
|-------|---------------|------|------------|
| `Marko\Queue\Event\JobProcessingEvent` | `JobRunner` | after the attempt is counted, just before `handle()` | `job`, `jobClass`, `jobId` |
| `Marko\Queue\Event\JobProcessedEvent` | `JobRunner` | after `handle()` returns without throwing | `job`, `jobClass`, `jobId` |
| `Marko\Queue\Event\JobFailedEvent` | `FailedJobHandler` | after the job is stored and `failed()` has run | `job`, `jobClass`, `jobId`, `queue`, `exception` |

The worker and the sync driver both run jobs through `JobRunner`, so the processing events fire for every driver. Observe them with a regular `#[Observer]`. An observer of `JobProcessingEvent` or `JobProcessedEvent` that throws fails the attempt, like an exception from `handle()`. Keep those observers synchronous: an async observer queues a job, and running that job fires the event again, without end.

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

`push()` and `later()` return a `PushResult`. When the job was queued, `$result->pushed` is `true` and `$result->id` holds the job ID. When it was not, `$result->pushed` is `false`, `$result->id` is `null`, and `$result->skipReason` says why. The built-in drivers always queue the job; a push is skipped by a `#[Before]` plugin on `push()` that returns `PushResult::skipped('...')`, such as the one behind [unique jobs](#unique-jobs):

```php
$result = $this->queue->push(new SendWelcomeEmail('user@example.com'));

if ($result->pushed) {
    $this->logger->info("Queued job $result->id");
}
```

### Unique Jobs

"Unique" here describes a kind of job you opt into. It doesn't mean every job is different. Every pushed job gets its own id, and by default the same work can be queued as many times as you push it: push `SendInvoiceEmail(42)` twice and it runs twice. That's the right behaviour for most jobs.

Some work should never be queued twice at once, such as re-indexing a product or regenerating a report. A job that implements `Marko\Queue\UniqueJobInterface` is queued at most once per unique id. A second push while the first is pending returns a skipped `PushResult` and queues nothing, so a double click, a scheduler that fires again or a retry that re-dispatches can't start the same work twice.

Only these jobs need [`marko/cache`](/docs/packages/cache/). Checking "is a copy already queued?" safely across processes takes an atomic lock, and the lock comes from `marko/cache`. `marko/queue` only suggests that package, so install it (plus a lock-capable driver, ideally [`marko/cache-redis`](/docs/packages/cache-redis/) across servers) before you use unique jobs:

```bash
composer require marko/cache marko/cache-redis
```

```php
use Marko\Queue\Job;
use Marko\Queue\UniqueJobInterface;
use Marko\Queue\UniqueUntil;

class ProposeStyleJob extends Job implements UniqueJobInterface
{
    public function __construct(
        public readonly int $studioId,
    ) {}

    public function handle(): void
    {
        // ...
    }

    public function uniqueId(): string
    {
        return (string) $this->studioId;
    }

    public function uniqueFor(): int
    {
        return 3600; // the lock expires after one hour at most
    }

    public function uniqueUntil(): UniqueUntil
    {
        return UniqueUntil::Processed;
    }
}
```

```php
$this->queue->push(new ProposeStyleJob(42)); // pushed
$result = $this->queue->push(new ProposeStyleJob(42)); // skipped

$result->pushed;     // false
$result->skipReason; // Unique job lock "unique-job:App\Jobs\ProposeStyleJob:42" is held: ...
```

Pushing takes the atomic lock `unique-job:{class}:{uniqueId}` through [`LockFactoryInterface`](/docs/packages/cache/#atomic-locks), and the lock's owner token travels in the job payload (`$uniqueLockOwner`), so whichever process runs the job can release it. `uniqueUntil()` decides when:

| `UniqueUntil` | Lock released | A copy can be queued |
|---------------|---------------|----------------------|
| `Processing` | when a worker starts the job (`JobProcessingEvent`) | while the job runs |
| `Processed` | when the job completes (`JobProcessedEvent`) or fails for the last time (`JobFailedEvent`) | only after it finishes |

- A retry is not a final failure, so the lock stays held while the job waits for its next attempt. A `Processed` job that calls `release()` from `handle()` keeps its lock too.
- Every final failure goes through `FailedJobHandler`, so the lock is released for a job that runs out of attempts in the worker, times out (before the worker exits), is failed at pop time by the database driver after crashed reservations, or fails in the sync driver.
- `uniqueFor()` is the lock's lifetime in seconds and must be greater than 0, or the push throws a `QueueException`: a lock that never expires would block the job forever after a crash. Once it expires, the job can be pushed again even if the first copy never ran. The same happens when the driver throws while storing the job: the lock was already taken, so it stays held until it expires.
- Uniqueness lives in `marko/queue`, as the `UniqueJobPushPlugin` (a `#[Before]` plugin on `QueueInterface::push()` and `later()`, with `sortOrder` 100 so it takes the lock after `RetryDeadlinePlugin` and the other push plugins have run) and three lifecycle observers, so every driver gets it with no driver code. Code that builds a driver by hand, outside the container, skips the plugin.
- The lock reaches as far as the lock store does: install [`marko/cache-redis`](/docs/packages/cache-redis/) when workers or web servers run on more than one host. [`marko/cache-file`](/docs/packages/cache-file/) locks one host and [`marko/cache-array`](/docs/packages/cache-array/) one process.
- Without `marko/cache` installed, pushing a unique job throws a `QueueException` telling you to `composer require marko/cache marko/cache-redis`. With `marko/cache` but no lock store bound, it throws a `QueueException` naming `LockFactoryInterface` and the cache drivers that provide one. Uniqueness never quietly turns off. Jobs that aren't unique never touch the lock store, so an app without `marko/cache` can still queue them.
- A job that implements `JobInterface` directly, without extending `Job`, also declares `public ?string $uniqueLockOwner { get; }` and `setUniqueLockOwner(string $owner): void`.

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
marko queue:work --queue emails     # or --queue=emails
marko queue:work --once
marko queue:work --queue=high,default,low --sleep=1
```

| Option | Description |
|--------|-------------|
| `--queue` | Queue names to work, in priority order, separated by commas. Defaults to the `queue.queue` config value |
| `--sleep` | Seconds to wait when every queue is empty (default `3`) |
| `--once` | Process at most one job, then exit |
| `--timeout` | Seconds one job may run before it is failed and the worker exits. Defaults to the `queue.timeout` config value (`60`); `0` turns it off. A job's own `$timeout` replaces it. Needs `ext-pcntl` |
| `--memory` | Exit after the current job once the worker uses this many megabytes (default `0`, no limit) |
| `--max-jobs` | Exit after processing this many jobs (default `0`, no limit) |
| `--max-time` | Exit after the current job once the worker has run this many seconds (default `0`, no limit) |

If popping a job throws (for example, the broker is unreachable), the worker [logs](#worker-logs) the error, sleeps for `--sleep` seconds and polls again, so a pop failure doesn't stop the worker. With `--once` the error is thrown instead. The one exception is a `FailedJobNotStoredException`, thrown when a driver fails a job at pop time and can't store it: the worker [stops](#when-a-failed-job-cannot-be-stored).

#### Worker Logs

The worker and `FailedJobHandler` log through Marko's `LoggerInterface` from [`marko/log`](/docs/packages/log/), so worker output goes wherever your `log.handlers` send it: `storage/logs` with [`marko/log-file`](/docs/packages/log-file/) (the default handler), or STDERR/STDOUT with [`marko/log-stream`](/docs/packages/log-stream/). Under a process supervisor or in a container, `LOG_HANDLERS=stream` puts worker logs in the supervisor's output; `LOG_HANDLERS=file,stream` writes both. Every line starts with `[marko/queue]`:

| Level | Line |
|-------|------|
| `info` | `Worker stopping: ...`, once per run, with the [stop reason](#stop-reasons-and-exit-codes) |
| `warning` | A job sets a timeout but `ext-pcntl` is not loaded, so it can't be enforced |
| `error` | A pop failed and the worker will retry, a job exceeded its timeout, a timed-out job could not be released or failed, or a job's `failed()` threw |
| `critical` | `Worker stopping: ...`, when a failed job [could not be stored](#when-a-failed-job-cannot-be-stored) |

To send worker logs somewhere else, bind your own `LoggerInterface` implementation or add a handler (see [`marko/log`](/docs/packages/log/)). In tests, pass a `FakeLogger` from [`marko/testing`](/docs/packages/testing/) to `Worker` and `FailedJobHandler` and assert on its entries.

### Worker Limits and Shutdown

Run `queue:work` under a process supervisor (systemd, Supervisor, a container restart policy) and let it recycle workers:

```bash
marko queue:work --timeout=60 --memory=128 --max-jobs=1000 --max-time=3600
```

- **Timeout** --- when a job runs longer than its timeout (the job's own `$timeout`, or `--timeout`), the worker fails that attempt with a `JobTimedOutException` (`Job '...' exceeded the 60-second timeout.`). The job is released for another attempt with its backoff, or [failed for good](#when-a-job-fails-for-good) on its last attempt, like a job that threw. The worker then logs the timeout and exits with status `1` (`Worker::EXIT_TIMED_OUT`): a job stuck in a blocking call can't be stopped safely inside the process, so the supervisor starts a fresh one.
- **Memory, job count and run time** --- with `--memory`, `--max-jobs` or `--max-time`, the worker finishes the current job, then returns and `queue:work` exits with status `0`, so leaks never build up to an out-of-memory crash and every worker is recycled regularly. These limits are checked after every job and after every idle pause, so an idle worker also exits once `--max-time` has passed.
- **SIGTERM / SIGINT** --- the worker finishes the job it is running, then exits, so a deploy or `docker stop` doesn't cut a job off halfway.

The timeout must be shorter than `queue.retry_after`. Otherwise a job still running when its reservation expires is handed to a second worker and runs twice, so `queue:work` refuses to start and exits with code `1` when `--timeout` (or `queue.timeout`) is not below `retry_after`. Leave a few seconds of margin.

The timeout and graceful shutdown need the `pcntl` extension. Without it, `queue:work` prints a warning and runs with **no** per-job timeout, and SIGTERM/SIGINT stop the worker immediately, mid-job. `--memory`, `--max-jobs` and `--max-time` work without `pcntl`.

From code, pass the limits as `WorkerOptions` (every limit defaults to `0`, meaning off):

```php
use Marko\Queue\WorkerOptions;

$reason = $worker->work(
    queues: ['default'],
    options: new WorkerOptions(timeout: 60, memory: 128, maxJobs: 1000, maxTime: 3600),
);
```

#### Per-Job Timeouts

A job that needs a different timeout from the rest sets `$timeout`. The worker uses it instead of `--timeout` / `queue.timeout`, whether it is shorter or longer; `0` turns the timeout off for that job, and `null` (the default) uses the worker's value:

```php
use Marko\Queue\Job;

class RenderVideo extends Job
{
    public protected(set) ?int $timeout = 1800;

    public function handle(): void
    {
        // ...
    }
}
```

A job's timeout must also stay below `queue.retry_after`, for the same reason as the worker's: a job still running when its reservation expires is handed to a second worker. `queue:work` only knows the worker's timeout when it starts, so the worker checks each job's own `$timeout` when it pops the job. A job whose `$timeout` is not below `retry_after` is [failed for good](#when-a-job-fails-for-good) without running, with a `QueueException` that says to raise `queue.retry_after`. Raise `retry_after` above your longest job timeout (with a few seconds of margin), then `queue:retry` the job.

Without `ext-pcntl`, a job that sets a timeout still runs, but the worker [logs](#worker-logs) a warning each time, because the timeout can't be enforced.

#### Stop Reasons and Exit Codes

Every way the worker stops goes through one mechanism. `work()` returns a `WorkerStopReason`, the worker [logs](#worker-logs) one `info` line, such as `[marko/queue] Worker stopping: ran for the --max-time limit.`, and `queue:work` exits with the reason's `exitCode()`:

| Reason | When | Exit code |
|--------|------|-----------|
| `Once` | `--once` processed one job, or found every queue empty | `0` |
| `MaxJobs` | The worker processed `--max-jobs` jobs | `0` |
| `Memory` | Memory reached `--memory` megabytes | `0` |
| `MaxTime` | The worker ran for `--max-time` seconds | `0` |
| `RestartRequested` | A restart of every worker was requested | `0` |
| `Signal` | SIGTERM or SIGINT arrived (or `stop()` was called) | `0` |
| `TimedOut` | A job exceeded its timeout | `1` (`Worker::EXIT_TIMED_OUT`) |

A failed job that [can't be stored](#when-a-failed-job-cannot-be-stored) isn't a stop reason: `work()` throws the `FailedJobNotStoredException`, and `queue:work` exits with status `1`.

The memory, job-count and run-time limits are stop conditions: classes that implement `WorkerStopConditionInterface`. `WorkerOptions::buildStopConditions()` builds `MaxJobsStopCondition`, `MemoryStopCondition` and `MaxTimeStopCondition` from the limits that are on. The worker checks every condition after each job and after each idle pause, with a `WorkerRunState` (when the worker started, the jobs processed so far and the memory in use), and stops with the first reason a condition returns.

To stop on something else, implement the interface and pass it through `stopConditions`. Conditions run in order after the built-in ones:

```php
use Marko\Queue\WorkerOptions;
use Marko\Queue\WorkerRunState;
use Marko\Queue\WorkerStopConditionInterface;
use Marko\Queue\WorkerStopReason;

class MaintenanceStopCondition implements WorkerStopConditionInterface
{
    public function check(
        WorkerRunState $state,
    ): ?WorkerStopReason {
        return file_exists('/var/run/app-maintenance') ? WorkerStopReason::RestartRequested : null;
    }
}

$worker->work(options: new WorkerOptions(stopConditions: [new MaintenanceStopCondition()]));
```

The worker reaches signals, the timer, memory usage and process exit through `ProcessControlInterface`, bound to `PcntlProcessControl`. Bind your own implementation to change how the worker handles them.

#### Restarting Workers After a Deploy

A running `queue:work` keeps the code it booted with, so after a deploy every worker must be replaced. `queue:restart` asks every worker to exit once its current job is done:

```bash
marko queue:restart
# Restart signal sent; workers will exit after their current job.
```

`queue:restart` records a new random token through `RestartSignalInterface`. `queue:work` reads the current token when it starts and passes a `RestartSignalStopCondition` to the worker through `WorkerOptions::$stopConditions`. Like the other [stop conditions](#stop-reasons-and-exit-codes), it is checked after every job and after every idle pause. Once the token differs from the one the worker started with, the worker stops with `RestartRequested`, logs `[marko/queue] Worker stopping: a worker restart was requested.`, and `queue:work` exits with status `0`. A busy worker finishes its job first; an idle one exits after its current `--sleep` pause.

Workers compare tokens, not timestamps: a restart requested before a worker started never stops it, and clocks that differ between hosts don't matter.

The process supervisor then starts a fresh worker on the new code. With Supervisor, `autorestart=true` restarts it whatever the exit status:

```ini title="/etc/supervisor/conf.d/marko-worker.conf"
[program:marko-worker]
command=php /var/www/app/vendor/bin/marko queue:work --max-time=3600
directory=/var/www/app
process_name=%(program_name)s_%(process_num)02d
numprocs=4
autorestart=true
stopwaitsecs=90
user=www-data
```

With systemd, use `Restart=always`. Then run `queue:restart` as the last step of the deploy, once the new code is in place:

```bash
# ...copy the new release into place...
composer install --no-dev --optimize-autoloader
php vendor/bin/marko queue:restart
```

`queue.restart_signal_driver` picks where the token is stored. It is `file` by default; set `QUEUE_RESTART_SIGNAL_DRIVER` to change it:

| Driver | Implementation | Reaches |
|--------|----------------|---------|
| `file` | `FileRestartSignal` | Workers on the host where `queue:restart` ran, or on hosts that share the directory |
| `cache` | `CacheRestartSignal` | Every worker that reads the same cache store, so every host when the store is [`marko/cache-redis`](/docs/packages/cache-redis/) |

Any other value fails loudly: resolving `RestartSignalInterface` (so `queue:work` and `queue:restart`) throws a `QueueException` naming the valid drivers. There is no fallback from one driver to the other.

`FileRestartSignal` writes the token to the file named by `queue.restart_signal` (`storage/queue/restart` under the project root by default; set `QUEUE_RESTART_SIGNAL` to change it). It writes to a temp file and renames it into place, so a worker never reads a half-written token. Run `queue:restart` as a user that can create and write that file, and run the workers as a user that can read it.

##### Workers on several hosts

**The file signal only reaches workers that see the same file.** When workers run on hosts that share no filesystem, store the token in a shared cache instead. Install [`marko/cache`](/docs/packages/cache/) with a driver every host reaches, then pick the cache driver:

```bash
composer require marko/cache marko/cache-redis
```

```bash title=".env"
QUEUE_RESTART_SIGNAL_DRIVER=cache
```

Set it on every host, both where `queue:restart` runs and where the workers run. `CacheRestartSignal` stores the token through `CacheInterface::forever()` under the key `queue.restart_signal` (`CacheRestartSignal::KEY`), so it never expires, and a `queue:restart` on any host reaches the workers on all of them. Each host's cache config must point at the same store (for `marko/cache-redis`, the same Redis server, database and `cache-redis.prefix`). [`marko/cache-file`](/docs/packages/cache-file/) only shares the token on one host and [`marko/cache-array`](/docs/packages/cache-array/) never outlives the process, so neither helps across hosts.

`marko/queue` only suggests `marko/cache`. With the `cache` driver chosen but `marko/cache` missing, `queue:work` and `queue:restart` throw a `QueueException` telling you to `composer require marko/cache marko/cache-redis` or switch back to `file`. With `marko/cache` but no cache driver bound, they throw a `QueueException` that names the cache drivers to install. With the `file` driver, `marko/cache` is never touched.

For another store (a database table, say), implement `RestartSignalInterface` yourself and bind it in your module's `module.php` or with a Preference.

### Failed Job Traces

The `exception` text stored for a failed job holds the exception message and a stack trace in `getTraceAsString()` format, **without call arguments**. Arguments can hold passwords or tokens, and with `zend.exception_ignore_args=Off` (the PHP development default) `getTraceAsString()` would include them, so `FailedJobHandler` builds the trace from file, line, class and function only.

### Queue Priority

Give `--queue` several names to work them in priority order from one worker process:

```bash
marko queue:work --queue=high,default,low
```

On every pass the worker pops `high` first, then `default`, then `low`, and processes the first job it finds. After each job it starts again at `high`, so a lower queue only runs when every queue ahead of it is empty. The worker sleeps only when all listed queues are empty. With `--once`, it processes at most one job across all the queues.

The worker lets the driver block (`pop($queue, block: true)`) only when it works a single queue. A driver with blocking pops, such as [`marko/queue-redis`](/docs/packages/queue-redis/#blocking-pop) with `block_for` set, then waits for a job to arrive instead of returning straight away. With several queues it never blocks, because waiting on one queue would hold back jobs arriving on the others. Drivers without blocking pops ignore the flag.

A failed job is recorded with the queue it was popped from, so `queue:failed` shows `low` for a job that failed on the `low` queue.

To work queues from code, pass the list to the worker:

```php
use Marko\Queue\WorkerInterface;

$worker->work(queues: ['high', 'default', 'low']);
```

`marko/queue` binds `WorkerInterface` to the built-in `Worker`, so `queue:work` resolves once a driver is installed. You don't need to bind it yourself. To replace the worker, bind `WorkerInterface` to your own class in your module's `module.php`, or use a Preference on `Worker`.

### Async Observers

`marko/queue` binds `Marko\Core\Event\AsyncObserverDispatcherInterface` to `QueueAsyncObserverDispatcher`. Installing the package and a driver is all it takes for `#[Observer(async: true)]` observers to be queued instead of run during the request:

```php
use Marko\Core\Attributes\Observer;

#[Observer(event: OrderShipped::class, async: true)]
class SendShippingEmail
{
    public function handle(OrderShipped $event): void
    {
        // Runs when queue:work processes the AsyncObserverJob
    }
}
```

When an async observer fires, `QueueAsyncObserverDispatcher` serializes the event, wraps it in a `JobEnvelope` and pushes an `AsyncObserverJob` onto the default queue. The worker verifies the envelope, resolves the observer from the container and calls `handle()` with the event. The binding is resolved the first time an async observer fires, so requests that dispatch none never open a queue connection. An event that can't be serialized throws `SerializationException` at dispatch time. Without `marko/queue`, an async observer throws an `EventException` rather than running inline. See [Events](/docs/concepts/events/#async-observers).

### Managing Failed Jobs

| Command | Description |
|---------|-------------|
| `marko queue:failed` | List failed jobs |
| `marko queue:retry <id>` | Retry a failed job (resets its attempt and exception counts so it gets its full `maxAttempts` and `maxExceptions`, and clears its retry deadline so a fresh one is captured on push). When the push is skipped, for example because a copy of a [unique job](#unique-jobs) is already queued, the failed-job row is kept, the skip reason is printed and the command returns exit code 1 |
| `marko queue:retry --all` | Retry every failed job. Rows whose payload could not be serialized, and rows whose push was skipped, are kept and reported, and the command returns exit code 1 if any were skipped |
| `marko queue:clear` | Clear all jobs from a queue |
| `marko queue:status` | Show queue size |

### Time and Testing

`FailedJobHandler` reads the current time from the injected `Psr\Clock\ClockInterface` ([`marko/clock`](/docs/packages/clock/)) and stamps `FailedJob::$failedAt` with it, rather than calling `new DateTimeImmutable()`. In a test, pass a [`FakeClock`](/docs/packages/testing/#fakeclock) to freeze that time, and a [`FakeEventDispatcher`](/docs/packages/testing/) to see the lifecycle events:

```php
use Marko\Queue\Event\JobFailedEvent;
use Marko\Queue\FailedJobHandler;
use Marko\Queue\JobRunner;
use Marko\Queue\RetryPolicy;
use Marko\Queue\Worker;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeLogger;

it('records when a job failed', function (): void {
    $clock = new FakeClock('2026-03-01 09:15:00 UTC');
    $events = new FakeEventDispatcher();
    $logger = new FakeLogger();
    $runner = new JobRunner($container, $envelope, $events);
    $worker = new Worker(
        $queue,
        new FailedJobHandler($failedJobs, $envelope, $runner, $events, $clock, $logger),
        $config,
        $runner,
        new RetryPolicy($config, $clock),
        $clock,
        $logger,
    );

    $worker->work(once: true);

    expect($failedJobs->find($jobId)?->failedAt)->toEqual($clock->now());
    $events->assertDispatched(JobFailedEvent::class);
});
```

Drivers that schedule delayed or reserved jobs, such as [`marko/queue-database`](/docs/packages/queue-database/#time-and-testing), take the same clock, so one `FakeClock` controls the whole queue.

### Configuration

Queue behavior is controlled by `config/queue.php`:

```php title="config/queue.php"
return [
    'driver'       => 'database',   // 'sync', 'database', 'redis' or 'rabbitmq'
    'connection'   => 'default',
    'queue'        => 'default',
    'retry_after'  => 90,           // seconds before a reserved-but-unfinished job is reclaimed
    'timeout'      => 60,           // seconds one job may run under queue:work; 0 = off
    'max_attempts' => 3,
    'backoff'      => null,         // int, list<int>, or null for 2^attempts * 10 seconds
    'restart_signal_driver' => 'file', // QUEUE_RESTART_SIGNAL_DRIVER; 'file' or 'cache'
    'restart_signal' => 'storage/queue/restart', // QUEUE_RESTART_SIGNAL; the file the file driver writes to
];
```

| Key | Default | Description |
|-----|---------|-------------|
| `driver` | `sync` | Queue backend: `sync`, `database`, `redis` or `rabbitmq` |
| `connection` | `default` | Named connection passed to the driver |
| `queue` | `default` | Default queue name, used by `push()`, `later()` and `pop()` when no queue is given |
| `retry_after` | `90` | Seconds after which a reserved job that has not been deleted or released is considered crashed and becomes eligible for re-reservation. The reclaimed reservation still counts as an attempt |
| `timeout` | `60` | Seconds one job may run under `queue:work` before it is failed and the worker exits; `0` turns it off. Must be below `retry_after`, and needs `ext-pcntl`. Overridden by `--timeout`. See [Worker Limits and Shutdown](#worker-limits-and-shutdown) |
| `max_attempts` | `3` | How many times a job is attempted before it is moved to the failed-job store. A job's own `maxAttempts`, when set, takes precedence |
| `backoff` | `null` | Seconds to wait before retrying a failed job that sets no `backoff` of its own: an `int` (fixed), a `list<int>` (per attempt; the last value repeats), or `null` for `2^attempts * 10` seconds. See [Retry Backoff](#retry-backoff) |
| `restart_signal_driver` | `file` | Where `queue:restart` stores its token: `file` (`FileRestartSignal`, the `restart_signal` file) or `cache` (`CacheRestartSignal`, needs `marko/cache`). Set with `QUEUE_RESTART_SIGNAL_DRIVER`. Any other value throws. See [Workers on several hosts](#workers-on-several-hosts) |
| `restart_signal` | `storage/queue/restart` | File the `file` restart signal driver writes its token to, relative to the project root (an absolute path is used as given). Set with `QUEUE_RESTART_SIGNAL`. Every worker that should restart must see the same file. See [Restarting Workers After a Deploy](#restarting-workers-after-a-deploy) |

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
        $backoff = $this->queueConfig->backoff();
        $restartSignalDriver = $this->queueConfig->restartSignalDriver();
        $restartSignal = $this->queueConfig->restartSignal();
    }
}
```

## API Reference

### QueueInterface

```php
use Marko\Queue\QueueInterface;
use Marko\Queue\JobInterface;
use Marko\Queue\PushResult;

public function push(JobInterface $job, ?string $queue = null): PushResult;
public function later(int $delay, JobInterface $job, ?string $queue = null): PushResult;
public function pop(?string $queue = null, bool $block = false): ?JobInterface; // $block: the driver may wait for a job (see Queue Priority)
public function size(?string $queue = null): int;
public function clear(?string $queue = null): int;
public function delete(string $jobId): bool;
public function release(JobInterface $job, int $delay = 0): bool; // stores the given job; false when no reserved job has its id
```

### PushResult

```php
use Marko\Queue\PushResult;

public ?string $id;          // the job ID when pushed, null when skipped
public bool $pushed;
public ?string $skipReason;  // why the job was not queued, null when pushed
public static function pushed(string $id): PushResult;
public static function skipped(string $reason): PushResult;
```

A readonly value object. The constructor throws `QueueException` when the fields contradict each other (pushed without an ID, or skipped without a reason).

### UniqueJobInterface

```php
use Marko\Queue\UniqueJobInterface;
use Marko\Queue\UniqueUntil;

// extends JobInterface
public ?string $uniqueLockOwner { get; } // the lock's owner token, set when the job is pushed
public function uniqueId(): string;
public function uniqueFor(): int; // lock lifetime in seconds, > 0
public function uniqueUntil(): UniqueUntil; // UniqueUntil::Processing or UniqueUntil::Processed
public function setUniqueLockOwner(string $owner): void;
```

`Job` provides `$uniqueLockOwner` and `setUniqueLockOwner()`. See [Unique Jobs](#unique-jobs).

### UniqueJobLocks

```php
use Marko\Queue\UniqueJobLocks;

public function lockName(UniqueJobInterface $job): string; // unique-job:{class}:{uniqueId}
public function acquire(UniqueJobInterface $job): bool; // takes the lock once and stores its owner on the job
public function release(UniqueJobInterface $job): void; // releases it when the job's owner still holds it
```

The shared helper behind `UniqueJobPushPlugin` and the release observers. It resolves `LockFactoryInterface` on first use, so constructing it never needs a lock store.

### JobInterface

```php
use Marko\Queue\JobInterface;

public ?string $id { get; }
public int $attempts { get; }
public ?int $maxAttempts { get; } // null = use queue.max_attempts
public array|int|null $backoff { get; } // int, list<int>, or null = use queue.backoff
public ?int $timeout { get; } // null = use the worker's timeout
public ?int $maxExceptions { get; } // null = no exception limit
public int $exceptions { get; }
public ?DateTimeImmutable $retryDeadline { get; }
public ?int $releaseDelay { get; } // set by release(), null when no release was requested
public function handle(): void;
public function setId(string $id): void;
public function incrementAttempts(): void;
public function resetAttempts(): void;
public function incrementExceptions(): void;
public function resetExceptions(): void;
public function setRetryDeadline(DateTimeImmutable $deadline): void;
public function clearRetryDeadline(): void;
public function release(int $delaySeconds = 0): void;
public function clearRelease(): void;
public function serialize(): string;
public static function unserialize(string $data): static;
```

### RetryUntilInterface

```php
use Marko\Queue\RetryUntilInterface;

public function retryUntil(): DateTimeImmutable; // called once, when the job is pushed
```

### JobIdGenerator

```php
use Marko\Queue\JobIdGenerator;

public function generate(): string; // a random RFC 4122 version 4 UUID
```

The one id generator the database, Redis and RabbitMQ drivers inject to give each pushed job its id. Replace it with a Preference to change the id format for every driver.

### RetryPolicy

```php
use Marko\Queue\JobInterface;
use Marko\Queue\RetryDecision;
use Marko\Queue\RetryPolicy;

public function decide(JobInterface $job, ?Throwable $e = null): RetryDecision; // $e null = the job released itself
public function limitReached(JobInterface $job): ?QueueException; // the limit reached, in precedence order, or null
public function limitReachedOnReservation(JobInterface $job): ?QueueException; // for drivers' pop-time check; attempts exhaustion is reported as crashed reservations
public function maxAttemptsFor(JobInterface $job): int; // the job's maxAttempts, or queue.max_attempts
public function backoffFor(JobInterface $job): int; // throws QueueException for an invalid backoff
```

Constructed with the `QueueConfig` and the clock. `decide()` returns a readonly `RetryDecision`: `shouldRelease()` is `true` with `$delay` set, or `false` with `$failure` holding the limit exception (or the backoff error). See [Retry Deadlines, Exception Limits and Self-Release](#retry-deadlines-exception-limits-and-self-release).

### JobRunnerInterface

```php
use Marko\Queue\JobRunnerInterface;
use Marko\Queue\JobInterface;

public function run(JobInterface $job): void; // rethrows whatever handle() throws
public function withContainer(JobInterface $job, callable $callback): mixed;
```

Bound to `JobRunner`, which is constructed with the container, the `JobEnvelope` and the `EventDispatcherInterface`. See [Running One Attempt](#running-one-attempt).

### FailedJobHandlerInterface

```php
use Marko\Queue\FailedJobHandlerInterface;
use Marko\Queue\JobInterface;

public function handle(JobInterface $job, string $queue, Throwable $exception, string $note = ''): void;
```

Bound to `FailedJobHandler`, which is constructed with the failed-job repository, the `JobEnvelope`, the `JobRunnerInterface`, the `EventDispatcherInterface`, the clock and the `LoggerInterface`. `$note` is appended to the stored exception text. The handler never touches the queue: the caller deletes the job. When the repository can't store the job, `handle()` throws `FailedJobNotStoredException` without calling `failed()` or dispatching the event, and the caller must not delete the job. See [When a Job Fails for Good](#when-a-job-fails-for-good).

### FailableJobInterface

```php
use Marko\Queue\FailableJobInterface;

public function failed(Throwable $exception): void;
```

### WorkerInterface

```php
use Marko\Queue\WorkerInterface;

/** @param list<string>|null $queues Priority order; null = the default queue */
public function work(
    ?array $queues = null,
    bool $once = false,
    int $sleep = 3,
    WorkerOptions $options = new WorkerOptions(), // timeout, memory (MB), maxJobs, maxTime; 0 = off
): WorkerStopReason;
public function stop(): void;
```

### WorkerOptions

```php
use Marko\Queue\WorkerOptions;

public function __construct(
    int $timeout = 0,           // default job timeout in seconds; a job's own $timeout replaces it
    int $memory = 0,            // megabytes
    int $maxJobs = 0,
    int $maxTime = 0,           // seconds
    array $stopConditions = [], // list<WorkerStopConditionInterface>, checked after the built-in limits
);
/** @return list<WorkerStopConditionInterface> */
public function buildStopConditions(ClockInterface $clock): array;
```

A negative limit, or a stop condition that doesn't implement `WorkerStopConditionInterface`, throws `QueueException`.

### WorkerStopConditionInterface

```php
use Marko\Queue\WorkerStopConditionInterface;

public function check(WorkerRunState $state): ?WorkerStopReason; // null = keep working
```

`WorkerRunState` is a readonly snapshot with `DateTimeImmutable $startedAt`, `int $jobsProcessed` and `int $memoryBytes`. `WorkerStopReason` is an enum with `message(): string` and `exitCode(): int`. See [Stop Reasons and Exit Codes](#stop-reasons-and-exit-codes).

The built-in `Worker` asks [`RetryPolicy`](#retrypolicy) whether a job that threw or released itself runs again, and after what delay.

### RestartSignalInterface

```php
use Marko\Queue\RestartSignalInterface;

public function request(): void;     // record a new restart token
public function current(): ?string;  // the current token, or null if no restart was ever requested
```

`module.php` resolves it through `RestartSignalFactory::create()`, which returns `FileRestartSignal` or `CacheRestartSignal` as `queue.restart_signal_driver` says, and throws `QueueException` for an unknown driver, or for `cache` without `marko/cache` or a cache driver. `queue:restart` calls `request()`; `RestartSignalStopCondition` reads `current()` when it is created and stops the worker with `RestartRequested` once it changes. `FileRestartSignal` throws `QueueException` when it cannot create, write or read the `queue.restart_signal` file; `CacheRestartSignal` throws it when the cache refuses the token, or when the `queue.restart_signal` cache key holds something other than a token string. See [Restarting Workers After a Deploy](#restarting-workers-after-a-deploy).

### RestartSignalFactory

```php
use Marko\Queue\RestartSignalFactory;

public function create(): RestartSignalInterface; // FileRestartSignal for 'file', CacheRestartSignal for 'cache'
```

`RestartSignalFactory::DRIVER_FILE` and `RestartSignalFactory::DRIVER_CACHE` hold the two driver names. `CacheRestartSignal` is resolved only when the `cache` driver is chosen, so an app without `marko/cache` can still use the `file` driver.

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
public function timeout(): int;
public function maxAttempts(): int;
public function backoff(): array|int|null; // int, list<int>, or null; throws QueueException when invalid
public function restartSignalDriver(): string; // 'file' or 'cache', as configured
public function restartSignal(): string;   // path of the restart signal file, as configured
```

### ContainerAwareJobInterface

```php
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\JobEnvelope;
use Marko\Core\Container\ContainerInterface;

public function setContainer(ContainerInterface $container): void;
public function setJobEnvelope(JobEnvelope $jobEnvelope): void;
public function releaseContainer(): void;
```

Implement this interface on any job class that needs to resolve services from the container when `handle()` runs. `JobRunner`, which the `Worker` and the [sync driver](/docs/packages/queue-sync/) both run jobs through, detects the interface, calls both setters before invoking `handle()`, and calls `releaseContainer()` once `handle()` returns or throws. Keep job constructor arguments to scalars and IDs only --- resolve services inside `handle()`.

`releaseContainer()` is part of the contract: a job written before it existed must add it. It must drop the container, the envelope and anything else the setters stored. See [Jobs That Need Container Services](#jobs-that-need-container-services).

### QueueAsyncObserverDispatcher

```php
use Marko\Core\Event\Event;
use Marko\Queue\QueueAsyncObserverDispatcher;

public function dispatch(string $observerClass, Event $event): void;
```

Implements `Marko\Core\Event\AsyncObserverDispatcherInterface` and is bound to it in `module.php`. Pushes an `AsyncObserverJob` whose event data is the serialized event wrapped in a `JobEnvelope`. Throws `SerializationException` when the event can't be serialized.

### Exceptions

| Exception | Description |
|-----------|-------------|
| `QueueException` | Base exception for all queue errors --- includes `getContext()` and `getSuggestion()` methods |
| `JobFailedException` | Thrown when a job fails during execution |
| `FailedJobNotStoredException` | Thrown by `FailedJobHandler` when the failed-job repository can't store a job that failed for good, with the storage error as `getPrevious()`. See [When a Failed Job Cannot Be Stored](#when-a-failed-job-cannot-be-stored) |
| `JobAttemptsExhaustedException` | The reason a job without a retry deadline was not retried once it used its `maxAttempts`. Passed to `failed()` and `JobFailedEvent` for a job that released itself on its last attempt, and when the database driver fails a job whose attempts were all reserved by workers that crashed or timed out |
| `JobRetryDeadlinePassedException` | The reason a job was not retried once its retry deadline passed |
| `JobExceptionsExhaustedException` | The reason a job was not retried once its exceptions reached `maxExceptions` |
| `JobTimedOutException` | Recorded as the failure of a job that ran longer than its timeout (its own `$timeout`, or the worker's). See [Worker Limits and Shutdown](#worker-limits-and-shutdown) |
| `SerializationException` | Thrown when a job payload cannot be serialized or deserialized, when an async observer's event cannot be serialized, when `encryption.key` is empty, or when an HMAC signature does not match (tampered payload) |
| `NoDriverException` | Thrown when a queue interface can't be resolved. For `QueueInterface` and `FailedJobRepositoryInterface` it lists the driver packages to install. For any other queue interface it names the interface that has no binding and tells you to bind it in `module.php` |
