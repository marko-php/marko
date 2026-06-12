# Task 006: F4a — RabbitmqQueue attempt persistence + release-to-origin-queue + per-queue declare

**Status**: complete
**Depends on**: [004]
**Retry count**: 0

## Description
RabbitMQ jobs retry forever. `RabbitmqQueue::release()` republishes the original
stored payload (`$this->messagePayloads[$jobId]`) without persisting the incremented
attempt count, so `Worker`'s `attempts < maxAttempts` check never terminates and the
job never reaches the failed-job repo. The release delay path also hardcodes
`$this->defaultQueue` instead of the job's originating queue. Persist incremented
attempts into the republished payload and release to the correct queue.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/queue-rabbitmq/src/RabbitmqQueue.php`
    (`pop()` tracks `$this->deliveryTags[$jobId]` and `$this->messagePayloads[$jobId]`
    from the originating queue; `release($jobId, $delay)` re-publishes
    `$this->messagePayloads[$jobId]` and, in the delayed branch, declares
    `$this->defaultQueue . '_delay'` — hardcoded default queue; `declare()` is gated by
    a single `$this->declared` bool so a second distinct queue is never declared)
  - `/Users/markshust/Sites/marko/packages/queue/src/Job.php`
    (`attempts`, `incrementAttempts()`, `serialize()`, `unserialize()`)
  - `/Users/markshust/Sites/marko/packages/queue/src/Worker.php`
    (`handleFailedJob` releases with exponential delay while `attempts < maxAttempts`)
  - `/Users/markshust/Sites/marko/packages/queue-rabbitmq/tests/RabbitmqQueueTest.php`
    (uses a `MockQueueChannel extends AMQPChannel` recording fake — follow that seam)
  - `/Users/markshust/Sites/marko/packages/queue-rabbitmq/src/RabbitmqConnection.php`
    (`channel()` returns the AMQPChannel; tests inject a connection wrapping the mock channel)
- Patterns to follow:
  - **Attempt persistence requires ack+republish, not raw nack-requeue.** The current
    delay-0 branch does `basic_nack($deliveryTag, false, requeue: true)`, which makes
    RabbitMQ redeliver the ORIGINAL unchanged body — there is no way to bump the attempt
    count via nack-requeue. To persist an incremented attempt on ANY release, you must
    `basic_ack` (or nack without requeue) the original delivery and `basic_publish` a NEW
    message whose body is the re-serialized job with `attempts` incremented. Apply this
    to BOTH branches (delay 0 and delay > 0) so `release()` always persists the higher
    attempt count. (Note: in practice `Worker::handleFailedJob` always passes a delay > 0
    — `pow(2, attempts) * 10` — so the delayed branch is the hot path, but the public
    `release($jobId, 0)` contract must persist attempts too.)
  - Deserialize the tracked payload, increment its attempts (or re-serialize the worker's
    already-incremented job), and publish the updated body so the next `pop()` sees the
    higher attempt count. Preserve the `job_id` application header on republish.
  - Track and use the originating queue name per job (a `$this->queueNames[$jobId]` map
    populated in `pop()`, alongside delivery tag / payload) so release returns to that
    queue and `_delay` is derived from it.
  - Fix `declare()` so distinct queues each get declared (per-queue tracking — e.g.
    `array<string, true> $declaredQueues` — not a single global `$declared` bool). Note
    the exchange only needs declaring once but each queue needs its own
    `queue_declare`/`queue_bind`.
  - Tier-1 rebase note: `unserialize($message->getBody())` is touched by Tier 1's
    unserialize hardening; rebase onto that seam.

## Requirements (Test Descriptions)
- [x] `it republishes a released job with an incremented attempt count so a subsequent
      pop() observes attempts greater than the original`
- [x] `it terminates retries: after maxAttempts releases the attempt count reaches
      maxAttempts so the worker stops retrying and the job is eligible for the failed store`
- [x] `it releases a job back to its originating queue, not the hardcoded default queue`
- [x] `it derives the delay queue name from the originating queue when releasing with a delay`
- [x] `it declares each distinct queue (a second queue name triggers its own queue_declare)`
- [x] `it preserves the job id when republishing a released job`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes

- Changed `release()` from `basic_nack(requeue: true)` to `basic_ack + basic_publish` for BOTH branches (delay=0 and delay>0) so attempt counts are persisted in the republished payload.
- Added `$queueNames[$jobId]` map populated in `pop()` alongside delivery tags and payloads, so `release()` republishes to the originating queue rather than the hardcoded `$this->defaultQueue`.
- Replaced single `$declared: bool` with `$declaredQueues: array<string, true>` (per-queue tracking) and a separate `$exchangeDeclared: bool` (exchange only declared once) so distinct queues each get their own `queue_declare`/`queue_bind` on first use.
- Updated two existing release tests (`it releases job back to queue immediately when no delay` and `it releases job with delay via delay queue mechanism`) to expect `basic_ack + basic_publish` instead of `basic_nack`.
- All 6 new requirement tests added; requirements 2–6 passed immediately since the implementation was complete after requirement 1.
