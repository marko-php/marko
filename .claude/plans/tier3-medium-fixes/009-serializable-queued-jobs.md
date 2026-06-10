# Task 009: Serializable webhook and notification jobs that resolve services at handle-time

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`DispatchWebhookJob` and `SendNotificationJob` hold live services (`WebhookDispatcherInterface`/`WebhookDeliveryService`/`QueueInterface` wrap a Guzzle client + PDO; `NotificationSender` wraps channels with PDO/mailer). `Job::serialize()` is `serialize($this)`, which PHP cannot do for closures/PDO — enqueueing onto a persistent driver crashes. Refactor both jobs to hold only serializable scalars/value-objects/ids and resolve their collaborators from the container at `handle()` time, mirroring `AsyncObserverJob`'s resolver pattern.

## Context
- Related files: `packages/webhook/src/Jobs/DispatchWebhookJob.php` (constructor ~18-25 holds dispatcher/deliveryService/config/queue, handle ~27-58 re-enqueues a `new self(...)`), `packages/notification/src/Job/SendNotificationJob.php` (constructor ~14-19 holds `NotificationSender`, handle ~21-24), `packages/queue/src/Job.php` (`serialize()`/`unserialize()` ~26-35), `packages/queue/src/AsyncObserverJob.php` (`handle(?callable $resolver = null)` resolver pattern), `packages/queue/src/Worker.php` (`work()` pop → incrementAttempts → handle → delete ~27-50), `packages/webhook/module.php`, `packages/notification/module.php` (boot resolves services from container), `packages/webhook/tests/Jobs/DispatchWebhookJobTest.php` + `DispatchWebhookJobRetryTest.php`
- Patterns to follow: store only the serializable payload (`WebhookPayload` value object, attempt number) / notifiable+notification data; resolve `WebhookDispatcherInterface`, `WebhookDeliveryService`, `ConfigRepositoryInterface`, `QueueInterface`, and `NotificationSender` from the container inside `handle()` (mirror `AsyncObserverJob`'s resolver / however Tier 2 wires container access onto `Job`/`Worker`); keep retry/backoff behavior; `WebhookPayload` and `NotificationInterface`/`NotifiableInterface` payloads must themselves be serializable.

### Cross-tier sequencing (REQUIRED)
`Job.php` and `Worker.php` are shared with Tier 1 (`Job::serialize()`/`unserialize()` HMAC envelope — Tier 1 Task 015) and Tier 2 (`AsyncObserverJob` self-resolution + `Worker` wiring — Tier 2 Tasks 004/006). **Rebase order: Tier 1 → Tier 2 → this task.** Before implementing, rebase onto the merged Tier 1 + Tier 2 state.

**Verified at review time:** `Worker::work()` (queue/src/Worker.php ~39-42) currently calls `$job->handle()` with **zero arguments** and `Job::serialize()` is bare `serialize($this)`. `AsyncObserverJob::handle(?callable $resolver = null)` self-resolves only because the resolver defaults to null and the job reaches the container some other way. Tier 2 Task 006 is the task that wires container access onto `AsyncObserverJob`/`Worker` (it explicitly states the container must be injected post-deserialization, never serialized). **This task MUST consume whatever container/resolver seam Tier 2 lands — do NOT add a parallel resolver argument to `Worker::work()` or a serialized container property.** The tests below assert behavior (clean serialize + successful dispatch), not a specific resolver signature, so they survive whichever mechanism Tier 2 lands. If Tier 2 has not landed at implementation time, escalate rather than inventing a competing seam.

## Requirements (Test Descriptions)
- [ ] `it serializes and unserializes a DispatchWebhookJob without error`
- [ ] `it dispatches the webhook payload when a unserialized DispatchWebhookJob is handled`
- [ ] `it re-enqueues a retry job that is itself serializable after a webhook failure`
- [ ] `it serializes and unserializes a SendNotificationJob without error`
- [ ] `it sends the notification to the notifiables when an unserialized SendNotificationJob is handled`
- [ ] `it holds only serializable data and no live service instances on either job`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
