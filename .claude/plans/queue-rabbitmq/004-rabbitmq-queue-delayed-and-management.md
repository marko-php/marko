# Task 004: RabbitmqQueue - Delayed & Management Operations

**Status**: done
**Depends on**: 003
**Retry count**: 0

## Description
Complete the `RabbitmqQueue` implementation with delayed message support (later, release) and queue management operations (size, clear). Delayed messages use the standard RabbitMQ TTL + DLX pattern: messages are published to a delay queue with per-message TTL, and when TTL expires they route via dead letter exchange to the main work queue.

## Context
- Related files: `packages/queue-rabbitmq/src/RabbitmqQueue.php` (from task 003)
- Patterns to follow: DatabaseQueue's later/release/size/clear signatures
- **Delay mechanism**: Publish to a delay queue (`{queue}_delay`) with per-message `expiration` header. Delay queue has `x-dead-letter-exchange` and `x-dead-letter-routing-key` pointing back to main exchange/queue. When TTL expires, message routes to main queue.
- `release()` with delay: nack the original message (don't requeue), then publish a new message through the delay mechanism
- `release()` without delay: nack the original message with requeue=true
- `size()`: use `queue_declare` passive mode which returns message count without modifying the queue
- `clear()`: use `queue_purge` to remove all messages

## Requirements (Test Descriptions)
- [x] `it queues delayed job with TTL expiration header`
- [x] `it declares delay queue with dead letter exchange configuration`
- [x] `it returns job ID for delayed job`
- [x] `it releases job back to queue immediately when no delay`
- [x] `it releases job with delay via delay queue mechanism`
- [x] `it returns false when releasing unknown job ID`
- [x] `it returns queue size via passive declare`
- [x] `it returns zero size for empty or non-existent queue`
- [x] `it clears all messages from queue via purge`

## Acceptance Criteria
- Delayed messages use per-message TTL + DLX (no plugin dependency)
- Delay queue (`{queue}_delay`) declared automatically when needed
- size() uses passive queue declare (no side effects)
- clear() uses queue purge
- All requirements have passing tests
- All QueueInterface methods now fully implemented
