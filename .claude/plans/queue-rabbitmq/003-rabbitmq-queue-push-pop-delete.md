# Task 003: RabbitmqQueue - Push, Pop, Delete Operations

**Status**: complete
**Depends on**: 001, 002
**Retry count**: 0

## Description
Create the `RabbitmqQueue` class implementing `QueueInterface` with the core operations: push (publish message), pop (consume message with delivery tag tracking), and delete (ack message). Handles exchange/queue declaration, message serialization, and maps job IDs to AMQP delivery tags for in-flight message management.

## Context
- Related files: `packages/queue-database/src/DatabaseQueue.php` (sibling pattern), `packages/queue/src/QueueInterface.php`
- Patterns to follow: DatabaseQueue's method signatures, sibling module naming
- `push()` publishes a serialized job as an AMQP message with job ID in message header
- `pop()` uses `basic_get()` to retrieve next message, stores delivery tag mapped to job ID
- `delete()` looks up delivery tag by job ID and sends ack
- Exchange and queue are declared on first operation (lazy, idempotent)
- Job ID is generated the same way as DatabaseQueue (UUID v4)
- Routing key strategy: for direct exchange, routing key = queue name. For fanout, empty. For topic, configurable. For headers, uses header matching.

## Requirements (Test Descriptions)
- [x] `it implements QueueInterface`
- [x] `it pushes job to RabbitMQ queue and returns job ID`
- [x] `it sets job ID on pushed job`
- [x] `it publishes serialized job payload as message body`
- [x] `it stores job ID in message header`
- [x] `it pops next available job from queue`
- [x] `it tracks delivery tag for popped job`
- [x] `it returns null when queue is empty on pop`
- [x] `it deletes job by acknowledging delivery tag`
- [x] `it returns false when deleting unknown job ID`
- [x] `it declares exchange and queue on first operation`
- [x] `it uses configured exchange type for declaration`

## Acceptance Criteria
- RabbitmqQueue class with constructor accepting RabbitmqConnection, ExchangeConfig, and default queue name
- All QueueInterface push/pop/delete methods implemented
- Delivery tag tracking via `array<string, int>` mapping
- Queue and exchange declared idempotently
- All requirements have passing tests
- Tests use mocked AMQPChannel (via connection's createConnection hook)
