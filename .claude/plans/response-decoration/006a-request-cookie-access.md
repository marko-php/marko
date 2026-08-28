# Task 006a: Request Cookie Access

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Give `Marko\Routing\Http\Request` first-class access to inbound cookies. Task 006 needs to read the session cookie off the request in order to seed the session ID; today there is no way to do that without reading `$_COOKIE` directly from middleware, which contradicts the plan's own superglobal stance and the worker-mode direction of issue #151.

This task has no dependencies and can run in parallel with task 001.

## Context
- Modify: `packages/routing/src/Http/Request.php`
- Tests: `packages/routing/tests/Http/RequestTest.php`

**Verified gap.** `Request` has no `cookie()` accessor, and `Request::fromGlobals()` (line 23) captures `$_SERVER`, `$_GET`, `$_POST` and `php://input` but never `$_COOKIE`. Both halves need adding.

- Follow the shape of the existing `query()` / `post()` accessors exactly: an optional `?string $key` returning the whole `array<string, string>` when null, plus a `mixed $default`. Keep the same `@return ($key is null ? ... : ...)` conditional-type docblock style.
- `Request` is a `readonly class` and stays one — it is not part of the `Response` decoration change and nothing clones it.
- Cookies become a new constructor parameter. It **must be added last or as a named-only addition with a default**, because `Request::withRoute()` (line 104) reconstructs via `new self(...)` and `fromGlobals()` uses named arguments. Every existing `new Request(server: [...])` call across the test suites must keep working untouched — there are many.
- `withRoute()` must carry cookies through to the new instance. Missing this is a silent data-loss bug that only shows up after routing has matched, which is exactly when `SessionMiddleware` runs.

## Requirements (Test Descriptions)
- [x] `it returns all cookies when no key is given`
- [x] `it returns a single cookie value by name`
- [x] `it returns the default when the cookie is absent`
- [x] `it defaults to an empty cookie collection`
- [x] `it captures cookies from globals`
- [x] `it preserves cookies through withRoute`

## Acceptance Criteria
- All requirements have passing tests
- Every pre-existing `new Request(...)` call site across all packages still compiles and passes unchanged
- `Request` remains a `readonly class`
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Added `private array $cookies = []` as the last constructor parameter (named, defaulted) so every pre-existing `new Request(...)` call site keeps working unchanged.
- Added `cookie(?string $key = null, mixed $default = null): mixed` following the exact shape of `query()`/`post()`, including the `@return ($key is null ? array<string, string> : mixed)` conditional docblock.
- `fromGlobals()` now passes `cookies: $_COOKIE`.
- `withRoute()` now forwards `cookies: $this->cookies` to the reconstructed instance.
- Requirements "returns a single cookie value by name", "returns the default when the cookie is absent", and "defaults to an empty cookie collection" passed immediately after the requirement-1 GREEN step, since the `cookie()` accessor (built to mirror `query()`/`post()` per the task's explicit instruction) already covered key lookup, default fallback, and the empty-array default in one pass. No extra code was written for these three; noting per TDD process rather than backfilling artificial failing tests.
- `Request` remains a `readonly class`; no clones anywhere.
- Verified with `phpstan analyse packages/routing/src/Http/Request.php` — no errors — and `php-cs-fixer fix` on both touched files — no changes needed.
- Full `packages/routing/tests/` run shows one pre-existing failure in `CookieTest.php` (untracked `Cookie.php`/`CookieException.php`/`CookieTest.php`, belonging to a different, unrelated task in this plan) — out of scope for 006a and not touched by this change.
