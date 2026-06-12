# Task 018: Inertia preserves the query string and the version check does not discard flash

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
Two correctness defects in the Inertia integration:

1. **Query string dropped from `url`.** `Inertia::render()` sets `'url' => $request->path()` (line 92), which excludes the query string. Inertia page objects must carry the full URL (path + query) so the client-side router stays in sync; a request to `/users?page=2` is reported to Inertia as `/users`. The 409 location header in `InertiaMiddleware` has the same defect (`'X-Inertia-Location' => $request->path()`, line 62).

2. **Asset-version check runs AFTER the controller, losing flash.** `InertiaMiddleware::handle()` calls `$next($request)` FIRST (line 24), runs the controller, and only THEN performs the asset-version comparison (lines 49-65). On a version mismatch it returns a fresh 409 and discards `$response` — but the controller already ran and consumed any flashed session data, so the forced full reload that follows the 409 has no flash. The version check must happen so that a 409 does not silently drop flash: either perform the check before invoking the controller, or reflash on the 409 path.

## Context
- Related files:
  - `packages/inertia/src/Inertia.php` (`render()` 80-108 — page array at 89-94, `'url' => $request->path()` at 92; Inertia-request JSON branch 96-105; `location()` 113+ builds a 409 with `X-Inertia` Vary)
  - `packages/inertia/src/Middleware/InertiaMiddleware.php` (`handle()` 20-72 — `$next($request)` at 24; redirect 303-upgrade at 33-45; version check at 49-65 with `X-Inertia-Location => $request->path()` at 62; gated to `method() === 'GET'` and both versions non-null at 52-57; `nullableScalarConfig()` 96-113)
  - `packages/routing/src/Http/Request.php` (`path()` strips at `?` at lines 50-56; `query()` returns the PARSED array — there is **NO** raw-query-string / `fullUrl()` accessor today)
- Patterns to follow:
  - **No path-plus-query accessor exists (verified):** the cleanest source of the full URL is `$server['REQUEST_URI']` (which still contains the raw query string exactly as the browser sent it). Prefer reading `REQUEST_URI` and using it verbatim, OR strip-then-reappend the raw query, so the `url` matches the wire bytes. Do NOT rebuild the query by re-encoding the parsed `query()` array — that risks the exact `+`/`%20` encoding mismatch Task 011 (F11) fixes for page-cache.
  - Build the full URL (path + `?` + raw query string when present) once. Apply it to BOTH `Inertia::render()`'s `url` AND the middleware's `X-Inertia-Location`. A request with no query string must produce exactly the current `path()` output (no trailing `?`).
  - Flash preservation: keep routing/inertia decoupled from a specific session API where possible. Preferred — run the version check before `$next()` so a stale-version GET short-circuits to the 409 before the controller runs and consumes flash; if the response must be produced first for header-merging reasons, reflash on the 409 branch. Either way, the test asserts the controller's flash-consuming side effect does not happen (or is preserved) on a 409.
  - Keep the existing `Vary: X-Inertia` and redirect 302→303 upgrade behavior intact.

### Verification note (read at planning time)
Confirmed: `Inertia.php:92` uses `$request->path()` (no query). `InertiaMiddleware` runs `$next` at line 24 before the version check at 49-65; 409 uses `$request->path()` at 62. DRIFT detail to preserve: the version check is gated to `method() === 'GET'` AND both configured/request versions non-null — keep that gating; the fix is about ORDERING (pre-controller vs reflash) and including the query string, not about widening when the check fires.

## Requirements (Test Descriptions)
- [ ] `it includes the query string in the Inertia page url`
- [ ] `it reports only the path when the request has no query string`
- [ ] `it preserves a literal plus or percent-encoded space in the query string unchanged in the page url`
- [ ] `it includes the query string in the 409 X-Inertia-Location header`
- [ ] `it returns a 409 on an asset-version mismatch`
- [ ] `it does not lose flash data on an asset-version mismatch`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
