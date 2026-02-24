# Task 002: Redis Connection Management

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Create RedisConnection class that wraps Predis\Client with lazy connection, configurable host/port/password/database/prefix, and disconnect support. Follows the RabbitmqConnection pattern exactly.

## Context
- Related files:
  - `packages/queue-rabbitmq/src/RabbitmqConnection.php` (pattern to follow)
  - `packages/queue-rabbitmq/tests/RabbitmqConnectionTest.php` (test pattern to follow)
- Patterns to follow:
  - Lazy connection via protected `createClient()` method (overridable in tests)
  - Constructor params with sensible defaults
  - `public readonly` properties for config values
  - `client()`, `disconnect()`, `isConnected()` methods

## Requirements (Test Descriptions)
- [x] `it creates RedisConnection with default configuration`
- [x] `it creates RedisConnection with custom host port password and database`
- [x] `it lazily connects on first client call`
- [x] `it returns same client on subsequent calls`
- [x] `it reports connected status correctly`
- [x] `it disconnects and clears client reference`
- [x] `it reconnects after disconnect`

## Acceptance Criteria
- All requirements have passing tests
- RedisConnection follows RabbitmqConnection pattern exactly
- Protected createClient() method enables test mocking
- Code follows all Marko code standards

## Implementation Notes
- Created `packages/cache-redis/src/RedisConnection.php` following RabbitmqConnection pattern exactly
- Created `packages/cache-redis/tests/RedisConnectionTest.php` with mock client extending `Predis\Client` with no-op constructor
- Added `Marko\Cache\Redis\` and `Marko\Cache\Redis\Tests\` autoload entries to root `composer.json`
- Added `predis/predis:^2.0` to root `require-dev` (was already added by another task)
- All 7 tests pass, code passes php-cs-fixer with no changes needed
