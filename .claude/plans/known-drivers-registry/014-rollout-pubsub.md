# Task 014: Roll out known-drivers pattern — marko/pubsub

**Status**: pending
**Depends on**: 001, 005
**Retry count**: 0

## Description
Apply the pilot pattern to `marko/pubsub`. Two drivers: `pubsub-pgsql`, `pubsub-redis`. Both bind `PublisherInterface` AND `SubscriberInterface` and are mutually exclusive.

## Context
- Interfaces: `Marko\PubSub\PublisherInterface`, `Marko\PubSub\SubscriberInterface`
- Drivers: `marko/pubsub-pgsql`, `marko/pubsub-redis`
- Recommended-first ordering: `pubsub-redis` (purpose-built for pub/sub; pgsql LISTEN/NOTIFY is functional but optimized for simpler use cases)
- Confirmed in audit: both bind PublisherInterface and SubscriberInterface

**Description text for known-drivers.php:**
- `marko/pubsub-redis` → `'Redis pub/sub driver (recommended — purpose-built for messaging)'`
- `marko/pubsub-pgsql` → `'PostgreSQL LISTEN/NOTIFY pub/sub driver (no additional infrastructure if you already use Postgres)'`

## Sub-steps
1. Create `packages/pubsub/known-drivers.php`
2. Refactor `packages/pubsub/src/Exceptions/NoDriverException.php`. Update existing `packages/pubsub/tests/Exceptions/NoDriverExceptionTest.php` to match the new output format.
3. Add mutual `conflict` blocks to both driver composer.json files
4. Add `packages/pubsub/tests/KnownDriversValidationTest.php`
5. Verify `marko/testing` is in `packages/pubsub/composer.json` `require-dev`; add it if missing

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing both pubsub drivers`
- [ ] `it lists marko/pubsub-redis first as the recommended driver`
- [ ] `pubsub NoDriverException reads from known-drivers.php and includes docs URLs`
- [ ] `each pubsub driver declares conflict with the sibling driver`
- [ ] `validation test confirms conflict blocks match known-drivers list`

## Acceptance Criteria
- `packages/pubsub/known-drivers.php` exists
- `NoDriverException` refactored
- Both driver composer.json files have correctly-populated `conflict` blocks
- Validation test passes
- Existing pubsub tests still pass
- Code follows code standards
