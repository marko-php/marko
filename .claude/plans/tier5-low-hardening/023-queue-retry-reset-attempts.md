# Task 023: RetryCommand doesn't reset attempts

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`RetryCommand` re-queues a failed job by verifying+unwrapping the stored payload, unserializing it, and pushing it back as-is. The unserialized job carries its failure-time `attempts` count, which is already at (or above) `maxAttempts`. So the "retried" job goes straight back to failure on the next worker pass without ever performing a real retry — the worker's `if ($job->attempts < $job->maxAttempts)` check fails immediately. Reset the job's `attempts` to 0 when retrying, in both the single-job (`retryJob`) and bulk (`retryAll`) paths.

## Description-note
A retry must actually give the job fresh attempts; otherwise the command is a no-op that re-fails immediately. `Job::$attempts` is `public private(set) int = 0` with only an `incrementAttempts()` mutator and no reset, so the reset capability must be added to the Job (and the `JobInterface` contract if retry is to work polymorphically).

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/queue/src/Command/RetryCommand.php` — **POST-TIER-1/2 CURRENT shape (verified):** the command injects `JobEnvelope $jobEnvelope` (line 24) and `execute()` declares `@throws SerializationException`. BOTH paths unwrap the payload through the Tier-1 HMAC envelope BEFORE unserializing:
    - `retryAll()` (lines 58-82): `foreach (...) { $job = unserialize($this->jobEnvelope->verifyAndUnwrap($failedJob->payload)); $this->queue->push($job, $failedJob->queue); $this->failedJobRepository->delete($failedJob->id); }`
    - `retryJob()` (lines 87-112): `$job = unserialize($this->jobEnvelope->verifyAndUnwrap($failedJob->payload)); $this->queue->push($job, $failedJob->queue); $this->failedJobRepository->delete($jobId);`
    - **DO NOT** replace `unserialize($this->jobEnvelope->verifyAndUnwrap($failedJob->payload))` with a raw `unserialize($failedJob->payload)` — that would silently drop the Tier-1 HMAC integrity check. The `verifyAndUnwrap(...)` call MUST stay exactly as-is; you are ONLY inserting `$job->resetAttempts();` between the unserialize and the `queue->push(...)`.
  - `/Users/markshust/Sites/marko/packages/queue/src/Job.php` (`public private(set) int $attempts = 0;` line 11; `incrementAttempts(): void { $this->attempts++; }` lines 21-24; `serialize()`/`static unserialize()` lines 26-35 — NO reset method)
  - `/Users/markshust/Sites/marko/packages/queue/src/JobInterface.php` (`public int $attempts { get; }` line 11, `public int $maxAttempts { get; }` line 13 — read-only get hooks; `incrementAttempts(): void` already declared line 19)
  - `/Users/markshust/Sites/marko/packages/queue/src/Worker.php` (the `if ($job->attempts < $job->maxAttempts) { $delay = pow(2, $job->attempts) * 10; ... }` retry gate a non-reset retry fails immediately)
  - Tests: `/Users/markshust/Sites/marko/packages/queue/tests/` (locate the existing `RetryCommandTest.php` — note: its fixtures construct payloads via `JobEnvelope::wrap(...)`/the failed-job repository, NOT raw `serialize()`. Match how it builds a valid wrapped payload so `verifyAndUnwrap` succeeds.)
- Verified findings (source-confirmed, CURRENT branch):
  - `RetryCommand` pushes the verify-unwrapped+unserialized failed job with no attempt reset, in both single and bulk paths.
  - `Job::$attempts` is `private(set)` with only `incrementAttempts()`; there is no reset method and `JobInterface` exposes `attempts` as a get-only hook.
  - `Worker` gates real retries on `$job->attempts < $job->maxAttempts`, confirming a non-reset retry re-fails at once.
- Patterns to follow:
  - Add a `resetAttempts(): void` method to `Job` that sets `$this->attempts = 0` (allowed inside the class despite `private(set)`), and declare it on `JobInterface` so `RetryCommand` can call it on the `JobInterface` it holds. Follow code standards: typed return, `@throws` if applicable (none here).
  - In `retryJob()` and `retryAll()`, call `$job->resetAttempts()` AFTER `$job = unserialize($this->jobEnvelope->verifyAndUnwrap(...))` and BEFORE `queue->push(...)`. Leave the `verifyAndUnwrap(...)` envelope call and the `failedJobRepository->delete(...)` ordering untouched.

## Requirements (Test Descriptions)
- [ ] `it resets a retried job's attempts to zero before re-queuing`
- [ ] `it resets attempts when retrying all failed jobs`
- [ ] `it re-pushes the retried job to its original queue`
- [ ] `it deletes the failed-job record after retrying`

## Acceptance Criteria
- A retried job has `attempts === 0` when pushed back, so the worker performs real retries before it can fail again.
- Both single-job and bulk retry paths reset attempts.
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- CROSS-TIER (Tier 1 + Tier 2 ALREADY MERGED on this branch): Tier 1 added the `JobEnvelope` HMAC seam to `RetryCommand` (`verifyAndUnwrap`) and Tier 2 reworked the attempt model. The current source already reflects both — the Context section above quotes the CURRENT, merged shape. Consume the existing `Job::$attempts` (`public private(set) int = 0`) attempt model by adding `resetAttempts()` to it; do NOT add a parallel attempt mechanism, and do NOT touch the `JobEnvelope::verifyAndUnwrap(...)` unwrap. Re-run `packages/queue/tests/` after editing.
