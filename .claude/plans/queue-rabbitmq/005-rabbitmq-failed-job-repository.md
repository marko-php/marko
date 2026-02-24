# Task 005: RabbitmqFailedJobRepository

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Create the `RabbitmqFailedJobRepository` implementing `FailedJobRepositoryInterface` using RabbitMQ's dead letter exchange mechanism. Failed jobs are stored as messages in a dedicated `failed_jobs` queue. CRUD operations work by consuming/requeuing messages from this queue.

## Context
- Related files: `packages/queue-database/src/DatabaseFailedJobRepository.php` (sibling pattern), `packages/queue/src/FailedJobRepositoryInterface.php`, `packages/queue/src/FailedJob.php`
- Patterns to follow: DatabaseFailedJobRepository method signatures and behavior
- `store()` publishes a JSON-encoded FailedJob as a message to the `failed_jobs` queue with the job ID in the message ID property
- `all()` consumes all messages from the queue and requeues them, building an array of FailedJob objects
- `find()` scans messages looking for matching ID, requeues all
- `delete()` consumes messages, requeues all except the target
- `clear()` purges the `failed_jobs` queue
- `count()` uses passive queue declare to get message count
- Note: `all()`, `find()`, and `delete()` are O(n) - acceptable since failed jobs should be infrequent

## Requirements (Test Descriptions)
- [x] `it implements FailedJobRepositoryInterface`
- [x] `it stores failed job as message in failed jobs queue`
- [x] `it stores job ID as message ID property`
- [x] `it retrieves all failed jobs from queue`
- [x] `it returns empty array when no failed jobs exist`
- [x] `it finds failed job by ID`
- [x] `it returns null when failed job not found`
- [x] `it deletes failed job by ID and returns true`
- [x] `it returns false when deleting non-existent failed job`
- [x] `it clears all failed jobs via queue purge`
- [x] `it counts failed jobs via passive queue declare`

## Acceptance Criteria
- RabbitmqFailedJobRepository class with constructor accepting RabbitmqConnection
- All FailedJobRepositoryInterface methods implemented
- Failed jobs stored as JSON with all FailedJob fields preserved
- All requirements have passing tests
- Tests mock AMQPChannel operations
