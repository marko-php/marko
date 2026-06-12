# Task 009: Serializable webhook and notification jobs that resolve services at handle-time

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
`DispatchWebhookJob` and `SendNotificationJob` hold live services (`WebhookDispatcherInterface`/`WebhookDeliveryService`/`QueueInterface` wrap a Guzzle client + PDO; `NotificationSender` wraps channels with PDO/mailer). `Job::serialize()` is `serialize($this)`, which PHP cannot do for closures/PDO — enqueueing onto a persistent driver crashes. Refactor both jobs to hold only serializable scalars/value-objects/ids and resolve their collaborators from the container at `handle()` time, mirroring `AsyncObserverJob`'s resolver pattern.

## Context
- Related files: `packages/webhook/src/Jobs/DispatchWebhookJob.php` (constructor ~18-25 holds dispatcher/deliveryService/config/queue, handle ~27-58 re-enqueues a `new self(...)` and calls `$this->queue->later(...)`), `packages/notification/src/Job/SendNotificationJob.php` (constructor ~14-19 holds `NotificationSender`, handle ~21-24), `packages/queue/src/Job.php` (`serialize()`/`unserialize()` 26-35, base setters live here), `packages/queue/src/AsyncObserverJob.php` (`setContainer()`/`setJobEnvelope()` setters + parameterless `handle()` — the EXISTING container seam), `packages/queue/src/Worker.php` (`work()` 27-63 — container/envelope are injected ONLY for `AsyncObserverJob` via the `instanceof` gate at 46-50), `packages/queue/src/JobInterface.php` (`handle(): void`), `packages/webhook/module.php`, `packages/notification/module.php` (boot resolves services from container), `packages/webhook/tests/Jobs/DispatchWebhookJobTest.php` + `DispatchWebhookJobRetryTest.php`
- Patterns to follow: store only the serializable payload (`WebhookPayload` value object, attempt number) / notifiable+notification data; resolve `WebhookDispatcherInterface`, `WebhookDeliveryService`, `ConfigRepositoryInterface`, `QueueInterface`, and `NotificationSender` from the container inside `handle()`; keep retry/backoff behavior; `WebhookPayload` and `NotificationInterface`/`NotifiableInterface` payloads must themselves be serializable.
- **Retry re-enqueue (DispatchWebhookJob):** `handle()` currently builds `new self($this->payload, $this->dispatcher, ..., $this->attemptNumber + 1)` and calls `$this->queue->later($delay, $nextJob)`. After the refactor the retry job must be constructed in its payload-only shape (`new self($this->payload, $this->attemptNumber + 1)`), and the `QueueInterface` used for `later()` must be resolved from the container at handle-time, NOT held as a property. Likewise `SendNotificationJob::handle()` resolves `NotificationSender` from the container; the notification `module.php` boot already registers channels and needs no change.

### Cross-tier sequencing + container-seam wiring (REQUIRED — verified against MERGED Tier1/Tier2 code)
`Job.php` and `Worker.php` are shared with Tier 1 (`Job::serialize()`/`unserialize()` HMAC envelope — Tier 1 Task 015) and Tier 2 (`AsyncObserverJob` self-resolution + `Worker` wiring). Tier 1 + Tier 2 are ALREADY MERGED on this branch — read the current files, do not rebase against an imagined earlier state.

**Verified at review time — the container seam does NOT reach these jobs today:**
- `Worker::work()` injects the container ONLY for `AsyncObserverJob`:
  ```php
  if ($job instanceof AsyncObserverJob) {
      $job->setContainer($this->container);
      $job->setJobEnvelope($this->jobEnvelope);
  }
  $job->incrementAttempts();
  $job->handle();   // parameterless — no resolver argument exists
  ```
- The seam is the `setContainer()`/`setJobEnvelope()` setters defined on `AsyncObserverJob` (NOT on the base `Job`, NOT on `JobInterface`). `JobInterface::handle()` is `handle(): void`.

**Therefore this task MUST extend the seam so webhook/notification jobs receive the container in the real Worker path — not just via a manual `setContainer()` in a unit test.** The required wiring:
1. Lift the container/envelope-aware contract to a shared abstraction — a `ContainerAwareJobInterface` (with `setContainer()` / `setJobEnvelope()`) implemented by `AsyncObserverJob`, `DispatchWebhookJob`, and `SendNotificationJob` (or move the setters onto the base `Job` and have `AsyncObserverJob` keep its current ones). Keep the container/envelope as non-serialized nullable properties (never serialized).
2. Widen the `Worker::work()` gate from `instanceof AsyncObserverJob` to the shared interface so EVERY container-aware job gets `setContainer()`/`setJobEnvelope()` before `handle()`. Do NOT add a resolver argument to `Worker::work()` or a serialized container property.
3. Do not break the HMAC-signed `JobEnvelope` path (`wrap`/`verifyAndUnwrap`) — the envelope continues to wrap the serialized job in the failed-job and queue paths.

If anything about the existing seam is ambiguous at implementation time, escalate rather than inventing a parallel resolver.

## Requirements (Test Descriptions)
- [x] `it serializes and unserializes a DispatchWebhookJob without error`
- [x] `it dispatches the webhook payload when a unserialized DispatchWebhookJob is handled`
- [x] `it re-enqueues a retry job that is itself serializable after a webhook failure`
- [x] `it serializes and unserializes a SendNotificationJob without error`
- [x] `it sends the notification to the notifiables when an unserialized SendNotificationJob is handled`
- [x] `it holds only serializable data and no live service instances on either job`
- [x] `it receives the container from the Worker so a webhook or notification job resolves its services in the real Worker path` (drive through `Worker::work()`, not a manual setContainer() in the test, to prove the gate was widened beyond AsyncObserverJob)
- [x] `it re-enqueues a webhook retry resolving the queue from the container at handle-time`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Created `ContainerAwareJobInterface` in `packages/queue/src/` with `setContainer()` and `setJobEnvelope()` methods
- `AsyncObserverJob` updated to `implements ContainerAwareJobInterface` (refactored existing setters)
- `Worker::work()` gate widened from `instanceof AsyncObserverJob` to `instanceof ContainerAwareJobInterface`
- `DispatchWebhookJob` refactored: constructor now holds only `WebhookPayload` and `int $attemptNumber`; implements `ContainerAwareJobInterface`; resolves all services from container at `handle()` time
- `SendNotificationJob` refactored: constructor holds only `NotifiableInterface|array` and `NotificationInterface`; implements `ContainerAwareJobInterface`; resolves `NotificationSender` from container at `handle()` time
- `NotificationSender::queue()` updated to use new constructor (removed `$this` from args)
- Root cause of PHP fatal error in `SerializableWebhookJobTest.php`: dispatcher stub anonymous classes initially had `dispatch(): mixed` return type, which is incompatible with `WebhookDispatcherInterface::dispatch(): WebhookResponse` — PHP fatal error at class definition time caused silent process crash with exit code 2 and no test output. Fixed by using correct `WebhookResponse` return type in all stubs.
- Helper class pattern (`SerializableWebhookJobTestHelpers`) used instead of namespace-level functions to avoid PSR-4 autoload redeclaration issues.
