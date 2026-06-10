# Task 004: F6 — AsyncObserverJob self-resolves and invokes observer; Worker wiring

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Async observers never run. `EventDispatcher` pushes an `AsyncObserverJob` for every
`#[Observer(async: true)]`, but `AsyncObserverJob::handle(?callable $resolver = null)`
is a no-op when no resolver is supplied, and `Worker::work()` calls `$job->handle()`
with no arguments — so the observer is silently dropped. Make the job reconstruct and
invoke the observer for the event without requiring an externally-passed resolver:
resolve the observer from the container and call its handler.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/queue/src/AsyncObserverJob.php`
    (`handle(?callable $resolver = null)`: `$event = unserialize($this->eventData);`
    then only acts `if ($resolver !== null)`, else no-op placeholder)
  - `/Users/markshust/Sites/marko/packages/queue/src/Worker.php`
    (`work()` calls `$job->incrementAttempts(); $job->handle();` with no resolver)
  - `/Users/markshust/Sites/marko/packages/queue/src/Job.php`,
    `/Users/markshust/Sites/marko/packages/queue/src/JobInterface.php`
    (`handle(): void` contract; jobs are `serialize($this)`)
  - `/Users/markshust/Sites/marko/packages/core/src/Event/EventDispatcher.php`
    (constructs `new AsyncObserverJob($definition->observerClass, serialize($event))`
    and `$this->queue->push($job)`)
  - `/Users/markshust/Sites/marko/packages/queue/tests/AsyncObserverJobTest.php`,
    `/Users/markshust/Sites/marko/packages/queue/tests/WorkerTest.php`
- Patterns to follow:
  - **Worker gains a `ContainerInterface` constructor dependency.** `Worker::__construct`
    today takes `(QueueInterface, FailedJobRepositoryInterface, QueueConfig)`. To inject
    the container into a popped `AsyncObserverJob`, the Worker must hold a
    `ContainerInterface`. Add it as a new constructor param (autowired in production —
    `Worker` has no explicit binding, it is autowired). This RIPPLES to every
    `new Worker(...)` call site, all in tests:
    `packages/queue/tests/WorkerTest.php` (7 sites: lines ~247, 340, 427, 455, 539, 609,
    703) and `packages/queue/tests/Feature/IntegrationTest.php` (3 sites: ~235, 337, 399).
    Update all 10 to pass a container stub. Use Marko's container interface
    `Marko\Core\Container\ContainerInterface` (the same type `EventDispatcher` uses).
  - **Worker special-cases `AsyncObserverJob`.** In `work()`, after popping, do
    `if ($job instanceof AsyncObserverJob) { $job->setContainer($this->container); }`
    before `$job->handle()`. Both `Worker` and `AsyncObserverJob` live in `marko/queue`,
    so this coupling is intra-package and acceptable.
  - **Serialization constraint (no magic methods).** `Job::serialize()` does
    `serialize($this)`, which includes every declared property. `AsyncObserverJob` may
    hold `private ?ContainerInterface $container = null` and a public
    `setContainer(ContainerInterface $container): void` setter. The container is null at
    push/serialize time (EventDispatcher serializes the job before any worker sees it),
    so serializing a null property is harmless and no `__serialize`/`__sleep` magic method
    is needed (Marko bans magic methods). The Worker sets the container at pop time and
    immediately calls `handle()`; the job is never re-serialized with a non-null
    container. `handle()` keeps the `JobInterface::handle(): void` signature (NO
    `$resolver` param) and resolves the observer via `$this->container->get(
    $this->observerClass)`, throwing a loud error if `$this->container` is null.
  - **Update the existing `AsyncObserverJobTest`.** The current test
    `it('handle executes observer')` calls `$job->handle(fn (...) => $observer)` — a
    resolver callback. Removing the `$resolver` param breaks it. Rewrite that test to set
    a container stub via `setContainer()` then call `handle()`. Keep the
    `serializes and unserializes correctly` test working (container stays null through
    serialize round-trip).
  - `WorkerTest.php` already uses `FakeConfigRepository` and an anonymous
    `FailedJobRepositoryInterface`; follow that fixture style. For container
    resolution use a `Marko\Core\Container\ContainerInterface` stub that returns a
    recording observer.
- Tier-1 rebase note: `unserialize($this->eventData)` is also touched by Tier 1's
  unserialize hardening; rebase onto that seam rather than re-adding raw `unserialize`.

## Requirements (Test Descriptions)
- [ ] `it resolves the observer from the container and calls handle() with the
      deserialized event when the async observer job is processed`
- [ ] `it executes an #[Observer(async: true)] observer end-to-end when its queued job runs
      (dispatch pushes a job, worker processes it, observer's handler is invoked once)`
- [ ] `it passes the original event payload to the observer (the event reconstructed
      from eventData equals the dispatched event)`
- [ ] `it no longer silently does nothing when handle() is called (it resolves and invokes
      the observer using the injected container)`
- [ ] `it surfaces a loud error when the observer class cannot be resolved (no silent swallow)`
- [ ] `it throws a loud error when handle() runs before a container has been set (never a no-op)`
- [ ] `it serializes and unserializes without the container (container is null across the
      serialize round-trip; no magic methods)`
- [ ] `it constructs a Worker with a ContainerInterface dependency and the worker injects
      that container into a popped AsyncObserverJob before calling handle()`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
