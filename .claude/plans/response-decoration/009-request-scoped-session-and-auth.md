# Task 009: Make Session and Auth Guard State Request-Scoped

**Status**: complete
**Depends on**: 006, 008
**Retry count**: 0

## Description
Give `Marko\Session\Session` and `Marko\Authentication\Guard\SessionGuard` a way to drop their per-request state, and make both implement `ResettableInterface`. Both are container **singletons** that cache the current user's identity in instance properties, and neither can be cleared through its public interface. In any booted-once process this is a cross-user data leak.

Both leaks below are **verified from source**, not hypotheses.

## Context

### Leak 1 — `Session` reuses the previous request's session ID

`packages/session-file/module.php:16-18` (and `session-database`) bind `SessionInterface` as a **singleton** to `Marko\Session\Session`.

- `Session::save()` (line 222) sets `started = false` but leaves `$this->id` and `$this->data` populated.
- `Session::start()` (line 52) does `if ($this->id !== '') { session_id($this->id); }`.
- Task 006's `SessionMiddleware` seeds the ID from the inbound request cookie — **but only when a cookie is present**.

So request N is an authenticated user with a session cookie; request N+1 arrives from an anonymous visitor with **no** cookie; nothing calls `setId()`; `$this->id` still holds request N's ID; `session_id()` loads request N's session. The anonymous visitor is now logged in as the previous user.

`SessionInterface` offers no escape: `setId('')` throws `InvalidSessionIdException` because `validateId()` requires `^[a-zA-Z0-9-]{32,128}$` (line 271).

**Fix**: clear `$this->id`, `$this->data` and `$this->flashBag` in `Session::save()` after `session_write_close()`. Under PHP-FPM the process dies immediately afterwards, so this is a behavioural no-op there — prove that by running the existing `packages/session/tests/` suite unchanged.

### Leak 2 — `SessionGuard` caches the resolved user forever

`packages/authentication/module.php:25-28` marks `AuthManager` and `GuardInterface` as **singletons**, and `AuthManager::$guards` (line 18) memoizes guard instances.

`SessionGuard::$cachedUser` (`packages/authentication/src/Guard/SessionGuard.php:24`) is set in `user()` (lines 60-75) and never cleared except by `logout()` — which is destructive and cannot serve as a reset. So request N+1 gets request N's `AuthenticatableInterface` back without ever touching the session.

**Fix**: give the guard a non-destructive way to forget the cached user.

### Leak 3 — shutdown functions accumulate per request

`Session::configure()` (line 254) is called from `start()` on **every** request, and `session_set_save_handler($handler, true)`'s second argument makes PHP `register_shutdown_function()` a `session_write_close` callback each time. In a long-running process that grows unbounded and fires N times at exit. Confirm the behaviour, and if confirmed register the handler once rather than per request.

### Constraints

- **Do NOT add reset methods to `SessionInterface` or `GuardInterface`.** `marko/testing`'s `FakeSession`, `FakeUserProvider` and `FakeAuthenticatable`, plus the anonymous-class fakes in `SessionMiddlewareTest`, all implement against those contracts and must still satisfy them with **no edits**. Implement `ResettableInterface` (task 008) on the concrete classes instead — that is precisely why the contract is separate from the domain interfaces.
- Every pre-existing test in `packages/session/`, `packages/session-file/`, `packages/session-database/`, `packages/authentication/` and `packages/testing/` must pass untouched. If one needs changing, that is a signal the change is not FPM-neutral.
- **Prove each leak with a failing test before fixing it.** Simulate two sequential requests against a single `Session` / `SessionGuard` instance — no worker or RoadRunner binary is needed to demonstrate this. A test that only asserts a new method exists proves nothing.

## Requirements (Test Descriptions)
- [x] `it clears the session id after saving` → renamed to `it clears the session id when reset` (clearing happens in `Session::reset()`, not `save()` — see Implementation Notes)
- [x] `it clears the session data after saving` → renamed to `it clears the session data when reset` (same rationale as above)
- [x] `it starts a fresh session when a second request arrives with no cookie`
- [x] `it resumes the same session when a second request arrives with the same cookie`
- [x] `it forgets the cached user without destroying the session`
- [x] `it does not return the previous requests user to an anonymous request`
- [x] `it registers the session save handler shutdown function only once`

## Acceptance Criteria
- All requirements have passing tests
- Each leak reproduced by a failing test first, then fixed
- `SessionInterface` and `GuardInterface` are byte-identical to before
- `Session` and `SessionGuard` implement `ResettableInterface`
- All pre-existing session, authentication and testing package tests pass unmodified
- Code follows code standards

## Implementation Notes

- **Renamed two requirement names to match correct behaviour**, per the task file's own dependency-context note: `it clears the session id after saving` → `it clears the session id when reset`, and `it clears the session data after saving` → `it clears the session data when reset`. The clearing lives in `Session::reset()` (a new `ResettableInterface` method), not in `save()` — `save()` still only flips `started = false`. This preserves `SessionMiddleware::attachSessionCookie()`'s `getId()` read after `save()`, keeping the page-cache tripwire (`tests/Integration/PageCacheSessionMiddlewareTest.php`) green.

- **A second, deeper root cause was discovered and fixed for Leak 1**, beyond what the task file anticipated. Clearing `Session::$id` alone (the fix implied by the task text) is *not* sufficient to stop the second visitor of a long-running process from resuming the first visitor's session. PHP's session extension retains the last-used session id in **process memory** (not exposed via any `$this` property) across `session_write_close()` calls. `Session::start()` previously only called `session_id($this->id)` when `$this->id !== ''`, so on the id-less path it silently fell through to PHP's internal state and resumed the previous request's id. Verified directly with a standalone PHP script before touching test code (see conversation — `session_id('')` explicitly forces a fresh id on the next `session_start()`, confirmed by comparing ids across two `session_start()`/`session_write_close()` cycles in the same process). Fixed by calling `session_id($this->id)` unconditionally in `start()`, so an empty `$this->id` explicitly clears PHP's internal state instead of leaving it untouched. This is the change that actually makes `it starts a fresh session when a second request arrives with no cookie` pass — the property-level `reset()` clearing from the first two requirements was necessary but not sufficient.

- **Requirements 3, 4 and 6 passed immediately** once the preceding requirement's fix landed (integration-level tests confirming behaviour already delivered by the lower-level fix, not over-implementation introduced ahead of a test). Noted per the TDD workflow's guidance for this case.

- **Test isolation bug found and fixed during requirement 4**: the first drafts of the two-sequential-request `Session` tests (`starts a fresh session...` / `resumes the same session...`) called `$session->start()` a second time without a closing `$session->save()`, leaving PHP's real session in the `PHP_SESSION_ACTIVE` state for the rest of that worker process under `--parallel`. This intermittently broke unrelated tests in the same file with `SessionException: A session is already active`. Fixed by wrapping the assertions in `try { ... } finally { $session->save(); }`, matching the existing `disables sapi cookie emission when the session starts` test's pattern. Verified stable across 5 repeated `--parallel` runs after the fix.

- **`SessionGuard::reset()`** clears only `$cachedUser`; it never touches the session (non-destructive, per `ResettableInterface`'s contract), verified by `it forgets the cached user without destroying the session` using a spy `UserProviderInterface` that returns a fresh instance per call so the test can prove a re-fetch happened rather than returning the same cached object.

- **`AuthManager` was deliberately left unchanged.** The task's Leak 2 section says "Consider whether `AuthManager` also needs to participate, given it memoizes guard instances" — but the Acceptance Criteria only require `Session` and `SessionGuard` to implement `ResettableInterface`, and there is no requirement/test asking for `AuthManager` participation. Left out of scope to avoid unrequested surface area; a long-running worker wiring resets together would need to resolve guard instances (e.g. via `AuthManager::guard()`) to reset them, which is a separate, unspecified concern.

- **Leak 3 (`it registers the session save handler shutdown function only once`)** is proven with a dedicated test file, `packages/session/tests/Unit/SessionShutdownHandlerTest.php`, that defines `Marko\Session\session_set_save_handler()` as a counting wrapper forwarding to the real built-in. PHP resolves the unqualified call inside `Session::configure()` against the `Marko\Session` namespace first, so this intercepts the call without any production-code changes for testability. The fix is a `private bool $handlerRegistered` flag on `Session`, checked in `configure()` — deliberately **not** cleared by `reset()`, since handler registration is per-process state, not per-request state. This test file is self-contained (own inline `SessionConfig`/handler) rather than reusing `SessionTest.php`'s global helper functions, because under `--parallel` paratest can schedule this file in a worker process that never loaded `SessionTest.php`, so those global functions would be undefined — discovered as a real failure during the RED→GREEN cycle and fixed by inlining.

- Full suite: `6972 passed` (`0 failed`) via `composer test` equivalent (`pest -c phpunit.xml --parallel --exclude-group=integration-destructive`), up from the pre-existing 6965 baseline (+7 new tests, matching the 7 requirements). `composer phpstan` reports `No errors` — its `phpstan.neon` scopes analysis to `packages/core/src` only, so it is unaffected by this task's `packages/session` / `packages/authentication` changes. All three named tripwires (`tests/Integration/PageCacheSessionMiddlewareTest.php`, `tests/MiddlewareDecorationTest.php`, `packages/session/tests/PackageStructureTest.php`) pass unmodified.
