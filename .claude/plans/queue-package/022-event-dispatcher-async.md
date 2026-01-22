# Task 022: Update EventDispatcher for Async Observers

**Status**: completed
**Depends on**: 004, 020, 021
**Retry count**: 0

## Description
Modify EventDispatcher to queue async observers instead of executing immediately.

## Context
- EventDispatcher accepts optional QueueInterface
- When observer has async=true and queue is available, queue the job
- Falls back to immediate execution if no queue
- Gracefully handles null queue (async observers run synchronously)

## Requirements (Test Descriptions)
- [ ] `EventDispatcher accepts optional queue`
- [ ] `EventDispatcher queues async observers`
- [ ] `EventDispatcher executes sync observers immediately`
- [ ] `EventDispatcher falls back when no queue`

## Implementation Notes
- Modify packages/core/src/Event/EventDispatcher.php
