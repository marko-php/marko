# Task 006: Session Cookie Travels on the Response

**Status**: completed
**Depends on**: 002, 003, 006a
**Retry count**: 0

## Description
Move the session cookie off the SAPI and onto the `Response` object, for both the set path and the clear path. This makes the session cookie visible to middleware, assertable in tests without superglobal fixtures, and — critically — able to reach a client under a SAPI where `setcookie()` is a no-op.

## Context
- Modify: `packages/session/src/Session.php` — `configure()` at line 233, `destroy()` at line 159 (the `setcookie()` at line 174)
- Modify: `packages/session/src/Middleware/SessionMiddleware.php` — currently returns `$response` untouched after calling `save()`; becomes the attach point via `withCookie()`
- Modify: `packages/session/composer.json` — see below
- Reference: `packages/session/src/Config/SessionConfig.php` for lifetime, path, domain, secure, httpOnly, sameSite values
- Tests: `packages/session/tests/Unit/Middleware/SessionMiddlewareTest.php` and the other existing session test locations

**The subtle part — read carefully.** `Session.php:174` is the only `setcookie()` call in the repository, but it only *clears* the cookie in `destroy()`. The cookie is *set* implicitly by `session_start()` through the SAPI. Both paths must move onto the `Response`.

### `session.use_cookies = 0` also disables session *reading* — you must seed the ID yourself

`Session::configure()` currently sets both `session.use_cookies = 1` (line 240) and `session.use_only_cookies = 1` (line 241). `use_cookies` is **not** write-only: it also controls whether PHP reads the session ID from the request cookie. Setting it to `0` while `use_only_cookies` stays `1` leaves `session_start()` with no ID source at all, and **every request silently starts a brand-new empty session** — logins, flash messages and CSRF tokens all stop working. No existing test would catch this, because the current session tests never assert continuity across two requests.

So the middleware must supply the ID explicitly:

1. Read the inbound cookie by `SessionConfig::cookieName()` off the `Request` (task 006a's accessor).
2. If present, `$session->setId($value)` **before** `start()`.
3. `Session::setId()` validates against `^[a-zA-Z0-9-]{32,128}$` and throws `InvalidSessionIdException` on anything else. That value is attacker-controlled — an invalid or tampered cookie must be **ignored** (fall through to a fresh session), never surface as a 500.
4. Then `start()`.

`regenerate()` (which calls `session_regenerate_id()`) updates `$this->id`, and the middleware reads the ID *after* `$next()` — so a mid-request regeneration is picked up correctly.

### Attach the cookie only when it changed — otherwise the page cache dies

`SessionMiddleware` is registered as **global middleware** by the session drivers (`packages/session-file/module.php:19-21`, same in `session-database`), sequenced `'after' => ['marko/page-cache']`. `Router::handle()` builds `[...$globalMiddleware, ...$routeMiddleware]` and `MiddlewarePipeline` peels from the front, so `SessionMiddleware` runs **inside** `PageCacheMiddleware` and its return value is what reaches `CacheabilityChecker::isResponseCacheable()` — which refuses to cache any cookie-bearing response (task 005, and it already rejects a literal `set-cookie` header today at `CacheabilityChecker.php:40`).

If every response carried a session cookie, the page cache would silently stop caching anything on any app with sessions enabled.

Today PHP only emits `Set-Cookie` at `session_start()` when it **creates** or **regenerates** an ID; repeat visitors get nothing. Replicate that exactly:

- Attach the cookie when the outgoing `getId()` differs from the inbound cookie value (new session, or `regenerate()` ran).
- Attach an **expired** cookie when the session was destroyed.
- Attach **nothing** when the ID is unchanged.

This is also what makes the "FPM behavior byte-identical" criterion literally true.

### Detecting a destroyed session without changing `SessionInterface`

`destroy()` sets `$this->id = ''` (line 169) and `started = false` (line 168). Comparing `getId()` before and after `$next()` therefore distinguishes all three cases with **no** addition to `SessionInterface` — which matters, because any interface change breaks both the anonymous-class fake in `SessionMiddlewareTest.php` and `marko/testing`'s `FakeSession` (`packages/testing/src/Fake/FakeSession.php`).

Note that the existing `createFakeSession()` helper returns `''` from `getId()` unconditionally, so it will read as "destroyed" unless updated. Update the fake.

### `destroy()`'s guard becomes dead code

`Session.php:172` wraps the cookie clearing in `if (ini_get('session.use_cookies'))`. Once `configure()` sets that to `'0'`, this branch can never run. Remove the guard **and** the `setcookie()` call together — do not leave a dead `if` behind.

### Ripple effects to handle in this task

- `SessionMiddleware::__construct()` gains `SessionConfig`. All five tests in `packages/session/tests/Unit/Middleware/SessionMiddlewareTest.php` call `new SessionMiddleware($session)` and must be updated.
- `packages/session/composer.json` requires only `marko/core` and `marko/config`, yet `SessionMiddleware` already imports `Marko\Routing\Http\{Request,Response}`. Add `"marko/routing": "self.version"` — the `Cookie` type makes this undeclared dependency untenable.
- Config values must come from `SessionConfig`, never hardcoded. `expireOnClose()` maps to a browser-session cookie with **no** expiry attribute (see task 001), not an epoch timestamp.

### The "exactly one Set-Cookie under FPM" claim is not directly testable

`header()` is a no-op under the CLI SAPI — that is the whole reason task 003 exists. A test can never observe a duplicate emitted by `session_start()` through the SAPI. Assert the two things that *are* observable in-process: the response's `headerLines()` contains exactly one `Set-Cookie` for the configured cookie name, **and** `ini_get('session.use_cookies')` reads `'0'` after `start()`. The ini state is the only in-process proxy for "the SAPI will not emit its own".

### Known behaviour change: the exception path

`SessionMiddleware` calls `save()` in a `finally` and lets the exception propagate — there is no response to decorate, so an error response carries no session cookie. Today the SAPI emitted it at `session_start()`. With the "attach only when changed" rule this only affects a visitor's very first request, but it is a real change and needs a test pinning the behaviour rather than being discovered later.

## Requirements (Test Descriptions)
- [x] `it reuses the session id from the inbound request cookie`
- [x] `it ignores an invalid inbound session cookie and starts a fresh session`
- [x] `it attaches the session cookie to the response when the session is new`
- [x] `it does not attach a session cookie when the id is unchanged`
- [x] `it attaches the new session cookie after the session id is regenerated`
- [x] `it emits exactly one session set-cookie line for the configured cookie name`
- [x] `it disables sapi cookie emission when the session starts`
- [x] `it applies the configured lifetime path and domain to the session cookie`
- [x] `it marks the session cookie httponly and applies the configured samesite value`
- [x] `it attaches an expired cookie to the response when the session is destroyed`
- [x] `it does not call setcookie directly`
- [x] `it still saves the session when the handler throws and attaches no cookie`

## Acceptance Criteria
- All requirements have passing tests
- All pre-existing session tests still pass (five `new SessionMiddleware($session)` call sites updated for the new constructor)
- `marko/testing`'s `FakeSession` still satisfies `SessionInterface` — no interface changes
- No `setcookie()` call and no dead `ini_get('session.use_cookies')` guard remains in `packages/session/`
- `packages/session/composer.json` declares `marko/routing`
- Code follows code standards

## Implementation Notes

- `Session::configure()` now sets `session.use_cookies` to `'0'` and no longer calls `session_set_cookie_params()` (that call started emitting an E_WARNING once `use_cookies` is disabled, and is unnecessary now that `SessionMiddleware` owns every cookie attribute). `session.use_only_cookies` is left at `'1'` — harmless now that the ID is always seeded explicitly via `session_id()` before `session_start()`.
- `Session::destroy()` no longer clears a cookie itself; the dead `ini_get('session.use_cookies')` guard and the `setcookie()` call were removed together. Cookie expiry on logout is now `SessionMiddleware`'s job (an expired `Cookie` is attached when `getId()` comes back `''` after `$next()`).
- `SessionMiddleware` gained a `SessionConfig` constructor dependency and now: (1) reads the inbound cookie by `SessionConfig::cookieName()` and seeds `Session::setId()` before `start()`, silently ignoring `InvalidSessionIdException` (attacker-controlled cookie value — commented as a deliberate catch, never a 500); (2) compares the outgoing `getId()` after `$next()`/`save()` against the inbound cookie value to decide whether to attach a fresh cookie (new or regenerated ID), an expired cookie (`getId() === ''`, i.e. destroyed), or nothing (unchanged ID) — this is what keeps the page-cache tripwire test green.
- `packages/session/composer.json` now requires `marko/routing` (already an undeclared transitive dependency via `Request`/`Response`).
- Given the tight coupling between the id-seeding, cookie-attach, and cookie-attribute logic (all live in `SessionMiddleware::handle()` and its private helpers), the RED/GREEN cycle was done at the level of "add one test, confirm it exercises a real assertion against the already-converged implementation" rather than fully isolating each of the 12 requirements into its own minimal-code increment — most of the middleware logic had to exist before any single cookie-lifecycle test could pass. Each test was verified individually passing and the `--filter` RED check was used for the two Session-level tests (`it disables sapi cookie emission...`, structural `it does not call setcookie directly`) where the underlying production change was still separable.
- Five `new SessionMiddleware($session)` call sites in `SessionMiddlewareTest.php` were updated to `new SessionMiddleware($session, createMiddlewareSessionConfig())`. Two additional call sites outside the enumerated five also needed updating to keep the suite compiling: `packages/session-file/tests/ModuleTest.php` (real end-to-end test) and `tests/Integration/PageCacheSessionMiddlewareTest.php` (the page-cache tripwire test), both now construct a real `SessionConfig` and pass matching inbound-cookie request state.
- The local `createFakeSession()` helper in `SessionMiddlewareTest.php` was rewritten to track `setId()`/`getId()` state (with an injectable `onSetId` callback and a `rejectSetId` flag to simulate `InvalidSessionIdException`), rather than returning `''` unconditionally from `getId()`, per the task's callout. `marko/testing`'s `FakeSession` was left untouched — it is never combined with `SessionMiddleware` anywhere in the current codebase (confirmed via repo-wide grep), so updating its `destroy()`/`getId()` semantics was out of scope.
- Verification: `packages/session`, `packages/session-file`, `packages/session-database`, and `tests/Integration/PageCacheSessionMiddlewareTest.php` all pass (`--parallel`), including the page-cache tripwire test proving repeat visits stay cacheable. `composer phpstan` (scoped to `packages/core/src` per `phpstan.neon`) is unaffected and passes with 0 errors. `php-cs-fixer` and `phpcs` report no issues on any touched file. A pre-existing, out-of-scope failure in `tests/MiddlewareDecorationTest.php` (rebuild-after-`$next()` anti-pattern in `authentication`, `cors`, and `security` packages' middleware) was observed in the full-suite run; it predates this task, does not involve any file this task touches, and was confirmed (via `git status`) to be uncommitted work from other in-flight tasks in this same plan.
