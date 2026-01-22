# Plan: Queue Package

## Created
2026-01-21

## Status
completed

## Objective
Implement the queue system for Marko framework with interface/implementation split pattern, enabling async observers and background job processing. Provide `marko/queue` (interfaces and infrastructure), `marko/queue-sync` (synchronous driver for development), and `marko/queue-database` (database-backed driver for production).

## Scope

### In Scope
- `marko/queue` package with interfaces, job classes, queue configuration, and exceptions
  - `QueueInterface` - primary queue contract (push, pop, later, size, clear)
  - `JobInterface` - contract for executable jobs
  - `Job` - base class for jobs with serialization support
  - `QueueConfig` - configuration loaded from config/queue.php
  - `QueueException` hierarchy (QueueException, JobFailedException, SerializationException)
  - `FailedJobRepositoryInterface` - contract for storing failed jobs
  - `WorkerInterface` - contract for queue workers
  - `Worker` - processes jobs from queue with retry logic
  - CLI commands: `queue:work`, `queue:failed`, `queue:retry`, `queue:clear`, `queue:status`
- `marko/queue-sync` package with synchronous driver
  - `SyncQueue` - executes jobs immediately (no external dependencies)
  - Ideal for development and testing
  - No persistence, jobs run inline
- `marko/queue-database` package with database-backed driver
  - `DatabaseQueue` - stores jobs in database table
  - `DatabaseFailedJobRepository` - stores failed jobs for retry
  - Requires `marko/database` for persistence
  - Migration for jobs and failed_jobs tables
- Integration with core's event system for async observers
  - Add `async` parameter to `#[Observer]` attribute
  - `AsyncObserverJob` - job class that wraps async observer execution
  - Modify `EventDispatcher` to queue async observers

### Out of Scope
- RabbitMQ/SQS/Redis drivers (future packages)
- Job batching and chaining
- Rate limiting
- Job middleware
- Scheduled/delayed jobs with precision timing (basic delay supported)
- Job events/hooks
- Supervisor/daemon management
- Horizontal scaling coordination

## Success Criteria
- [ ] `QueueInterface` provides clean push/pop/later/size/clear contract
- [ ] `JobInterface` defines serializable job contract with handle() method
- [ ] `Job` base class handles serialization of job data
- [ ] `Worker` processes jobs with configurable retry attempts
- [ ] `QueueConfig` loads configuration from `config/queue.php`
- [ ] `SyncQueue` executes jobs immediately for development
- [ ] `DatabaseQueue` persists jobs to database table
- [ ] `queue:work` processes jobs from queue (runs until stopped or --once flag)
- [ ] `queue:failed` lists failed jobs with error details
- [ ] `queue:retry` retries specific or all failed jobs
- [ ] `queue:clear` clears all jobs from queue
- [ ] `queue:status` shows queue statistics
- [ ] `#[Observer(async: true)]` queues observer for later processing
- [ ] Failed jobs stored with payload, exception, and attempt count
- [ ] Loud error when no queue driver is installed
- [ ] Driver conflict handling if multiple drivers installed
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding (composer.json for queue, queue-sync, queue-database) | - | pending |
| 002 | QueueException hierarchy | 001 | pending |
| 003 | JobInterface and Job base class | 001 | pending |
| 004 | QueueInterface contract | 003 | pending |
| 005 | QueueConfig class | 001 | pending |
| 006 | FailedJobRepositoryInterface | 002 | pending |
| 007 | WorkerInterface and Worker implementation | 003, 004, 006 | pending |
| 008 | queue package module.php with QueueConfig binding | 005 | pending |
| 009 | SyncQueue implementation | 004 | pending |
| 010 | queue-sync module.php with bindings | 009 | pending |
| 011 | DatabaseQueue implementation | 004 | pending |
| 012 | DatabaseFailedJobRepository implementation | 006 | pending |
| 013 | Database migrations for jobs tables | 011, 012 | pending |
| 014 | queue-database module.php with bindings | 011, 012, 013 | pending |
| 015 | CLI: queue:work command | 007 | pending |
| 016 | CLI: queue:failed command | 006 | pending |
| 017 | CLI: queue:retry command | 006, 007 | pending |
| 018 | CLI: queue:clear command | 004 | pending |
| 019 | CLI: queue:status command | 004, 006 | pending |
| 020 | Update Observer attribute with async parameter | - | pending |
| 021 | AsyncObserverJob class | 003 | pending |
| 022 | Update EventDispatcher for async observer support | 004, 020, 021 | pending |
| 023 | Unit tests for queue package | 002-008 | pending |
| 024 | Unit tests for queue-sync package | 009, 010 | pending |
| 025 | Unit tests for queue-database package | 011-014 | pending |
| 026 | Integration tests | 015-022 | pending |

## Architecture Notes

### Package Structure
```
packages/
  queue/                      # Interfaces + shared code
    src/
      Contracts/
        QueueInterface.php
        JobInterface.php
        WorkerInterface.php
        FailedJobRepositoryInterface.php
      Config/
        QueueConfig.php
      Exceptions/
        QueueException.php
        JobFailedException.php
        SerializationException.php
      Job.php                 # Base job class
      Worker.php              # Job processor
      FailedJob.php           # Failed job value object
      Command/
        WorkCommand.php
        FailedCommand.php
        RetryCommand.php
        ClearCommand.php
        StatusCommand.php
    tests/
    composer.json
    module.php
  queue-sync/                 # Synchronous implementation
    src/
      Driver/
        SyncQueue.php
      Factory/
        SyncQueueFactory.php
      Repository/
        NullFailedJobRepository.php
    tests/
    composer.json
    module.php
  queue-database/             # Database implementation
    src/
      Driver/
        DatabaseQueue.php
      Factory/
        DatabaseQueueFactory.php
      Repository/
        DatabaseFailedJobRepository.php
      Migration/
        CreateJobsTable.php
        CreateFailedJobsTable.php
    tests/
    composer.json
    module.php
```

### Config Location
```php
// config/queue.php
return [
    'driver' => $_ENV['QUEUE_DRIVER'] ?? 'sync',
    'connection' => $_ENV['QUEUE_CONNECTION'] ?? 'default',
    'queue' => $_ENV['QUEUE_NAME'] ?? 'default',
    'retry_after' => 90,      // Seconds before retrying reserved job
    'max_attempts' => 3,      // Max retry attempts before marking failed
];
```

### Interface Design

```php
// QueueInterface - primary queue contract
interface QueueInterface
{
    /**
     * Push a job onto the queue.
     */
    public function push(JobInterface $job, ?string $queue = null): string;

    /**
     * Push a job onto the queue after a delay.
     *
     * @param int $delay Delay in seconds
     */
    public function later(int $delay, JobInterface $job, ?string $queue = null): string;

    /**
     * Pop the next job off the queue.
     */
    public function pop(?string $queue = null): ?JobInterface;

    /**
     * Get the size of the queue.
     */
    public function size(?string $queue = null): int;

    /**
     * Clear all jobs from the queue.
     */
    public function clear(?string $queue = null): int;

    /**
     * Delete a job from the queue.
     */
    public function delete(string $jobId): bool;

    /**
     * Release a job back onto the queue.
     *
     * @param int $delay Delay in seconds before job is available
     */
    public function release(string $jobId, int $delay = 0): bool;
}
```

```php
// JobInterface - contract for executable jobs
interface JobInterface
{
    /**
     * Execute the job.
     */
    public function handle(): void;

    /**
     * Get the job's unique identifier.
     */
    public function getId(): ?string;

    /**
     * Set the job's unique identifier.
     */
    public function setId(string $id): void;

    /**
     * Get the number of times this job has been attempted.
     */
    public function getAttempts(): int;

    /**
     * Increment the attempt counter.
     */
    public function incrementAttempts(): void;

    /**
     * Get the maximum number of attempts.
     */
    public function getMaxAttempts(): int;

    /**
     * Serialize the job for storage.
     */
    public function serialize(): string;

    /**
     * Unserialize a job from storage.
     */
    public static function unserialize(string $data): static;
}
```

```php
// WorkerInterface - contract for job processing
interface WorkerInterface
{
    /**
     * Process jobs from the queue.
     *
     * @param bool $once Process only one job then stop
     * @param int $sleep Seconds to sleep when queue is empty
     */
    public function work(?string $queue = null, bool $once = false, int $sleep = 3): void;

    /**
     * Stop the worker after current job completes.
     */
    public function stop(): void;
}
```

```php
// FailedJobRepositoryInterface - contract for failed job storage
interface FailedJobRepositoryInterface
{
    /**
     * Store a failed job.
     */
    public function store(FailedJob $failedJob): void;

    /**
     * Get all failed jobs.
     *
     * @return FailedJob[]
     */
    public function all(): array;

    /**
     * Find a failed job by ID.
     */
    public function find(string $id): ?FailedJob;

    /**
     * Delete a failed job.
     */
    public function delete(string $id): bool;

    /**
     * Clear all failed jobs.
     */
    public function clear(): int;

    /**
     * Count failed jobs.
     */
    public function count(): int;
}
```

### Job Base Class
```php
// Job - base class with serialization support
abstract class Job implements JobInterface
{
    private ?string $id = null;
    private int $attempts = 0;
    protected int $maxAttempts = 3;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): void
    {
        $this->attempts++;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function serialize(): string
    {
        return serialize($this);
    }

    public static function unserialize(string $data): static
    {
        $job = unserialize($data);

        if (!$job instanceof static) {
            throw SerializationException::invalidJobData();
        }

        return $job;
    }
}
```

### Async Observer Integration

**Updated Observer Attribute:**
```php
// packages/core/src/Attributes/Observer.php
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Observer
{
    public function __construct(
        public string $event,
        public int $priority = 0,
        public bool $async = false,  // NEW: queue for later processing
    ) {}
}
```

**AsyncObserverJob:**
```php
// packages/queue/src/Job/AsyncObserverJob.php
class AsyncObserverJob extends Job
{
    public function __construct(
        private string $observerClass,
        private string $eventData,  // Serialized event
    ) {}

    public function handle(): void
    {
        $container = Application::getInstance()->getContainer();
        $observer = $container->get($this->observerClass);
        $event = unserialize($this->eventData);

        $observer->handle($event);
    }
}
```

**Updated EventDispatcher:**
```php
// packages/core/src/Event/EventDispatcher.php
readonly class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private ContainerInterface $container,
        private ObserverRegistry $registry,
        private ?QueueInterface $queue = null,  // Optional queue dependency
    ) {}

    public function dispatch(Event $event): void
    {
        $observers = $this->registry->getObserversFor($event::class);
        usort($observers, fn ($a, $b) => $b->priority <=> $a->priority);

        foreach ($observers as $definition) {
            if ($event->propagationStopped) {
                break;
            }

            if ($definition->async && $this->queue !== null) {
                // Queue for later processing
                $job = new AsyncObserverJob(
                    $definition->observerClass,
                    serialize($event),
                );
                $this->queue->push($job);
            } else {
                // Execute immediately
                $observer = $this->container->get($definition->observerClass);
                $observer->handle($event);
            }
        }
    }
}
```

### Database Tables

**jobs table:**
```sql
CREATE TABLE jobs (
    id VARCHAR(36) PRIMARY KEY,
    queue VARCHAR(255) NOT NULL DEFAULT 'default',
    payload TEXT NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    reserved_at TIMESTAMP NULL,
    available_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_queue_available (queue, available_at)
);
```

**failed_jobs table:**
```sql
CREATE TABLE failed_jobs (
    id VARCHAR(36) PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    exception TEXT NOT NULL,
    failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

### Worker Processing Logic
```php
class Worker implements WorkerInterface
{
    public function work(?string $queue = null, bool $once = false, int $sleep = 3): void
    {
        $this->running = true;

        while ($this->running) {
            $job = $this->queue->pop($queue);

            if ($job === null) {
                if ($once) {
                    return;
                }
                sleep($sleep);
                continue;
            }

            try {
                $job->incrementAttempts();
                $job->handle();
                $this->queue->delete($job->getId());
            } catch (Throwable $e) {
                $this->handleFailedJob($job, $e);
            }

            if ($once) {
                return;
            }
        }
    }

    private function handleFailedJob(JobInterface $job, Throwable $e): void
    {
        if ($job->getAttempts() < $job->getMaxAttempts()) {
            // Release back to queue with exponential backoff
            $delay = (int) pow(2, $job->getAttempts()) * 10;
            $this->queue->release($job->getId(), $delay);
        } else {
            // Max attempts reached, store as failed
            $this->failedRepository->store(new FailedJob(
                id: Uuid::uuid4(),
                queue: $this->config->queue,
                payload: $job->serialize(),
                exception: (string) $e,
                failedAt: new DateTimeImmutable(),
            ));
            $this->queue->delete($job->getId());
        }
    }
}
```

### CLI Commands

**queue:work**
```
$ marko queue:work
Processing jobs from queue...
[2026-01-21 10:00:01] Processing: App\Jobs\SendEmail
[2026-01-21 10:00:02] Processed:  App\Jobs\SendEmail
^C
Worker stopped.

$ marko queue:work --once
[2026-01-21 10:00:01] Processing: App\Jobs\SendEmail
[2026-01-21 10:00:02] Processed:  App\Jobs\SendEmail

$ marko queue:work --queue=emails
Processing jobs from 'emails' queue...
```

**queue:failed**
```
$ marko queue:failed
+--------------------------------------+----------+----------------------+---------------------+
| ID                                   | Queue    | Job                  | Failed At           |
+--------------------------------------+----------+----------------------+---------------------+
| a1b2c3d4-e5f6-7890-abcd-ef1234567890 | default  | App\Jobs\SendEmail   | 2026-01-21 09:45:00 |
| b2c3d4e5-f6g7-8901-bcde-fg2345678901 | default  | App\Jobs\ProcessFile | 2026-01-21 09:50:00 |
+--------------------------------------+----------+----------------------+---------------------+
Total: 2 failed jobs
```

**queue:retry**
```
$ marko queue:retry a1b2c3d4-e5f6-7890-abcd-ef1234567890
Job a1b2c3d4-e5f6-7890-abcd-ef1234567890 pushed back to queue.

$ marko queue:retry --all
2 jobs pushed back to queue.
```

**queue:clear**
```
$ marko queue:clear
Cleared 15 jobs from queue.

$ marko queue:clear --queue=emails
Cleared 3 jobs from 'emails' queue.
```

**queue:status**
```
$ marko queue:status
Queue Driver: database
Queue Name: default
Pending Jobs: 42
Failed Jobs: 2
```

### Driver Conflict Handling
Only one driver package can be installed. If both `marko/queue-sync` and `marko/queue-database` are installed, the framework throws a loud error during boot:

```
BindingConflictException: Multiple implementations bound for QueueInterface.

Context: Both SyncQueue and DatabaseQueue are attempting to bind.

Suggestion: Install only one queue driver package. Remove one with:
  composer remove marko/queue-sync
  or
  composer remove marko/queue-database
```

### No Driver Installed Handling
If `marko/queue` is installed without a driver, attempting to use queue features throws:

```
QueueException: No queue driver installed.

Context: Attempted to resolve QueueInterface but no implementation is bound.

Suggestion: Install a queue driver package:
  composer require marko/queue-sync   (for development)
  or
  composer require marko/queue-database   (for production)
```

### Module Bindings

**queue/module.php**
```php
return [
    'enabled' => true,
    'bindings' => [
        QueueConfig::class => QueueConfig::class,
    ],
];
```

**queue-sync/module.php**
```php
return [
    'enabled' => true,
    'bindings' => [
        QueueInterface::class => function (ContainerInterface $container): QueueInterface {
            return $container->get(SyncQueueFactory::class)->create();
        },
        FailedJobRepositoryInterface::class => NullFailedJobRepository::class,
    ],
];
```

**queue-database/module.php**
```php
return [
    'enabled' => true,
    'bindings' => [
        QueueInterface::class => function (ContainerInterface $container): QueueInterface {
            return $container->get(DatabaseQueueFactory::class)->create();
        },
        FailedJobRepositoryInterface::class => function (ContainerInterface $container): FailedJobRepositoryInterface {
            return new DatabaseFailedJobRepository(
                $container->get(ConnectionInterface::class),
            );
        },
    ],
];
```

### SyncQueue Behavior
The sync driver executes jobs immediately during `push()`:
```php
class SyncQueue implements QueueInterface
{
    public function push(JobInterface $job, ?string $queue = null): string
    {
        $id = Uuid::uuid4();
        $job->setId($id);
        $job->incrementAttempts();

        try {
            $job->handle();
        } catch (Throwable $e) {
            throw JobFailedException::fromException($job, $e);
        }

        return $id;
    }

    public function later(int $delay, JobInterface $job, ?string $queue = null): string
    {
        // Sync driver ignores delay, executes immediately
        return $this->push($job, $queue);
    }

    public function pop(?string $queue = null): ?JobInterface
    {
        // Sync driver has no queue to pop from
        return null;
    }

    public function size(?string $queue = null): int
    {
        return 0;
    }

    public function clear(?string $queue = null): int
    {
        return 0;
    }

    public function delete(string $jobId): bool
    {
        return true;
    }

    public function release(string $jobId, int $delay = 0): bool
    {
        return true;
    }
}
```

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| **Job serialization failures** | Use PHP's native serialize/unserialize; document that closures and resources cannot be queued; clear SerializationException with suggestion |
| **Database driver not installed for queue-database** | queue-database requires marko/database in composer.json; loud error if connection not available |
| **Long-running workers** | Document supervisor/systemd setup; provide --once flag for cron-based processing |
| **Memory leaks in workers** | Workers should be restarted periodically; document memory management |
| **Async observer event serialization** | Events must be serializable; document this requirement; throw clear error if event cannot be serialized |
| **Race conditions in database queue** | Use database transactions and row locking for pop operations |
| **Failed job payload too large** | Consider TEXT vs LONGTEXT for payload column; document maximum job size |
| **Observer queue optional** | EventDispatcher gracefully handles null queue - async observers run synchronously if no queue installed |
