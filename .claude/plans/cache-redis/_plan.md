# Plan: cache-redis Package

## Created
2026-02-23

## Status
completed

## Objective
Build `marko/cache-redis` — a Redis cache driver implementing CacheInterface using predis/predis, following the same patterns as cache-array and cache-file.

## Scope

### In Scope
- RedisConnection class with lazy Predis client creation (mirrors RabbitmqConnection pattern)
- RedisCacheDriver implementing all 9 CacheInterface methods
- Key validation using existing InvalidKeyException
- PHP serialization for values (consistency with cache-file)
- TTL support (null → config default, 0 → eternal, >0 → seconds)
- Key prefix support to namespace cache entries
- Comprehensive unit tests with mocked Predis client
- Package scaffolding (composer.json, module.php, Pest.php)
- Monorepo integration (root composer.json autoload entries)

### Out of Scope
- Redis Cluster or Sentinel support
- Redis-specific features (pub/sub, streams, etc.)
- Config file/class (follows RabbitmqConnection constructor-defaults pattern)
- Session-redis driver (separate package, future work)
- README.md (can be added later)

## Success Criteria
- [ ] All CacheInterface methods implemented and tested
- [ ] Tests pass with mocked Predis client (no real Redis needed)
- [ ] Module binds CacheInterface → RedisCacheDriver
- [ ] Package follows all Marko code standards
- [ ] Full test suite passes (`pest --parallel`)
- [ ] Linting passes (`php-cs-fixer fix`)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding and module tests | - | completed |
| 002 | Redis connection management | - | completed |
| 003 | RedisCacheDriver core CRUD operations | 001, 002 | completed |
| 004 | RedisCacheDriver batch and item operations | 003 | completed |

## Architecture Notes

### Pattern: Constructor defaults (like RabbitmqConnection)
RedisConnection takes host/port/password/database/prefix as constructor params with sensible defaults. No config class needed — users customize via module bindings or Preferences.

### Serialization
Uses PHP `serialize()`/`unserialize()` for consistency with cache-file driver. Stores serialized data in Redis string values.

### Key prefixing
All keys are prefixed with configurable prefix (default: `marko:cache:`) to namespace cache entries and avoid collisions with other Redis users.

### TTL semantics (same as array/file drivers)
- `null` ttl → use CacheConfig::defaultTtl()
- `0` ttl → never expire (Redis persistent key)
- `> 0` → seconds until expiration (Redis SETEX)

### Testing approach
Mock Predis client via protected `createClient()` override pattern (same as RabbitmqConnection's `createConnection()`).

### Dependency graph
```
001 ──┐
      ├──► 003 ──► 004
002 ──┘
```

## Risks & Mitigations
- **Predis API changes**: Pin to `^2.0` (stable, widely used)
- **Redis-specific behaviors**: All tests use mocked client, so behavior is fully controlled
