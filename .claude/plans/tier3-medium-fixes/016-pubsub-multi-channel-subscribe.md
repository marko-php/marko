# Task 016: pubsub drivers honor multi-channel subscriptions (redis + pgsql)

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
The pubsub `Subscription` contract is a single object that multiplexes ALL subscribed channels into one message stream — that contract is correct. Both shipped drivers break it:

- **redis** (`RedisSubscriber`): `subscribe(string ...$channels)` uses only `$channels[0]` (line 24) and `psubscribe(string ...$patterns)` uses only `$patterns[0]` (line 35) — every channel/pattern after the first is silently dropped.
- **pgsql** (`PgSqlSubscription`): `subscribe()` correctly opens a `PostgresListener` per channel, but `getIterator()` (lines 22-30) iterates the listeners SEQUENTIALLY (`foreach ($this->listeners as $listener) { foreach ($listener as $notification) {...} }`). The inner loop over listener 1 blocks forever, so notifications on later channels are never delivered.

Fix both: redis subscribes to ALL channels (and ALL patterns) and multiplexes them into one `Subscription`; pgsql multiplexes its listeners concurrently/non-blocking so any channel's notification yields. The interface stays unchanged.

## Context
This is one task spanning two driver packages. They are independent files and may be implemented as two sub-clusters, but ship together because they fix the same contract violation and share the multi-channel test shape.

- redis files:
  - `packages/pubsub-redis/src/Driver/RedisSubscriber.php` (`subscribe` 19-28 — `$channels[0]` at 24; `psubscribe` 30-40 — `$patterns[0]` at 35; `createAmphpSubscriber()` returns `AmphpRedisSubscriberInterface`)
  - `packages/pubsub-redis/src/Driver/RedisSubscription.php` (wraps a single `Amp\Redis\RedisSubscription`; channel-vs-pattern branch in `getIterator()`, `stripPrefix()`) — to carry multiple channels/patterns this likely needs to wrap multiple amphp subscriptions and multiplex them, or a sibling subscription type
  - `packages/pubsub-redis/tests/Driver/RedisSubscriberTest.php`, `RedisSubscriptionTest.php` (existing test patterns to mirror)
- pgsql files:
  - `packages/pubsub-pgsql/src/Driver/PgSqlSubscription.php` (`getIterator()` 22-30 sequential nested foreach; `cancel()` 32-37; ctor takes `PostgresListener[] $listeners` + `string $prefix`)
  - `packages/pubsub-pgsql/src/Driver/PgSqlSubscriber.php` (`subscribe()` 20-30 already builds a listener per channel — leave as is; `psubscribe()` already throws `PubSubException::patternSubscriptionNotSupported('pgsql')` — leave as is)
  - `packages/pubsub-pgsql/tests/Driver/PgSqlSubscriptionTest.php`, `PgSqlSubscriberTest.php`
- shared contract: `packages/pubsub/src/Subscription.php`, `packages/pubsub/src/SubscriberInterface.php`, `packages/pubsub/src/Message.php`
- Patterns to follow:
  - redis: loop ALL `$channels` (and ALL `$patterns`), subscribing each, and yield from whichever amphp subscription produces next. Keep prefix handling and the pattern-vs-channel `Message` shape (pattern messages carry `pattern:`).
  - pgsql: multiplex listeners so the iterator yields the next notification from ANY listener (non-blocking poll / concurrent await) instead of fully draining listener 0 first. Preserve prefix stripping and `cancel()` unlistening all listeners.
  - No interface changes; `subscribe()` / `psubscribe()` signatures stay `string ...$x`.

### Verification note (read at planning time — drift corrected)
- redis: confirmed `$channels[0]` (line 24) and `$patterns[0]` (line 35).
- pgsql: confirmed sequential `getIterator()` nested foreach (22-30). DRIFT vs. the original finding: pgsql `psubscribe()` does NOT silently drop patterns — it already throws `PubSubException::patternSubscriptionNotSupported('pgsql')`. So the "psubscribe multiple patterns works" requirement applies to the REDIS driver only; the pgsql bug is purely the sequential multiplex in `getIterator()`. Requirements below reflect this.

## Requirements (Test Descriptions)
- [ ] `it subscribes a redis subscription to every requested channel`
- [ ] `it delivers a redis message published to a non-first subscribed channel`
- [ ] `it subscribes a redis pattern subscription to every requested pattern`
- [ ] `it multiplexes pgsql listeners so a non-first channel notification is delivered`
- [ ] `it does not block pgsql delivery on the first listener`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
