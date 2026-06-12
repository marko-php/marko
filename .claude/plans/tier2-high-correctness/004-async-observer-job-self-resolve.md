# Task 004: F6 — AsyncObserverJob self-resolves and invokes observer; Worker wiring

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Async observers never run. `EventDispatcher` pushes an `AsyncObserverJob` for every
`#[Observer(async: true)]`, but `AsyncObserverJob::handle()` only acts when an external
`$resolver` callback is supplied (a no-op placeholder otherwise), and `Worker::work()`
calls `$job->handle()` with no arguments — so the observer is silently dropped. Make the
job reconstruct and invoke the observer for the event without requiring an
externally-passed resolver: resolve the observer from the container and call its handler.

## CRITICAL — post-Tier-1 signatures (do NOT use the pre-Tier-1 shapes)
Tier 1 is already merged into this branch and changed BOTH signatures this task
touches. Read the CURRENT files before writing tests:

- **`Worker::__construct` already has FOUR params**, not three:
  `(QueueInterface $queue, FailedJobRepositoryInterface $failedJobRepository,
  QueueConfig $config, JobEnvelope $jobEnvelope)` (`packages/queue/src/Worker.php:15-20`).
  Adding `ContainerInterface` makes FIVE. Add it AFTER `JobEnvelope` (keep the existing
  arg order intact); do NOT drop or reorder the `JobEnvelope` dependency.
- **`AsyncObserverJob::handle` already has TWO optional params**:
  `handle(?callable $resolver = null, ?JobEnvelope $jobEnvelope = null): void`
  (`packages/queue/src/AsyncObserverJob.php:25-41`). The `eventData` is an HMAC-signed
  envelope and `handle()` currently calls `$jobEnvelope->verifyAndUnwrap($this->eventData)`
  before `unserialize($rawEventData)`. The envelope-verification path is Tier-1 security
  hardening and MUST be preserved — do NOT collapse to a raw `unserialize($this->eventData)`.

## Envelope threading (the load-bearing detail)
Removing `$resolver` is not enough: the job still needs the signed envelope to verify
`eventData`. The Worker already HOLDS a `JobEnvelope` (constructor dep). The fix threads
BOTH the container AND the envelope onto the popped `AsyncObserverJob` before calling
`handle()`:

- `AsyncObserverJob` keeps `private ?ContainerInterface $container = null` and adds
  `private ?JobEnvelope $jobEnvelope = null`, with `setContainer(ContainerInterface): void`
  and `setJobEnvelope(JobEnvelope): void` setters. Both are null at push/serialize time
  (harmless to serialize), set at pop time by the Worker.
- `handle()` keeps a signature compatible with `JobInterface::handle(): void`. When a
  `JobEnvelope` has been set, it does `verifyAndUnwrap($this->eventData)` exactly as
  today; otherwise it treats `eventData` as raw (preserving the existing
  no-envelope test path). It then resolves the observer via
  `$this->container->get($this->observerClass)` and calls `->handle($event)`, throwing a
  loud error when `$this->container` is null.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/queue/src/AsyncObserverJob.php`
    (POST-TIER-1: `handle(?callable $resolver = null, ?JobEnvelope $jobEnvelope = null)`;
    `$rawEventData = $jobEnvelope !== null ? $jobEnvelope->verifyAndUnwrap($this->eventData)
    : $this->eventData;` then `$event = unserialize($rawEventData);` then only acts
    `if ($resolver !== null)`, else no-op placeholder)
  - `/Users/markshust/Sites/marko/packages/queue/src/Worker.php`
    (POST-TIER-1: `__construct(QueueInterface, FailedJobRepositoryInterface, QueueConfig,
    JobEnvelope)`; `work()` calls `$job->incrementAttempts(); $job->handle();` with no
    resolver and no envelope)
  - `/Users/markshust/Sites/marko/packages/queue/src/JobEnvelope.php`
    (`wrap()`/`verifyAndUnwrap()` — the HMAC envelope the Worker holds and must hand to
    the job for `eventData` verification)
  - `/Users/markshust/Sites/marko/packages/queue/src/Job.php`,
    `/Users/markshust/Sites/marko/packages/queue/src/JobInterface.php`
    (`handle(): void` contract; jobs are `serialize($this)`)
  - `/Users/markshust/Sites/marko/packages/core/src/Event/EventDispatcher.php`
    (constructs `new AsyncObserverJob($definition->observerClass, serialize($event))`
    and `$this->queue->push($job)`)
  - `/Users/markshust/Sites/marko/packages/queue/tests/AsyncObserverJobTest.php`,
    `/Users/markshust/Sites/marko/packages/queue/tests/WorkerTest.php`
- Patterns to follow:
  - **Worker gains a `ContainerInterface` constructor dependency.** POST-TIER-1
    `Worker::__construct` takes `(QueueInterface, FailedJobRepositoryInterface,
    QueueConfig, JobEnvelope)` — FOUR params. To inject the container into a popped
    `AsyncObserverJob`, the Worker must hold a `ContainerInterface`. Add it as a new
    constructor param AFTER `JobEnvelope` (FIVE params total; autowired in production —
    `Worker` has no explicit binding, it is autowired; keep the existing arg order). This
    RIPPLES to every `new Worker(...)` call site, all in tests — and every site ALREADY
    passes the Tier-1 envelope helper as the 4th arg, so the container is the new 5th arg:
    `packages/queue/tests/WorkerTest.php` (7 sites: lines **255, 348, 435, 463, 547, 617,
    711**, each `new Worker($queue, $failedRepository, $config,
    createWorkerTestEnvelope())`) and `packages/queue/tests/Feature/IntegrationTest.php`
    (3 sites: lines **243, 346, 408**, each `new Worker(..., createIntegrationJobEnvelope())`;
    note line 408 uses `$syncQueue, $nullRepository, $queueConfig`). Update all 10 by
    APPENDING a container stub as the 5th argument — do NOT replace the envelope arg. Use
    Marko's container interface `Marko\Core\Container\ContainerInterface` (the same type
    `EventDispatcher` uses; `marko/queue` already requires `marko/core`).
  - **Worker special-cases `AsyncObserverJob`.** In `work()`, after popping, do
    `if ($job instanceof AsyncObserverJob) { $job->setContainer($this->container);
    $job->setJobEnvelope($this->jobEnvelope); }` before `$job->handle()`. Both `Worker`
    and `AsyncObserverJob` live in `marko/queue`, so this coupling is intra-package and
    acceptable. The Worker already holds `$this->jobEnvelope` (Tier-1 dep), so threading
    it to the job preserves signed-envelope verification.
  - **Serialization constraint (no magic methods).** `Job::serialize()` does
    `serialize($this)`, which includes every declared property. `AsyncObserverJob` holds
    `private ?ContainerInterface $container = null` and `private ?JobEnvelope $jobEnvelope
    = null`, with public `setContainer(ContainerInterface $container): void` and
    `setJobEnvelope(JobEnvelope $jobEnvelope): void` setters. Both are null at
    push/serialize time (EventDispatcher serializes the job before any worker sees it), so
    serializing null properties is harmless and no `__serialize`/`__sleep` magic method is
    needed (Marko bans magic methods). The Worker sets both at pop time and immediately
    calls `handle()`; the job is never re-serialized with a non-null container/envelope.
    `handle()` keeps a `JobInterface::handle(): void`-compatible signature (the public
    `$resolver` param is removed) and: (1) when `$this->jobEnvelope !== null`, does
    `verifyAndUnwrap($this->eventData)` before `unserialize` (preserving Tier-1
    verification); otherwise treats `eventData` as raw; (2) resolves the observer via
    `$this->container->get($this->observerClass)` and calls `->handle($event)`, throwing a
    loud error if `$this->container` is null. Do NOT re-introduce a raw
    `unserialize($this->eventData)` that bypasses the envelope.
  - **Update the existing `AsyncObserverJobTest` (THREE affected tests).** Removing the
    public `$resolver` param breaks every test that passes a resolver:
    (1) `it('handle executes observer')` (line ~45) calls `$job->handle(fn (...) =>
    $observer)`. (2) `it('verifies the envelope before unserializing AsyncObserverJob
    event data')` (line ~101) calls `$job->handle(fn (...) => $observer, $envelope)`.
    (3) `it('throws SerializationException when AsyncObserverJob event data is tampered')`
    (line ~136) calls `$job->handle(fn (...) => ..., $envelope)`. Rewrite ALL THREE to use
    `setContainer()` (+ `setJobEnvelope()` for the two envelope tests) before
    `handle()` — do NOT delete the two envelope tests; they are Tier-1 regression guards
    for signed-envelope verification and tamper detection and must keep passing. Keep the
    `serializes and unserializes correctly` test working (container + envelope stay null
    through the serialize round-trip).
  - `WorkerTest.php` already uses `FakeConfigRepository` and an anonymous
    `FailedJobRepositoryInterface`; follow that fixture style. For container
    resolution use a `Marko\Core\Container\ContainerInterface` stub that returns a
    recording observer.
- Tier-1 rebase note: the `eventData` deserialization is already on Tier 1's hardened
  seam (`$jobEnvelope->verifyAndUnwrap($this->eventData)` before `unserialize`). Keep that
  seam — thread the Worker's `JobEnvelope` to the job via `setJobEnvelope()` rather than
  re-adding a raw `unserialize($this->eventData)`.

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
- [ ] `it serializes and unserializes without the container or envelope (both are null
      across the serialize round-trip; no magic methods)`
- [ ] `it constructs a Worker with a ContainerInterface dependency (added after the
      existing JobEnvelope dep) and the worker injects both the container and the
      JobEnvelope into a popped AsyncObserverJob before calling handle()`
- [ ] `it verifies the signed envelope before unserializing eventData when a JobEnvelope
      has been set (Tier-1 verification path preserved, not bypassed)`
- [ ] `it throws SerializationException when the signed eventData is tampered and an
      envelope is set (Tier-1 tamper-detection regression guard still passes)`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
