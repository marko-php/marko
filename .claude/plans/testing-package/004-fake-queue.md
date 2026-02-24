# Task 004: FakeQueue

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create a `FakeQueue` that implements `QueueInterface` from `marko/queue`. It captures all pushed jobs in memory and provides assertion methods for verifying jobs were queued in tests.

## Context
- Related files:
  - `packages/queue/src/QueueInterface.php` - interface to implement (7 methods: push, later, pop, size, clear, delete, release)
  - `packages/queue/src/JobInterface.php` - job interface
- Location: `packages/testing/src/Fake/FakeQueue.php`

## Requirements (Test Descriptions)
- [ ] `it implements QueueInterface`
- [ ] `it captures pushed jobs with queue name`
- [ ] `it captures delayed jobs with delay and queue name`
- [ ] `it pops jobs in FIFO order`
- [ ] `it returns null when popping from empty queue`
- [ ] `it returns queue size`
- [ ] `it clears all jobs from a queue`
- [ ] `it deletes a specific job by ID`
- [ ] `it asserts job was pushed by class name`
- [ ] `it throws AssertionFailedException when asserting pushed job that was not pushed`
- [ ] `it asserts job was not pushed`
- [ ] `it asserts pushed count`
- [ ] `it asserts nothing was pushed`

## Acceptance Criteria
- All requirements have passing tests
- Implements `QueueInterface` from `marko/queue`
- Supports multiple named queues
- Jobs get sequential string IDs for tracking
- Code follows all code standards

## Implementation Notes
### Public API
```php
class FakeQueue implements QueueInterface
{
    /** @var array<array{job: JobInterface, queue: ?string, delay: int, id: string}> */
    public private(set) array $pushed = [];

    public function push(JobInterface $job, ?string $queue = null): string;
    public function later(int $delay, JobInterface $job, ?string $queue = null): string;
    public function pop(?string $queue = null): ?JobInterface;
    public function size(?string $queue = null): int;
    public function clear(?string $queue = null): int;
    public function delete(string $jobId): bool;
    public function release(string $jobId, int $delay = 0): bool;
    public function assertPushed(string $jobClass, ?callable $callback = null): void;
    public function assertNotPushed(string $jobClass): void;
    public function assertPushedCount(int $expected): void;
    public function assertNothingPushed(): void;
}
```
