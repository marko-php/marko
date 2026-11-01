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
4. **Jobs that implement `JobInterface` directly**, without extending `Job`, add the [per-job state](#per-job-state) properties and methods. Jobs that extend `Job` need no change, and jobs already queued by the old release unserialize with the new fields at their defaults.
5. **Code that constructs `Worker`, `SyncQueue`, `DatabaseQueue` or `JobRunner` by hand** wires in the new [failed-job handler](#when-a-job-fails-for-good) and an event dispatcher. `JobRunner` takes an `EventDispatcherInterface` as its third argument. `Worker` takes a `FailedJobHandlerInterface` in place of the failed-job repository and no longer takes the envelope or the clock: `new Worker($queue, $failedJobHandler, $config, $jobRunner)`. `SyncQueue` takes `($jobRunner, $failedJobHandler, $queueConfig)`, and `DatabaseQueue` takes `failedJobHandler:` in place of `failedJobRepository:`. Build the handler with `new FailedJobHandler($failedJobs, $envelope, $jobRunner, $eventDispatcher, $clock)`. Containers that autowire them need no change.

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

On a job's last attempt the backoff isn't read, so an invalid value there doesn't add a backoff error to the failed job. `Worker::backoffFor()` itself still throws `QueueException` for an invalid value.

### Per-Job State

Besides `maxAttempts` and `backoff`, every job carries this state. `Job` gives each one a default, so a job that extends it only overrides what it needs:

| Member | Default on `Job` | Meaning |
|--------|------------------|---------|
| `?int $timeout` | `null` | Seconds one attempt of this job may run, or `null` for the worker's timeout. Override with `public protected(set) ?int $timeout = 120;` |
| `?int $maxExceptions` | `null` | Attempts that may end in an exception before the job fails, or `null` for no exception limit. Override with `public protected(set) ?int $maxExceptions = 1;` |
| `int $exceptions` | `0` | Attempts that ended in an exception, counted by `incrementExceptions()` |
| `?DateTimeImmutable $retryDeadline` | `null` | The retry deadline captured when the job is dispatched, stored with `setRetryDeadline()`. It is not re-evaluated on later attempts |
| `?int $releaseDelay` | `null` | Seconds to wait before running again, set when `handle()` calls `release()`. `clearRelease()` resets it to `null` |

`release(int $delaySeconds = 0)` records a release request without throwing; a negative delay throws `QueueException`. `JobRunner` counts exceptions and clears a leftover release request at the start of each attempt. The worker does not act on `timeout`, `maxExceptions`, `retryDeadline` or `releaseDelay` yet: they are part of the contract so that drivers keep them across retries, ready for the queue features that read them.

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

    public function setRetryDeadline(DateTimeImmutable $deadline): void
    {
        $this->retryDeadline = $deadline;
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

A job fails for good when its last attempt throws or times out, when its backoff is invalid so it can't be retried, when the [database driver](/docs/packages/queue-database/#retries-and-attempt-counting) finds its attempts used up by workers that crashed, and when it throws under the [sync driver](/docs/packages/queue-sync/). Every one of those paths goes through `FailedJobHandlerInterface` (bound to `FailedJobHandler`), which in order:

1. stores the job in the failed-job store, with its exception and an [argument-free trace](#failed-job-traces)
2. calls `failed()` if the job implements `FailableJobInterface`
3. dispatches `JobFailedEvent`

The caller then deletes the job from its queue.

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
- If `failed()` throws, the error is written to PHP's error log with the job class and id, `JobFailedEvent` is still dispatched, and the worker keeps running.
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

`push()` and `later()` return a `PushResult`. When the job was queued, `$result->pushed` is `true` and `$result->id` holds the job ID. When it was not, `$result->pushed` is `false`, `$result->id` is `null`, and `$result->skipReason` says why. The built-in drivers always queue the job. A `#[Before]` plugin on `push()` can return `PushResult::skipped('...')` to stop it:

```php
$result = $this->queue->push(new SendWelcomeEmail('user@example.com'));

if ($result->pushed) {
    $this->logger->info("Queued job $result->id");
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
marko queue:work --queue emails     # or --queue=emails
marko queue:work --once
marko queue:work --queue=high,default,low --sleep=1
```

| Option | Description |
|--------|-------------|
| `--queue` | Queue names to work, in priority order, separated by commas. Defaults to the `queue.queue` config value |
| `--sleep` | Seconds to wait when every queue is empty (default `3`) |
| `--once` | Process at most one job, then exit |
| `--timeout` | Seconds one job may run before it is failed and the worker exits. Defaults to the `queue.timeout` config value (`60`); `0` turns it off. Needs `ext-pcntl` |
| `--memory` | Exit after the current job once the worker uses this many megabytes (default `0`, no limit) |
| `--max-jobs` | Exit after processing this many jobs (default `0`, no limit) |

If popping a job throws (for example, the broker is unreachable), the worker writes the error to PHP's error log (STDERR for `queue:work`), sleeps for `--sleep` seconds and polls again, so a pop failure doesn't stop the worker. With `--once` the error is thrown instead.

### Worker Limits and Shutdown

Run `queue:work` under a process supervisor (systemd, Supervisor, a container restart policy) and let it recycle workers:

```bash
marko queue:work --timeout=60 --memory=128 --max-jobs=1000
```

- **Timeout** --- when a job runs longer than `--timeout` seconds, the worker fails that attempt with a `JobTimedOutException` (`Job '...' exceeded the 60-second timeout.`). The job is released for another attempt with its backoff, or [failed for good](#when-a-job-fails-for-good) on its last attempt, like a job that threw. The worker then logs the timeout and exits with status `1` (`Worker::EXIT_TIMED_OUT`): a job stuck in a blocking call can't be stopped safely inside the process, so the supervisor starts a fresh one.
- **Memory and job count** --- with `--memory` or `--max-jobs`, the worker finishes the current job, then returns and `queue:work` exits with status `0`, so leaks never build up to an out-of-memory crash.
- **SIGTERM / SIGINT** --- the worker finishes the job it is running, then exits, so a deploy or `docker stop` doesn't cut a job off halfway.

The timeout must be shorter than `queue.retry_after`. Otherwise a job still running when its reservation expires is handed to a second worker and runs twice, so `queue:work` refuses to start and exits with code `1` when `--timeout` (or `queue.timeout`) is not below `retry_after`. Leave a few seconds of margin.

The timeout and graceful shutdown need the `pcntl` extension. Without it, `queue:work` prints a warning and runs with **no** per-job timeout, and SIGTERM/SIGINT stop the worker immediately, mid-job. `--memory` and `--max-jobs` work without `pcntl`.

From code, pass the limits as `WorkerOptions` (every limit defaults to `0`, meaning off):

```php
use Marko\Queue\WorkerOptions;

$worker->work(queues: ['default'], options: new WorkerOptions(timeout: 60, memory: 128, maxJobs: 1000));
```

The worker reaches signals, the timer, memory usage and process exit through `ProcessControlInterface`, bound to `PcntlProcessControl`. Bind your own implementation to change how the worker handles them.

### Failed Job Traces

The `exception` text stored for a failed job holds the exception message and a stack trace in `getTraceAsString()` format, **without call arguments**. Arguments can hold passwords or tokens, and with `zend.exception_ignore_args=Off` (the PHP development default) `getTraceAsString()` would include them, so `FailedJobHandler` builds the trace from file, line, class and function only.

### Queue Priority

Give `--queue` several names to work them in priority order from one worker process:

```bash
marko queue:work --queue=high,default,low
```

On every pass the worker pops `high` first, then `default`, then `low`, and processes the first job it finds. After each job it starts again at `high`, so a lower queue only runs when every queue ahead of it is empty. The worker sleeps only when all listed queues are empty. With `--once`, it processes at most one job across all the queues.

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
| `marko queue:retry <id>` | Retry a failed job (resets attempt counter so the job gets its full `maxAttempts`) |
| `marko queue:retry --all` | Retry every failed job. Rows whose payload could not be serialized are skipped and reported, and the command returns exit code 1 if any were skipped |
| `marko queue:clear` | Clear all jobs from a queue |
| `marko queue:status` | Show queue size |

### Time and Testing

`FailedJobHandler` reads the current time from the injected `Psr\Clock\ClockInterface` ([`marko/clock`](/docs/packages/clock/)) and stamps `FailedJob::$failedAt` with it, rather than calling `new DateTimeImmutable()`. In a test, pass a [`FakeClock`](/docs/packages/testing/#fakeclock) to freeze that time, and a [`FakeEventDispatcher`](/docs/packages/testing/) to see the lifecycle events:

```php
use Marko\Queue\Event\JobFailedEvent;
use Marko\Queue\FailedJobHandler;
use Marko\Queue\JobRunner;
use Marko\Queue\Worker;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeEventDispatcher;

it('records when a job failed', function (): void {
    $clock = new FakeClock('2026-03-01 09:15:00 UTC');
    $events = new FakeEventDispatcher();
    $runner = new JobRunner($container, $envelope, $events);
    $worker = new Worker($queue, new FailedJobHandler($failedJobs, $envelope, $runner, $events, $clock), $config, $runner);

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
    'driver'       => 'database',   // 'sync', 'database', or 'rabbitmq'
    'connection'   => 'default',
    'queue'        => 'default',
    'retry_after'  => 90,           // seconds before a reserved-but-unfinished job is reclaimed
    'timeout'      => 60,           // seconds one job may run under queue:work; 0 = off
    'max_attempts' => 3,
    'backoff'      => null,         // int, list<int>, or null for 2^attempts * 10 seconds
];
```

| Key | Default | Description |
|-----|---------|-------------|
| `driver` | `sync` | Queue backend: `sync`, `database`, or `rabbitmq` |
| `connection` | `default` | Named connection passed to the driver |
| `queue` | `default` | Default queue name, used by `push()`, `later()` and `pop()` when no queue is given |
| `retry_after` | `90` | Seconds after which a reserved job that has not been deleted or released is considered crashed and becomes eligible for re-reservation. The reclaimed reservation still counts as an attempt |
| `timeout` | `60` | Seconds one job may run under `queue:work` before it is failed and the worker exits; `0` turns it off. Must be below `retry_after`, and needs `ext-pcntl`. Overridden by `--timeout`. See [Worker Limits and Shutdown](#worker-limits-and-shutdown) |
| `max_attempts` | `3` | How many times a job is attempted before it is moved to the failed-job store. A job's own `maxAttempts`, when set, takes precedence |
| `backoff` | `null` | Seconds to wait before retrying a failed job that sets no `backoff` of its own: an `int` (fixed), a `list<int>` (per attempt; the last value repeats), or `null` for `2^attempts * 10` seconds. See [Retry Backoff](#retry-backoff) |

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
public function pop(?string $queue = null): ?JobInterface;
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
public function setRetryDeadline(DateTimeImmutable $deadline): void;
public function release(int $delaySeconds = 0): void;
public function clearRelease(): void;
public function serialize(): string;
public static function unserialize(string $data): static;
```

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

Bound to `FailedJobHandler`, which is constructed with the failed-job repository, the `JobEnvelope`, the `JobRunnerInterface`, the `EventDispatcherInterface` and the clock. `$note` is appended to the stored exception text. The handler never touches the queue: the caller deletes the job. See [When a Job Fails for Good](#when-a-job-fails-for-good).

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
    WorkerOptions $options = new WorkerOptions(), // timeout, memory (MB), maxJobs; 0 = off
): void;
public function stop(): void;
```

The built-in `Worker` also exposes `backoffFor(JobInterface $job): int`, which returns the retry delay for a job whose latest attempt failed.

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
| `JobAttemptsExhaustedException` | Passed to `failed()` and `JobFailedEvent` when the database driver fails a job whose attempts were all reserved by workers that crashed or timed out |
| `JobTimedOutException` | Recorded as the failure of a job that ran longer than the worker timeout. See [Worker Limits and Shutdown](#worker-limits-and-shutdown) |
| `SerializationException` | Thrown when a job payload cannot be serialized or deserialized, when an async observer's event cannot be serialized, when `encryption.key` is empty, or when an HMAC signature does not match (tampered payload) |
| `NoDriverException` | Thrown when a queue interface can't be resolved. For `QueueInterface` and `FailedJobRepositoryInterface` it lists the driver packages to install. For any other queue interface it names the interface that has no binding and tells you to bind it in `module.php` |
