# Plan: Pub/Sub System with AMPHP Async Foundation

## Created
2026-02-26

## Status
completed

## Objective
Build a real-time pub/sub messaging system for Marko with async foundation, supporting Redis and PostgreSQL LISTEN/NOTIFY drivers, integrated with SSE for browser push and auto-started by dev:up.

## Scope

### In Scope
- `marko/pubsub` — Interface package (PublisherInterface, SubscriberInterface, Subscription, Message)
- `marko/amphp` — Async foundation (event loop lifecycle, DI integration, pubsub:listen command, config)
- `marko/pubsub-redis` — Non-blocking Redis pub/sub driver via amphp/redis
- `marko/pubsub-pgsql` — Non-blocking PostgreSQL LISTEN/NOTIFY driver via amphp/postgres
- SSE integration — Modify `marko/sse` to consume Subscription directly
- Dev-server integration — `dev:up` auto-detects and starts `pubsub:listen`
- Config files, module.php bindings, exceptions, README.md for each package

### Out of Scope
- Generic async database wrappers (marko/amphp-mysql, marko/amphp-pgsql)
- Blocking Predis-based pub/sub driver
- amphp/http-server integration (async HTTP server)
- amphp/parallel integration
- Demo application customizations

## Success Criteria
- [ ] `PublisherInterface` and `SubscriberInterface` contracts are clean and driver-agnostic
- [ ] Redis driver publishes and subscribes via amphp/redis non-blocking
- [ ] PostgreSQL driver publishes via NOTIFY and subscribes via LISTEN non-blocking
- [ ] Both drivers follow sibling module conventions (identical patterns, naming, visibility)
- [ ] `SseStream` can consume a `Subscription` directly for real-time browser push
- [ ] `pubsub:listen` command runs the Revolt event loop for pub/sub
- [ ] `dev:up` auto-starts `pubsub:listen` when marko/pubsub is installed
- [ ] All tests passing with ≥80% coverage
- [ ] Code follows all Marko standards (strict types, constructor injection, @throws, etc.)
- [ ] README.md for each new package

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | marko/pubsub interfaces and value objects | - | completed |
| 002 | marko/pubsub config and exception classes | 001 | completed |
| 003 | marko/amphp event loop lifecycle and config | - | completed |
| 004 | pubsub:listen command | 003 | completed |
| 005 | marko/pubsub-redis connection and publisher | 001, 002, 003 | completed |
| 006 | marko/pubsub-redis subscriber and subscription | 005 | completed |
| 007 | marko/pubsub-pgsql connection and publisher | 001, 002, 003 | completed |
| 008 | marko/pubsub-pgsql subscriber and subscription | 007 | completed |
| 009 | Sibling consistency pass (redis + pgsql) | 006, 008 | completed |
| 010 | SSE integration — SseStream subscription support | 001 | completed |
| 011 | Package scaffolding and module.php bindings | 006, 008 | completed |
| 012 | README.md for all packages | 009, 010, 011, 013 | completed |
| 013 | Dev-server integration — auto-start pubsub:listen | 004, 011 | completed |

## Architecture Notes

### amphp API Usage

**Redis (amphp/redis v2):**
- `RedisConfig::fromUri('tcp://localhost:6379')` — config via URI
- `createRedisClient($config)` — returns `RedisClient` for PUBLISH
- `new RedisSubscriber(createRedisConnector($config))` — dedicated subscriber connection
- `$subscriber->subscribe('channel')` → `RedisSubscription` (implements `IteratorAggregate<int, string>`)
- `$subscriber->subscribeToPattern('prefix:*')` → `RedisSubscription` (yields `[payload, channel]` tuples)
- `$client->publish('channel', 'message')` — fire-and-forget publish
- Subscriber auto-reconnects on connection failure

**PostgreSQL (amphp/postgres v2):**
- `new PostgresConfig(host, port, user, password, database)` — typed config
- `Amp\Postgres\connect($config)` → `PostgresConnection`
- `$connection->listen('channel')` → `PostgresListener` (implements `Traversable<int, PostgresNotification>`)
- `$connection->notify('channel', 'payload')` — sends NOTIFY
- `PostgresNotification` has `->channel`, `->pid`, `->payload`
- Connection pool dedicates one connection for all listeners

### Marko Patterns to Follow
- Interface/implementation split: `marko/pubsub` defines contracts, drivers implement
- Sibling conventions: `pubsub-redis` and `pubsub-pgsql` must read as if written by same person
- Config in config files only, env vars only in config files
- Module.php bindings for DI wiring
- Constructor injection, no service locator
- MarkoException pattern (message, context, suggestion)
- Protected `createClient()` / `createConnection()` hooks for testability

### Key Design Decisions
- `Subscription` implements `IteratorAggregate<int, Message>` — works with `foreach`
- `Message` is a readonly value object with `channel`, `payload`, `?pattern`
- Channel prefixing handled by drivers using `pubsub.prefix` config
- Redis driver uses separate connections for publish (RedisClient) and subscribe (RedisSubscriber)
- PostgreSQL driver uses separate connections: pool connection for NOTIFY, dedicated connection for LISTEN
- SSE integration: `SseStream` accepts either `dataProvider` (current) or `subscription` (new)
- `pubsub:listen` command starts event loop, mirrors `queue:work` naming pattern
- `dev:up` auto-detects marko/pubsub via `class_exists()` and starts listener as managed process

## Risks & Mitigations
- **amphp requires running event loop**: The `pubsub:listen` command manages the loop lifecycle; drivers must work within it
- **Testing async code without real Redis/Postgres**: Use protected connection factory hooks (anonymous class override pattern) and test behavior through interface contracts
- **Postgres LISTEN/NOTIFY channel name restrictions**: PostgreSQL identifiers have 63-byte limit and are case-folded; validate channel names in driver
- **Pattern subscriptions not native in Postgres**: LISTEN doesn't support glob patterns; `psubscribe` throws PubSubException explaining limitation
