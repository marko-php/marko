# Task 007: F4b — RabbitmqFailedJobRepository non-livelocking store

**Status**: complete
**Depends on**: [006]
**Retry count**: 0

## Description
`RabbitmqFailedJobRepository` stores failed jobs in a `failed_jobs` AMQP queue and
reads them with `while ($message = $channel->basic_get(...)) { ...; basic_nack(...,
requeue: true); }`. Because every read requeues the message it just fetched, the loop
re-fetches the same head message endlessly — `all()`, `find()`, and `delete()`
livelock and never terminate. Redesign the failed-job storage/read path so reads
terminate and return correct results, with stable message identification.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/queue-rabbitmq/src/RabbitmqFailedJobRepository.php`
    (`store()` publishes JSON to `failed_jobs` and ALREADY sets `'message_id' =>
    $failedJob->id` in the AMQPMessage properties — correction to an earlier reading:
    the message_id IS set today, so the existing message_id test is a regression guard,
    not a fix. The ACTUAL bug is purely the read loops: `all()` loops `basic_get` +
    `basic_nack(requeue:true)`, and `find($id)`/`delete($id)` nack-requeue every
    non-matching message, so the head message is re-fetched forever and the loop never
    terminates → livelock.)
  - `/Users/markshust/Sites/marko/packages/queue/src/FailedJob.php`,
    `/Users/markshust/Sites/marko/packages/queue/src/FailedJobRepositoryInterface.php`
    (`store/all/find/delete/clear` contract)
  - `/Users/markshust/Sites/marko/packages/queue-rabbitmq/tests/RabbitmqFailedJobRepositoryTest.php`
    (recording mock channel seam)
- Patterns to follow:
  - Set the AMQP `message_id` (= FailedJob id) on publish in `store()` so `find/delete`
    can match deterministically.
  - Make reads terminate: drain each message exactly once per call (track delivery
    tags / message ids seen this iteration, or ack-and-restore, or move to a dedicated
    store) so the head is not infinitely requeued. `all()` returns every stored failed
    job once; `find()` returns the matching one or null; `delete()` acks (removes) only
    the matching message and returns whether it existed.
  - Whichever storage shape is chosen, `delete($id)` must not lose the other failed
    jobs, and `clear()` must still purge.

## Requirements (Test Descriptions)
- [x] `it sets the AMQP message_id to the failed job id when storing a failed job`
- [x] `it returns all stored failed jobs from all() and the call terminates (no infinite
      basic_get/basic_nack requeue loop)`
- [x] `it returns the matching failed job from find() by id and terminates`
- [x] `it returns null from find() when no failed job matches the id`
- [x] `it deletes only the matching failed job and returns true, leaving the others intact`
- [x] `it returns false from delete() when no failed job matches the id`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes

Fixed the livelock bug in `RabbitmqFailedJobRepository` by replacing the `basic_nack(requeue:true)` pattern with a drain-then-restore approach:

- `all()`: drain all messages with `basic_ack`, deserialize them, re-publish all back
- `find(id)`: drain all with `basic_ack`, re-publish all back, return matching one
- `delete(id)`: drain all with `basic_ack`, re-publish only non-matching ones

Extracted three private helpers to eliminate duplication:
- `drain(channel)`: loops `basic_get` + `basic_ack` until empty, returns drained messages
- `republish(channel, message)`: re-publishes a drained message with original properties
- `publishPersistent(channel, body, messageId)`: creates and publishes a persistent AMQP message

The `message_id` property was already set correctly on `store()` — the fix was purely in the read paths.
