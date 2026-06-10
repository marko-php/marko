# Task 007: F4b — RabbitmqFailedJobRepository non-livelocking store

**Status**: pending
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
- [ ] `it sets the AMQP message_id to the failed job id when storing a failed job`
- [ ] `it returns all stored failed jobs from all() and the call terminates (no infinite
      basic_get/basic_nack requeue loop)`
- [ ] `it returns the matching failed job from find() by id and terminates`
- [ ] `it returns null from find() when no failed job matches the id`
- [ ] `it deletes only the matching failed job and returns true, leaving the others intact`
- [ ] `it returns false from delete() when no failed job matches the id`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
