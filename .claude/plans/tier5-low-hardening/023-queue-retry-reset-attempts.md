# Task 023: RetryCommand doesn't reset attempts

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`RetryCommand` re-queues a failed job by unserializing the stored payload and pushing it back as-is: `$job = unserialize($failedJob->payload); $this->queue->push($job, $failedJob->queue);`. The unserialized job carries its failure-time `attempts` count, which is already at (or above) `maxAttempts`. So the "retried" job goes straight back to failure on the next worker pass without ever performing a real retry — the worker's `if ($job->attempts < $job->maxAttempts)` check fails immediately. Reset the job's `attempts` to 0 when retrying, in both the single-job (`retryJob`) and bulk (`retryAll`) paths.

## Description-note
A retry must actually give the job fresh attempts; otherwise the command is a no-op that re-fails immediately. `Job::$attempts` is `public private(set) int = 0` with only an `incrementAttempts()` mutator and no reset, so the reset capability must be added to the Job (and the `JobInterface` contract if retry is to work polymorphically).

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/queue/src/Command/RetryCommand.php` (`retryJob()` ~75-99 — `unserialize` then `queue->push($job, ...)` then `failedJobRepository->delete(...)`; `retryAll()` ~49-72 — same pattern in a `foreach`)
  - `/Users/markshust/Sites/marko/packages/queue/src/Job.php` (`public private(set) int $attempts = 0;` ~11; `incrementAttempts(): void { $this->attempts++; }` ~21-24; `serialize()`/`unserialize()` ~26-31 — NO reset method)
  - `/Users/markshust/Sites/marko/packages/queue/src/JobInterface.php` (`public int $attempts { get; }` ~11, `public int $maxAttempts { get; }` ~13 — read-only get hooks on the interface)
  - `/Users/markshust/Sites/marko/packages/queue/src/Worker.php` (~63 — `if ($job->attempts < $job->maxAttempts) { $delay = pow(2, $job->attempts) * 10; ... }` — this is the gate a non-reset retry fails immediately)
  - Tests: `/Users/markshust/Sites/marko/packages/queue/tests/` (locate the existing `RetryCommandTest.php`)
- Verified findings (source-confirmed):
  - `RetryCommand` pushes the unserialized failed job with no attempt reset, in both single and bulk paths.
  - `Job::$attempts` is `private(set)` with only `incrementAttempts()`; there is no reset method and `JobInterface` exposes `attempts` as a get-only hook.
  - `Worker` gates real retries on `$job->attempts < $job->maxAttempts`, confirming a non-reset retry re-fails at once.
- Patterns to follow:
  - Add a `resetAttempts(): void` method to `Job` that sets `$this->attempts = 0` (allowed inside the class despite `private(set)`), and declare it on `JobInterface` so `RetryCommand` can call it on the `JobInterface` it holds. Follow code standards: typed return, `@throws` if applicable (none here).
  - In `retryJob()` and `retryAll()`, call `$job->resetAttempts()` after unserializing and BEFORE `queue->push(...)`.
  - Keep `serialize`/`unserialize` and the failed-job-delete ordering unchanged.

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
- CROSS-TIER COORDINATION (Tier 2): Tier 2 queue tasks 004 / 005 / 006 change the attempt-counting model (how/when `attempts` is incremented and how failures are recorded). This task must CONSUME that attempt model, not fight it — if Tier 2 introduces a different reset/increment API or moves attempt tracking, adopt it here rather than adding a parallel mechanism. Rebase onto Tier 2's queue changes first, re-locate `Job::$attempts` / the increment+reset API, and re-run `packages/queue/tests/` after rebasing.
