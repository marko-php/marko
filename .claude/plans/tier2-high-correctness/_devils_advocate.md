# Devil's Advocate Review: tier2-high-correctness

Reviewed all 13 task files plus `_plan.md` against the ACTUAL post-Tier-1 source
tree on branch `feature/tier2-high-correctness`. Tier 1 (the `unserialize`
hardening + `JobEnvelope` signer seam) is already merged into this branch, and the
queue tasks were authored against the PRE-Tier-1 shape. Most of the plan is sound;
the queue-wiring task (004) is the main casualty of the rebase, and there is one
missed interface member in the SMTP socket task (010).

## Critical (Must fix before building)

### C1 — Task 004 describes pre-Tier-1 Worker / AsyncObserverJob signatures; the envelope-verification path is dropped
Task 004 says `Worker::__construct` "today takes
`(QueueInterface, FailedJobRepositoryInterface, QueueConfig)`" and that
`AsyncObserverJob::handle(?callable $resolver = null)` is the current signature.
Both are wrong on this branch — Tier 1 already changed them:

- ACTUAL `Worker::__construct(QueueInterface, FailedJobRepositoryInterface,
  QueueConfig, JobEnvelope $jobEnvelope)` — FOUR params (`packages/queue/src/Worker.php:15-20`).
  Adding `ContainerInterface` makes FIVE, and it must slot in without disturbing the
  existing `JobEnvelope` arg.
- ACTUAL `AsyncObserverJob::handle(?callable $resolver = null, ?JobEnvelope
  $jobEnvelope = null): void` — TWO optional params
  (`packages/queue/src/AsyncObserverJob.php:25-41`). The `eventData` is an
  HMAC-signed envelope; `handle()` calls `$jobEnvelope->verifyAndUnwrap($this->eventData)`
  before `unserialize`. Task 004 only mentions removing `$resolver`.

The load-bearing consequence: if `handle()` is reduced to the bare
`JobInterface::handle(): void` signature (no `$jobEnvelope`), the signed envelope can
no longer be verified — the job would either re-introduce a raw `unserialize` of the
signed bytes (Tier-1 regression, explicitly forbidden) or fail to unwrap. The Worker
already HOLDS a `JobEnvelope` (constructor dep). The fix must thread the envelope to
the job the same way it threads the container: set BOTH on the popped
`AsyncObserverJob` before calling `handle()` (e.g. a `setContainer()` AND a
`setJobEnvelope()` setter, or pass the envelope through a single `handle(JobEnvelope
$jobEnvelope)` — but that diverges from `JobInterface::handle(): void`). Recommended:
keep the container-and-envelope as nullable private properties set via setters at pop
time, and have `handle()` do `verifyAndUnwrap` when an envelope is present (mirroring
the current behaviour) then resolve+invoke via the container.

### C2 — Task 004's enumerated `new Worker(...)` call sites are stale (line numbers AND argument shape)
Task 004 lists "WorkerTest 7 sites (lines ~247, 340, 427, 455, 539, 609, 703)" and
"IntegrationTest 3 sites (~235, 337, 399)" each needing "a container stub" added.
Verified against the tree:

- WorkerTest sites are at lines **255, 348, 435, 463, 547, 617, 711** and ALREADY pass
  a 4th arg `createWorkerTestEnvelope()`:
  `new Worker($queue, $failedRepository, $config, createWorkerTestEnvelope())`.
- IntegrationTest sites are at lines **243, 346, 408** and ALREADY pass
  `createIntegrationJobEnvelope()` (line 408 uses `$syncQueue, $nullRepository,
  $queueConfig, ...`).

A worker following the stale line numbers / "add a container stub" instruction will
either fail to find the sites or produce `new Worker($q, $r, $c, $container)` — dropping
the now-mandatory `JobEnvelope` and breaking compilation. The fix is to add the
container as the FIFTH argument after the existing envelope helper at each site.

### C3 — Task 004 misses two Tier-1 envelope tests in AsyncObserverJobTest that the `$resolver` removal breaks
`packages/queue/tests/AsyncObserverJobTest.php` contains, in addition to
`'handle executes observer'` (line 45, calls `$job->handle(fn ... => $observer)`):

- `'verifies the envelope before unserializing AsyncObserverJob event data'`
  (line 101) — calls `$job->handle(fn ..., $envelope)` (resolver + envelope).
- `'throws SerializationException when AsyncObserverJob event data is tampered'`
  (line 136) — calls `$job->handle(fn ..., $envelope)` and asserts the signed-envelope
  tamper path throws.

Task 004 only mentions rewriting `'handle executes observer'`. Removing `$resolver`
breaks all THREE. The two envelope tests are Tier-1 regression guards for the signed
path and must be preserved (rewritten to use the container setter + envelope setter),
not deleted — otherwise the envelope-verification coverage Tier 1 added silently
disappears.

## Important (Should fix before building)

### I1 — Task 010 omits the `SocketInterface::$connected` property-hook member
`packages/mail-smtp/src/SocketInterface.php` declares, besides the methods,
`public bool $connected { get; }` (an interface property hook, lines 24-26). Task
010's requirement list enumerates only "connect, read, write, enableTls, close" and
the Context says "a `close()`-style method — confirm the full interface" — it never
surfaces `$connected`. A concrete `StreamSocket` that omits the `$connected` property
is fatal ("Class StreamSocket must implement property $connected"). Add the
`$connected` get-hook to the task so the worker implements it (backed by whether the
stream resource is open).

## Minor (Nice to address)

### M1 — Task 005 line references for `popJob`/transaction may drift
Task 005 cites specific behaviours in `DatabaseQueue::popJob()` (the unguarded UPDATE,
the attempts resync loop at the tail). Verified accurate against
`packages/queue-database/src/DatabaseQueue.php:132-150`, but no line numbers are
pinned, so this is informational only — the described shape matches.

### M2 — Task 013's `driverName()` coincidence
`VecSearch` already declares `driverName(): string` returning `'docs-vec'`
(`packages/docs-vec/src/VecSearch.php:34`), but this is the `DocsSearchInterface`
driver-name, entirely unrelated to Task 009's `ConnectionInterface::driverName()`.
No conflict — just noting so an implementer does not conflate them. The FTS-fusion bug
at lines 65-66 and the `ftsSearch` PDOException leak at line 116 match Task 013 exactly.

### M3 — F8 selector fallback already partially guarded
`ReadWriteConnection::query()` already filters out failed replicas with
`array_values(array_filter(...))` and a code comment naming the weight-realignment
limitation (`ReadWriteConnection.php:39-56`). Task 008's framing ("the fallback can
mis-target") is accurate; the existing comment is the v1 limitation the task replaces.

## Questions for the Team

### Q1 — Should `AsyncObserverJob` resolve the observer via the container, or keep an optional resolver for testability?
Task 004 removes the `$resolver` callable entirely in favour of container resolution.
That is cleaner and matches the locked F6 design, but it means every test of the
observer-invocation path must construct a real `ContainerInterface` stub rather than a
one-line closure. The plan accepts this; flagging only so the team confirms the
ergonomics trade-off (container stub per test vs. the deleted resolver seam) is
intended.

### Q2 — F2 shutdown-function un-registration
`register_shutdown_function` cannot be unwound in PHP, so `unregister()` (mirroring
`SimpleErrorHandler`) restores the error/exception handlers but leaves the shutdown
callback registered — it is a no-op at shutdown because `error_get_last()` is benign
and `handledFatalError` guards it. This is the same behaviour as the existing
`errors-simple` handler, so it is consistent, but the team should confirm the leaked
(harmless) shutdown registration across the parallel test suite is acceptable (it is,
per the SimpleErrorHandler precedent).
