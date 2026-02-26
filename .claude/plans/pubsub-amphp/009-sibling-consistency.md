# Task 009: Sibling Consistency Pass (Redis + PgSql)

**Status**: completed
**Depends on**: 006, 008
**Retry count**: 0

## Description
Review both pub/sub drivers for sibling consistency. Ensure both packages read as if written by the same person — identical naming, method visibility, PHPDoc format, test structure, class modifiers, and exception messages. Fix any inconsistencies.

## Context
- Follow `.claude/sibling-modules.md` checklist for adding siblings
- Both drivers must have identical method names for same-purpose methods
- Both must use same visibility (public/protected/private) for equivalent methods
- Both must use same class modifiers (readonly, etc.)
- Both must have same test structure and patterns
- Both must have same exception message formats
- Both must have same PHPDoc style (multi-line format)
- Config files should follow identical structure

## Requirements (Test Descriptions)
- [ ] `it has identical public method signatures on RedisPublisher and PgSqlPublisher`
- [ ] `it has identical public method signatures on RedisSubscriber and PgSqlSubscriber`
- [ ] `it has identical public method signatures on RedisSubscription and PgSqlSubscription`
- [ ] `it has identical method visibility for same-purpose methods across connections`
- [ ] `it uses consistent class modifiers across siblings`
- [ ] `it uses consistent exception message format across drivers`

## Acceptance Criteria
- All requirements have passing tests
- Both packages pass the sibling review checklist from `.claude/sibling-modules.md`
- Method names, visibility, PHPDoc style, class modifiers all match
- Test file organization mirrors between packages
- Config file structure matches between packages
- No "potentially polymorphic call" warnings in tests

## Implementation Notes

### Checklist to Verify
| Aspect | Redis | PgSql |
|--------|-------|-------|
| Connection class | `RedisPubSubConnection` | `PgSqlPubSubConnection` |
| Publisher class | `RedisPublisher` | `PgSqlPublisher` |
| Subscriber class | `RedisSubscriber` | `PgSqlSubscriber` |
| Subscription class | `RedisSubscription` | `PgSqlSubscription` |
| `disconnect()` visibility | must match | must match |
| `isConnected()` visibility | must match | must match |
| Protected hooks | `createClient()`, `createConnector()` | `createConnection()`, `createConfig()` |
| PHPDoc format | multi-line | multi-line |
| readonly class? | check consistency | check consistency |
| Test directory | `tests/Driver/` | `tests/Driver/` |

### Connection Method Consistency
Both connection classes must have:
- Same constructor parameter style (public readonly)
- Same lazy initialization pattern
- `disconnect(): void` — same visibility
- `isConnected(): bool` — same visibility
- Protected creation hooks — must exist in both even if named differently for driver-specific reasons

### Exception Message Format
```php
// Must be consistent format
"Failed to publish to Redis channel '{$channel}'"
"Failed to publish to PostgreSQL channel '{$channel}'"
// Not:
"Redis publish failed on channel {$channel}"
"Failed to publish to PostgreSQL channel '{$channel}'"
```
