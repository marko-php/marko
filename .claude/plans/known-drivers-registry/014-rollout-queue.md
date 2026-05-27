# Task 014: Roll out known-drivers pattern — marko/queue

**Status**: pending
**Depends on**: 001, 004
**Retry count**: 0

## Description
Apply the pilot pattern to `marko/queue`. Three drivers: `queue-sync`, `queue-database`, `queue-rabbitmq`. All bind `QueueInterface` and `FailedJobRepositoryInterface` and are mutually exclusive.

## Context
- Interfaces: `Marko\Queue\QueueInterface`, `Marko\Queue\FailedJobRepositoryInterface`
- Drivers: `marko/queue-sync`, `marko/queue-database`, `marko/queue-rabbitmq`
- Recommended-first ordering: `queue-sync` (zero-infrastructure default — runs jobs inline; appropriate for dev and simple apps); `queue-database` for production without extra infra; `queue-rabbitmq` for high-throughput / production with RabbitMQ available
- Confirmed in audit: all three bind QueueInterface AND FailedJobRepositoryInterface

**Description text for known-drivers.php:**
- `marko/queue-sync` → `'Synchronous queue driver (recommended for development — runs jobs inline, no infrastructure)'`
- `marko/queue-database` → `'Database-backed queue driver (production-ready; uses your existing database)'`
- `marko/queue-rabbitmq` → `'RabbitMQ queue driver (recommended for high-throughput production deployments)'`

## Sub-steps
1. Create `packages/queue/known-drivers.php`
2. Refactor `packages/queue/src/Exceptions/NoDriverException.php`. Update existing `packages/queue/tests/NoDriverExceptionTest.php` to match the new output format.
3. Add `packages/queue/tests/KnownDriversValidationTest.php`
4. Verify `marko/testing` is in `packages/queue/composer.json` `require-dev`; add it if missing

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing all three queue drivers`
- [ ] `it lists marko/queue-sync first as the recommended development default`
- [ ] `queue NoDriverException reads from known-drivers.php and includes docs URLs`

## Acceptance Criteria
- `packages/queue/known-drivers.php` exists with three entries
- `NoDriverException` refactored
- Validation test passes
- Existing queue tests still pass
- Code follows code standards
