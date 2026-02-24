# Plan: Refactor All Packages to Use marko/testing Fakes

## Created
2026-02-24

## Status
completed

## Objective
Add FakeGuard to marko/testing, then refactor all packages across the framework to replace hand-rolled test stubs with marko/testing fakes — eliminating ~2,000+ lines of duplicated boilerplate across 30+ test files in 20+ packages.

## Scope

### In Scope
- Add FakeGuard (new) to marko/testing with assertion methods and Pest expectations
- Refactor 20+ packages to use marko/testing fakes (FakeGuard, FakeSession, FakeMailer, FakeEventDispatcher, FakeCookieJar, FakeConfigRepository, FakeAuthenticatable, FakeUserProvider, FakeQueue)
- Add `marko/testing` to `require-dev` for each refactored package
- Delete all replaced mock files, named stub classes, and inline anonymous class stubs
- Update marko/testing README to document FakeGuard

### Out of Scope
- Blog domain-specific mocks (repositories, BlogConfig, TokenRepository, blog CookieJarInterface)
- Queue WorkerTest QueueInterface stubs (intentionally custom per test — track specific behaviors like pop counts, exception throwing, backoff delays)
- Creating new fakes beyond FakeGuard (no FakeView, FakeRateLimiter, etc.)

## Success Criteria
- [ ] FakeGuard implements full GuardInterface with assertion methods
- [ ] All 9 GuardInterface stubs across 5 packages eliminated
- [ ] All ~23 EventDispatcherInterface stubs across blog + admin-auth eliminated
- [ ] All ~25 ConfigRepositoryInterface stubs across 20+ packages eliminated
- [ ] All replaced stubs/mocks are deleted
- [ ] All package test suites pass
- [ ] Full test suite passes (`./vendor/bin/pest --parallel`)
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Add FakeGuard to marko/testing | - | pending |
| 002 | Refactor security tests (Session + Config) | - | pending |
| 003 | Refactor rate-limiting tests (Config) | - | pending |
| 004 | Refactor blog event tests (EventDispatcher + Mailer) | - | pending |
| 005 | Refactor blog controller/service/command tests | - | pending |
| 006 | Refactor blog admin tests (Guard + EventDispatcher + Config) | 001 | pending |
| 007 | Refactor authorization tests (Guard × 3 + Config) | 001 | pending |
| 008 | Refactor admin-api tests (Guard × 2 + Config) | 001 | pending |
| 009 | Refactor admin-auth tests (Guard + EventDispatcher + Config) | 001 | pending |
| 010 | Refactor admin-panel tests (Guard × 2 + Authenticatable × 2 + Config) | 001 | pending |
| 011 | Refactor authentication tests (Authenticatable + UserProvider) | - | pending |
| 012 | Refactor queue tests (Config stubs) | - | pending |
| 013 | Refactor core tests (QueueInterface stubs) | - | pending |
| 014 | Config refactor: cache packages | - | pending |
| 015 | Config refactor: mail, log, view packages | - | pending |
| 016 | Config refactor: session-file, encryption-openssl, filesystem | - | pending |
| 017 | Config refactor: hashing, pagination, translation, admin, queue-sync | - | pending |
| 018 | Update marko/testing README | 001 | pending |

## Architecture Notes

### FakeGuard Design
Superset of all 9 existing stub implementations:
- Constructor accepts `name` parameter (replaces hardcoded guard names)
- Records all `attempt()` calls with credentials for assertion
- Tracks `logoutCalled` state
- Configurable `attemptResult` for controlling `attempt()` return value
- Assertion methods: `assertAuthenticated()`, `assertGuest()`, `assertAttempted()`, `assertNotAttempted()`, `assertLoggedOut()`
- Pest expectations: `toHaveAttempted()`, `toBeAuthenticated()`

### What Gets Replaced vs What Stays

**Replace:**
| Fake | Stubs Eliminated | Packages |
|---|---|---|
| FakeGuard (new) | 9 named stubs (~576 lines) | authorization, admin-api, admin-auth, admin-panel, blog |
| FakeEventDispatcher | ~23 inline/named stubs (~300 lines) | blog (18), admin-auth (1), + 4 already in plan |
| FakeConfigRepository | ~25 inline stubs (~1,500 lines) | 20+ packages |
| FakeSession | 4 stubs (~250 lines) | blog, security |
| FakeMailer | 2 stubs (~40 lines) | blog |
| FakeAuthenticatable | 7 stubs (~150 lines) | admin-panel, authentication |
| FakeUserProvider | 6+ stubs (~100 lines) | authentication |
| FakeQueue | 3 stubs (~60 lines) | core |

**Keep (domain-specific or intentionally custom):**
- Blog: MockPostRepository, MockCategoryRepository, MockCommentRepository, MockAuthorRepository, MockBlogConfig, MockTokenRepository, MockCookieJar (blog's CookieJarInterface ≠ authentication's)
- Queue: WorkerTest QueueInterface stubs (custom per-test behaviors)
- Rate-limiting: RateLimiterInterface stubs (domain-specific)

### Dependency Graph
```
001 (FakeGuard) ─┬─► 006 (blog admin)
                 ├─► 007 (authorization)
                 ├─► 008 (admin-api)
                 ├─► 009 (admin-auth)
                 ├─► 010 (admin-panel)
                 └─► 018 (README)

002 (security) ──────────► independent
003 (rate-limiting) ──────► independent
004 (blog events) ────────► independent
005 (blog controllers) ──► independent
011 (authentication) ─────► independent
012 (queue) ──────────────► independent
013 (core) ───────────────► independent
014 (cache config) ───────► independent
015 (mail/log/view config) ► independent
016 (session/enc/fs config) ► independent
017 (hash/page/trans/admin) ► independent
```

**Batch 1 (parallel):** 001, 002, 003, 004, 005, 011, 012, 013, 014, 015, 016, 017 (12 tasks)
**Batch 2 (parallel):** 006, 007, 008, 009, 010, 018 (6 tasks)

## Risks & Mitigations
- Blog CookieJarInterface differs from authentication CookieJarInterface: Mitigation — MockCookieJar stays, clearly documented
- Queue WorkerTest stubs are highly customized: Mitigation — only replacing ConfigRepository stubs in queue, leaving QueueInterface stubs
- Adding marko/testing to core's require-dev adds 7 transitive deps: Mitigation — dev-only, acceptable trade-off for consistency
- ConfigRepository stubs may use different key resolution (nested vs flat): Mitigation — FakeConfigRepository supports flat dot-notation which is the standard pattern
