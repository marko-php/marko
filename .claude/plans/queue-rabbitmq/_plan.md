# Plan: Queue RabbitMQ Driver

## Created
2026-02-23

## Status
completed

## Objective
Create a `marko/queue-rabbitmq` package that implements `QueueInterface` and `FailedJobRepositoryInterface` using RabbitMQ via php-amqplib, with support for all exchange types (direct, fanout, topic, headers), TLS/SSL connections, and native dead letter exchange (DLX) for failed job handling.

## Scope

### In Scope
- RabbitMQ connection management via php-amqplib/php-amqplib
- TLS/SSL connection support (ca_cert, client_cert, client_key)
- Full `QueueInterface` implementation (push, later, pop, size, clear, delete, release)
- All exchange types: direct, fanout, topic, headers
- Delayed message support via per-message TTL + DLX pattern
- Dead letter exchange-based `FailedJobRepositoryInterface` implementation
- Delivery tag tracking for in-flight job management
- Module bindings (module.php)
- Consistent sibling module patterns matching queue-database and queue-sync

### Out of Scope
- RabbitMQ Management HTTP API integration
- Cluster/federation configuration
- Priority queues
- Quorum queues (can be added later)
- Consumer prefetch/QoS tuning (beyond basic defaults)
- rabbitmq-delayed-message-exchange plugin support

## Success Criteria
- [ ] `RabbitmqQueue` implements all `QueueInterface` methods
- [ ] `RabbitmqFailedJobRepository` implements all `FailedJobRepositoryInterface` methods
- [ ] All four exchange types configurable and functional
- [ ] TLS/SSL connections work via configuration
- [ ] Delayed messages work via TTL + DLX pattern
- [ ] Module bindings register correctly
- [ ] All tests passing
- [ ] Code follows project standards (strict types, constructor promotion, no final classes)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding & RabbitmqConnection | - | completed |
| 002 | Exchange configuration (ExchangeType enum, ExchangeConfig) | - | completed |
| 003 | RabbitmqQueue - push, pop, delete operations | 001, 002 | completed |
| 004 | RabbitmqQueue - later, release, size, clear operations | 003 | completed |
| 005 | RabbitmqFailedJobRepository (DLX-based) | 001 | completed |
| 006 | Module integration & configuration | 003, 004, 005 | completed |

## Dependency Graph
```
001 ─┬─► 003 ──► 004 ─┐
     │                 │
     └─► 005 ──────────┼─► 006
                       │
002 ──► 003 ───────────┘
```

## Architecture Notes

### Connection Pattern
`RabbitmqConnection` wraps php-amqplib's `AMQPStreamConnection`/`AMQPSSLConnection` with:
- Lazy connection (connect on first `channel()` call)
- `protected createConnection()` hook for test overrides (sibling module pattern)
- Channel reuse via single channel per connection

### Delayed Messages (TTL + DLX)
Standard RabbitMQ pattern without plugins:
1. Publish delayed messages to a delay queue with per-message TTL (`expiration` header)
2. Delay queue configured with `x-dead-letter-exchange` pointing to the main exchange
3. When TTL expires, message routes from delay queue → DLX → main work queue

Note: Per-message TTL has a head-of-queue limitation (messages only expire from head). Acceptable for v1.

### Delivery Tag Tracking
`pop()` returns a job but RabbitMQ requires the delivery tag for ack/nack. Track in-flight messages:
```php
/** @var array<string, int> */
private array $deliveryTags = [];  // jobId => deliveryTag
```
`delete()` looks up delivery tag and acks. `release()` nacks and republishes with delay if needed.

### Failed Jobs via DLX
- Main queue configured with `x-dead-letter-exchange` → `failed_jobs` exchange
- `store()` publishes FailedJob data as message to `failed_jobs` queue
- `all()` / `find()` consume + requeue from failed_jobs queue
- `delete()` consumes without requeuing target message
- `clear()` purges the failed_jobs queue
- `count()` uses passive queue declare to get message count

### Exchange Types
All four types supported via `ExchangeType` enum and `ExchangeConfig` value object:
- **Direct**: routing key = queue name (default)
- **Fanout**: broadcast to all bound queues
- **Topic**: pattern-based routing (e.g., `orders.*`)
- **Headers**: match on message headers (uses `x-match` argument)

## Risks & Mitigations
- **Per-message TTL head-of-queue limitation**: Document this behavior; for most use cases delay granularity is acceptable
- **Failed job CRUD via queue scanning is O(n)**: Acceptable since failed jobs should be infrequent and the queue small; document this trade-off
- **php-amqplib mocking in tests**: Use protected `createConnection()` hook and mock `AMQPChannel` methods
