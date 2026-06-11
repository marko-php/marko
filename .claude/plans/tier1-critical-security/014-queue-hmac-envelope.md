# Task 014: F6 — HMAC-signed envelope for queue job payloads (queue + broker drivers + Worker + Commands)

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
Close PHP object injection across EVERY queue `unserialize()` sink. **CORRECTED scope (the original draft mis-classified the broker drivers):** the two broker drivers call bare `unserialize()` DIRECTLY on the stored `Job::serialize()` output — `queue-database/DatabaseQueue.php:120` (`unserialize($row['payload'])`) and `queue-rabbitmq/RabbitmqQueue.php:120` (`unserialize($message->getBody())`). These are the two highest-traffic injection sinks and MUST be hardened in Tier 1. There are also bare `unserialize()` calls in `Job::unserialize()` (:34), `AsyncObserverJob::handle()` (:17, on `eventData`), `RetryCommand` (:64, :89), and `FailedCommand` (:73). The `Worker` (:70) serialises a failed job's payload for storage.

**DI seam reality (CRITICAL):** `Job::serialize()` is a constructor-less instance method and `Job::unserialize()` is `static` returning `static` — neither can read a container-injected key. Therefore the HMAC signer CANNOT live inside `Job`. Introduce a `JobEnvelope` signer service in `marko/queue` (container-managed, injected with the app key from `marko/encryption` config). The signer exposes `wrap(string $serialized): string` and `verifyAndUnwrap(string $envelope): string` (verify HMAC with `hash_equals()` BEFORE returning the inner bytes; throw `SerializationException` on mismatch). Every caller that surrounds a raw (un)serialize uses the injected `JobEnvelope`:
- **Broker drivers** (`DatabaseQueue`, `RabbitmqQueue`): inject `JobEnvelope`. On store, wrap `$job->serialize()`; on read, `verifyAndUnwrap($payload)` then `unserialize(...)` (or `JobClass::unserialize(...)`).
- **Worker** (:70): wrap the failed-job payload before storing it in the failed-job repository.
- **`RetryCommand` / `FailedCommand`**: inject `JobEnvelope`; `verifyAndUnwrap()` before `unserialize()`.
- **`AsyncObserverJob`**: its `eventData` must also be wrapped at construction time and verified before `unserialize($this->eventData)`. Since `AsyncObserverJob` is constructed by application code (and `handle()` may run without a container), pass the signer to `handle()` via the existing `?callable $resolver` seam OR wrap the `eventData` string at construction. Pick the approach that keeps `handle()` verifiable; document it.

`Job::serialize()` may stay raw `serialize($this)` (the envelope is applied by the wrapping caller), OR `Job` can be left untouched and ALL wrapping/verification done by the callers. Do NOT give `Job` a container dependency. Keep `JobInterface::serialize()`/`static unserialize()` signatures unchanged; document that the on-wire payload is the envelope.

There is NO `packages/queue/module.php` today — CREATE one to bind/register the `JobEnvelope` signer (and wire it into the broker driver bindings in their respective `module.php` files: `queue-database/module.php`, `queue-rabbitmq/module.php`).

**Cross-tier note:** Tier 2 also edits these queue files (Job, AsyncObserverJob, Worker, the broker drivers, the Commands) for retry/double-processing fixes and MUST rebase onto this task's envelope shape. Run this first, sequentially.

## Context
- Related files:
  - `packages/queue/src/JobEnvelope.php` (NEW — the signer service)
  - `packages/queue/module.php` (NEW — bind `JobEnvelope`, inject `EncryptionConfig`/`ConfigRepositoryInterface`)
  - `packages/queue/composer.json` (add `marko/encryption` to `require`)
  - `packages/queue/src/AsyncObserverJob.php` (`unserialize($this->eventData)` ~17 — verify before unwrap)
  - `packages/queue/src/Worker.php` (`$job->serialize()` ~70 — wrap before storing failed payload)
  - `packages/queue/src/Command/RetryCommand.php` (`unserialize($failedJob->payload)` ~64, ~89 — inject signer, verify first)
  - `packages/queue/src/Command/FailedCommand.php` (`@unserialize($payload)` ~73 — inject signer, `verifyAndUnwrap()` BEFORE `@unserialize()`). CORRECTION: `extractJobClass()` checks `is_array($data) && isset($data['class'])`, but the stored payload is `serialize($this)` (an OBJECT, not an array with a `class` key), so this branch is already dead and returns `'Unknown'` for real jobs today. Do NOT invent a `class` key. After unwrap the inner bytes are still the object serialization; preserve the existing `'Unknown'` fallback. The only change here is to verify-then-unwrap before `@unserialize()`.
  - `packages/queue/src/Exceptions/SerializationException.php` (add a signature-mismatch factory)
  - `packages/queue/src/Job.php` / `JobInterface.php` (signatures unchanged; document the envelope is applied by callers)
  - `packages/queue-database/src/DatabaseQueue.php` (store `$job->serialize()` ~52 → wrap; `unserialize($row['payload'])` ~120 → verifyAndUnwrap)
  - `packages/queue-database/module.php` (inject `JobEnvelope` into `DatabaseQueue`)
  - `packages/queue-database/composer.json` (already requires `marko/queue`; add `marko/encryption` only if the driver constructs the signer itself — prefer receiving the bound `JobEnvelope` from `marko/queue` so no new require is needed)
  - `packages/queue-rabbitmq/src/RabbitmqQueue.php` (store `$job->serialize()` ~47/~91 → wrap; `unserialize($message->getBody())` ~120 → verifyAndUnwrap)
  - `packages/queue-rabbitmq/module.php` (inject `JobEnvelope`)
  - tests under `packages/queue/tests/`, `packages/queue-database/tests/`, `packages/queue-rabbitmq/tests/`
- Patterns to follow:
  - HMAC scheme (locked, identical to task 013): `hash_hmac('sha256', $serialized, $appKey)`, envelope `hex_hmac . '.' . $serialized`; the sha256 hex is fixed 64 chars, so split the first 64 chars + the `.` separator to recover the inner bytes unambiguously (the serialized payload contains `.`). Verify with `hash_equals()`.
  - App key via `marko/encryption` config (`encryption.key`). `encryption.php` defaults the key to `''` when `ENCRYPTION_KEY` is unset, so `EncryptionConfig::key()` returns `''` (does NOT throw). The signer MUST explicitly throw a loud `SerializationException`/`EncryptionException` on an empty key — no unsigned fallback.
  - Acyclic: `marko/encryption` depends only on core/config.
  - Existing queue tests call `serialize()`/`unserialize()` and assert round-trips — these MUST be updated to construct the signer (with a test key) and to expect the envelope shape. Do NOT leave them asserting bare `serialize()` output. The full list (verified by grep of `packages/queue/tests`): `JobTest`, `AsyncObserverJobTest`, `RetryCommandTest`, `FailedCommandTest`, `Feature/IntegrationTest`, AND the broker `*QueueTest` files under `queue-database/tests` and `queue-rabbitmq/tests`. `Feature/IntegrationTest` is a real-broker (`@group integration`) test — the signer must be wired through the integration harness there, not just supplied as a unit fixture.
  - DI-seam edits (constructor changes) and their bindings/test churn:
    - `Worker` (`packages/queue/src/Worker.php`) takes `(QueueInterface, FailedJobRepositoryInterface, QueueConfig)`; add `JobEnvelope` as a new required param (it is autowired, so the binding is unaffected, but every `new Worker(...)` in tests must pass the signer).
    - `DatabaseQueue` takes `(ConnectionInterface, string $table = 'jobs', string $defaultQueue = 'default')`. VERIFIED: `queue-database/module.php` uses a SIMPLE class-mapping binding (`QueueInterface::class => DatabaseQueue::class`), so the container autowires it and fills the two `string` params from their constructor DEFAULTS. Adding `JobEnvelope` (a container-resolvable type) keeps it autowirable — NO closure conversion needed and `module.php` need not change. CRITICAL ordering: `JobEnvelope` MUST be placed BEFORE the defaulted `string $table`/`string $defaultQueue` params (a required param cannot follow an optional one in PHP), e.g. `(ConnectionInterface, JobEnvelope, string $table = 'jobs', string $defaultQueue = 'default')`. Update direct-construction test setups in `queue-database/tests`.
    - `RabbitmqQueue` takes `(RabbitmqConnection, ExchangeConfig, string $defaultQueue = 'default')`. VERIFIED: `queue-rabbitmq/module.php` is also a simple class-mapping binding — same autowire + ordering rule (place `JobEnvelope` before the defaulted `string $defaultQueue`). Update direct-construction test setups in `queue-rabbitmq/tests`.
  - Signer key in tests: depend on `EncryptionConfig` (or `ConfigRepositoryInterface`). `EncryptionConfig::key()` calls `$config->getString('encryption.key')`, which THROWS `ConfigNotFoundException` if the key is entirely absent (it returns `''` only when `encryption.php` is loaded with `ENCRYPTION_KEY` unset). In isolated queue unit tests, seed the key explicitly via `new FakeConfigRepository(['encryption.key' => '<test-key>'])` (flat dot-notation) to get deterministic control over both the populated-key and empty-key (`''`) cases.

## Requirements (Test Descriptions)
- [ ] `it wraps a serialized job in an HMAC-signed envelope via JobEnvelope`
- [ ] `it verifies and unwraps a legitimately signed envelope`
- [ ] `it throws SerializationException when the envelope HMAC does not verify`
- [ ] `it refuses to unwrap a payload that has been tampered with`
- [ ] `it throws loudly when the signing key is empty`
- [ ] `it verifies the envelope before unserializing in DatabaseQueue::pop()`
- [ ] `it rejects a tampered DatabaseQueue payload before unserializing`
- [ ] `it verifies the envelope before unserializing in RabbitmqQueue (pop/consume)`
- [ ] `it rejects a tampered RabbitmqQueue payload before unserializing`
- [ ] `it verifies the envelope before unserializing AsyncObserverJob event data`
- [ ] `it rejects a tampered failed-job payload in the retry command`
- [ ] `it verifies the envelope before unserializing in the failed command and preserves the Unknown fallback`
- [ ] `it round-trips a legitimate job through DatabaseQueue push and pop`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
