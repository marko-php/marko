# Task 012: README.md for All Packages

**Status**: completed
**Depends on**: 009, 010, 011, 013
**Retry count**: 0

## Description
Create README.md files for all four new packages following Marko's Package README Standards. Each README must have: Title + One-Liner, Overview, Installation, Usage, Customization (if applicable), and API Reference sections.

## Context
- Follow standards in `.claude/code-standards.md` under "Package README Standards"
- Interface packages describe what they define, note no implementation, show type-hinting
- Implementation packages describe what they do, explain concrete benefit, show it works automatically
- Lead with practical benefit, keep prose minimal, let code speak
- `marko/pubsub` is an interface package
- `marko/amphp` is a foundation package
- `marko/pubsub-redis` and `marko/pubsub-pgsql` are implementation packages

## Requirements (Test Descriptions)
- [ ] `it creates README.md for marko/pubsub with all required sections`
- [ ] `it creates README.md for marko/amphp with all required sections`
- [ ] `it creates README.md for marko/pubsub-redis with all required sections`
- [ ] `it creates README.md for marko/pubsub-pgsql with all required sections`

## Acceptance Criteria
- All four packages have README.md files
- Each follows the Package README Standards format
- Code examples in Usage sections follow Marko code standards
- Interface package README explains contracts
- Driver READMEs explain concrete benefits (Redis: pattern subscriptions; PgSql: zero extra infrastructure)
- amphp README explains its role as async foundation

## Implementation Notes

### README Structure for Each Package

**marko/pubsub (Interface):**
- Title: "Marko PubSub — Real-time publish/subscribe messaging contracts"
- Overview: Defines the pub/sub interfaces. Install a driver to get an implementation.
- Usage: Show type-hinting against PublisherInterface and SubscriberInterface
- API Reference: Interface signatures

**marko/amphp (Foundation):**
- Title: "Marko AMPHP — Async event loop foundation for Marko"
- Overview: Provides Revolt event loop integration for async packages
- Usage: Show `pubsub:listen` command, explain dev:up auto-detection, explain when you need this
- API Reference: EventLoopRunner, AmphpConfig

**marko/pubsub-redis (Driver):**
- Title: "Marko PubSub Redis — Non-blocking Redis pub/sub for Marko"
- Overview: Explain Redis pub/sub with pattern support, non-blocking via amphp
- Usage: Show publish + subscribe, pattern subscribe, SSE integration
- Customization: Extending via Preferences
- API Reference: Public signatures

**marko/pubsub-pgsql (Driver):**
- Title: "Marko PubSub PostgreSQL — Zero-infrastructure pub/sub via LISTEN/NOTIFY"
- Overview: Explain Postgres LISTEN/NOTIFY, no Redis needed, uses existing database
- Usage: Show publish + subscribe, SSE integration
- Customization: Extending via Preferences
- API Reference: Public signatures
- Note: Pattern subscriptions not supported (use Redis driver)
