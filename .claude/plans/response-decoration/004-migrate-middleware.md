# Task 004: Migrate Rebuild-Pattern Middleware to Decoration

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Replace the response-rebuilding pattern in all five middleware with decoration calls, so a response passing through them keeps its concrete subclass. This is where the live SSE bug from issue #150 actually stops reproducing.

## Context
Five files, **six** rebuild sites. Every one of these builds a brand-new base `Response` from the previous response's getters, discarding subclass identity and any subclass state:

| File | Line | What it rebuilds | Decoration replacement |
|---|---|---|---|
| `packages/security/src/Middleware/SecurityHeadersMiddleware.php` | 27 | headers merge | `withHeaders()` |
| `packages/security/src/Middleware/CorsMiddleware.php` | 44 | headers merge | `withHeader()` |
| `packages/cors/src/Middleware/CorsMiddleware.php` | 67 | headers merge | `withHeaders()` |
| `packages/ratelimiter/src/Middleware/RateLimitMiddleware.php` | 51 | headers merge | `withHeaders()` |
| `packages/inertia/src/Middleware/InertiaMiddleware.php` | 55 | headers merge **+ status 302 → 303** | `withHeaders()` then `withStatus()` |
| `packages/inertia/src/Middleware/InertiaMiddleware.php` | 64 | headers merge | `withHeaders()` |

**`InertiaMiddleware` has two rebuild sites, not one.** Line 55 is the redirect branch and it also changes the status code (302 → 303 for PUT/PATCH/DELETE) — `withHeader()` alone cannot express it, which is why task 002 provides `withStatus()`. Line 64 is the normal branch.

**`InertiaMiddleware.php:28` must be left alone.** That is a genuinely fresh 409 version-mismatch response constructed before `$next()` is ever called — it is not a rebuild. The same goes for the fresh preflight/limit responses at `cors/CorsMiddleware.php:48`, `security/CorsMiddleware.php:34` and `ratelimiter/RateLimitMiddleware.php:36`. Task 007's guard test is written to permit exactly these.

- Depends on task 002 for `withHeader()`, `withHeaders()` and `withStatus()`. Do not start until those exist.
- The regression fixture MUST be a `StreamingResponse` (or an equivalent `Response` subclass carrying extra state), not a base `Response` — a base-class test would pass even with the bug present.
- Header values applied by each middleware must be unchanged; this is a mechanism change, not a behavior change. Existing middleware tests must stay green.
- Note that `cors/CorsMiddleware` and `security/CorsMiddleware` are two separate implementations in two packages. Migrate both.

## Requirements (Test Descriptions)
- [x] `it preserves the response subclass through security headers middleware`
- [x] `it preserves the response subclass through the security package cors middleware`
- [x] `it preserves the response subclass through the cors package middleware`
- [x] `it preserves the response subclass through rate limit middleware`
- [x] `it preserves the response subclass through inertia middleware`
- [x] `it preserves the response subclass through the inertia redirect branch while upgrading 302 to 303`
- [x] `it preserves the streaming payload when a streaming response passes through security headers middleware`
- [x] `it still applies the same header values after migrating to decoration`

## Acceptance Criteria
- All requirements have passing tests
- All pre-existing tests for these five middleware still pass unchanged
- No `new Response(` remains **after the `$next()` call** in any of the five middleware; the four pre-`$next()` fresh responses stay as they are
- Code follows code standards

## Implementation Notes

- Migrated all six rebuild sites to decoration:
  - `security/SecurityHeadersMiddleware.php`: `new Response(...)` → `$response->withHeaders($this->buildSecurityHeaders())`.
  - `security/CorsMiddleware.php`: `new Response(...)` → `$response->withHeader('Access-Control-Allow-Origin', $origin)`.
  - `cors/CorsMiddleware.php`: `new Response(...)` → `$response->withHeaders($corsHeaders)` (removed the now-dead intermediate `$headers` variable).
  - `ratelimiter/RateLimitMiddleware.php`: `new Response(...)` → `$response->withHeaders([...])`.
  - `inertia/InertiaMiddleware.php` redirect branch (line 55): `new Response(...)` → `$response->withHeaders($headers)->withStatus($statusCode)`.
  - `inertia/InertiaMiddleware.php` normal branch (line 64): `new Response(...)` → `$response->withHeaders($headers)`.
  - The four pre-`$next()` fresh responses (409 version-mismatch, both preflight 204s, 429 rate-limited) were left untouched, per the task's explicit carve-out.
- Regression fixtures: added a per-package `tests/Helpers.php` (global-namespace-adjacent, namespaced to `Marko\{Package}\Tests`, loaded via root `composer.json` `autoload-dev.files`) in `security`, `cors`, `ratelimiter`, and `inertia`, each defining a `TaggedResponse extends Response` carrying an extra `tag` property plus a `createTaggedResponse()` factory. `security`'s Helpers.php additionally defines `StreamingLikeResponse` (carries a `chunks` list) for the streaming-payload requirement, avoiding a test-only cross-package dependency on `marko/sse` (per the task's explicit guidance).
- Confirmed RED authentically: for the security-headers middleware, verified via `git stash` on the production file alone that both `preserves the response subclass...` and `preserves the streaming payload...` tests genuinely fail against the pre-migration rebuild code (fails with `Failed asserting that an instance of class Marko\Routing\Http\Response is an instance of class ...`), then restored and re-verified GREEN. The other four middleware's subclass-preservation tests were confirmed RED via `--filter --bail` before their respective migrations.
- `it still applies the same header values after migrating to decoration` and the streaming-payload test were authored alongside the security-headers subclass test (same file, same edit) and consequently already passed once that single production fix landed — this is expected since both exercise the same `withHeaders()` call, not over-implementation of a separate requirement.
- Ran `composer phpstan` (0 errors) and the full suite (`vendor/bin/pest -c phpunit.xml --parallel --exclude-group=integration-destructive`): 6946 passed, 0 failed (pre-existing risky/notice/skipped counts unrelated to this change, e.g. a curl-extension-availability test).
- `phpcs`/`php-cs-fixer` run on all touched files; manually tidied import ordering in the four migrated test files after `php-cs-fixer` split `use function` imports into awkward single-line blocks.
