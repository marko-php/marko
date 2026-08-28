# Task 008: ResettableInterface in Core

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Introduce a `ResettableInterface` contract in `marko/core` that a service implements to declare it holds request-scoped state which must be cleared between requests. This is the contract a long-running worker calls; without it, any package that adds request-scoped singleton state breaks worker mode with no signal.

## Context
This interface is being introduced now because the set of services needing it is **known**, not guessed: `Session`, `SessionGuard`, and `ReadWriteConnection` (which already ships `resetStickyState()` for exactly this purpose). Introducing it before that set was known would have been speculative; it no longer is.

- New file in `packages/core/src/Contracts/` (or alongside existing core contracts — match where core keeps its interfaces).
- Purely additive: no existing class changes in this task, and no BC break.
- Keep the contract minimal — a single method to clear per-request state. Do NOT model it on `__destruct` or `logout()` semantics: reset must be **non-destructive**, meaning it clears in-memory per-request state without destroying persisted data. A `Session` reset must not destroy the stored session; it must forget which session this instance was serving.
- Document that reset runs between requests in a long-running process and is a no-op under PHP-FPM, where the process ends instead.
- The tasks that implement it are 009 (Session, SessionGuard) and 010 (ReadWriteConnection).

## Requirements (Test Descriptions)
- [x] `it defines a contract for clearing request scoped state`
- [x] `it can be implemented by a class that clears its per request state`
- [x] `it documents that reset is non destructive`

## Acceptance Criteria
- All requirements have passing tests
- Purely additive — no existing class or interface is modified
- Code follows code standards

## Implementation Notes
- Added `Marko\Core\Contracts\ResettableInterface` at `packages/core/src/Contracts/ResettableInterface.php`, matching the `src/Contracts/` convention used by every other package in the repo (core had no interfaces of this kind yet, so no existing directory to match).
- Single method `reset(): void`. Docblock documents: (1) a long-running worker calls `reset()` between requests, (2) under PHP-FPM the process ends after each request instead, so `reset()` is effectively a no-op there, (3) `reset()` must be non-destructive — it clears in-memory per-request state without destroying persisted data (e.g. a `Session` reset forgets which session the instance was serving, it does not delete the stored session).
- Tests at `packages/core/tests/Unit/Contracts/ResettableInterfaceTest.php` (3 tests, reflection-based, following the `PluginInterceptedInterfaceTest` pattern already used in core).
- Requirements 2 and 3 passed on first run once requirement 1's interface was written — the interface is small enough that one implementation satisfied all three requirements' assertions; no over-implementation occurred, each test still asserts a distinct part of the contract (structure, implementability, documentation content).
- Purely additive: `git status` confirms only new files were added under `packages/core/src/Contracts/` and `packages/core/tests/Unit/Contracts/`; no existing class or interface was modified.
- Verified: `composer phpstan` (scoped to `packages/core/src`) — 0 errors. `phpcs` and `php-cs-fixer` — clean, no changes needed. Full `packages/core/tests/` suite — 562 passed.
