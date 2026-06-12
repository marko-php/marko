# Task 008: Routing Request Content-Type header (CGI key fallback)

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`Request::header()` maps a header name only to the `HTTP_*` server key, so `header('Content-Type')` (and `Content-Length`) misses the CGI/SAPI variants `CONTENT_TYPE`/`CONTENT_LENGTH`, which PHP stores WITHOUT the `HTTP_` prefix. Fix `header()` to fall back to the un-prefixed CGI key for those two headers only.

> SCOPE CORRECTION (devil's-advocate): the original task ALSO added `rawurldecode()` to `Request::path()`. **That is now REMOVED.** The plan's "tier3-medium-fixes/ is empty / no prior task touches `path()`" note is STALE — Tier 3 (merged) added per-parameter percent-decoding in `RouteMatcher::extractParameters()` (`packages/routing/src/RouteMatcher.php` line 64: `rawurldecode($matches[$name])`) and pinned it with a test (`packages/routing/tests/RouteRegexEscapingTest.php` → `it('rawurldecodes a matched parameter value exactly once', ...)`). The dispatch flow `Router::match($request->method(), $request->path())` (`Router.php` line 41) feeds `path()` straight into the matcher. If `path()` decoded the URI, route parameters would be **decoded twice** (corrupting values like `hello%2520world`) and an encoded `%2F` would become a literal `/` and change the path structure before matching. Decoding is already correctly handled per-parameter by the matcher. **Do NOT add `rawurldecode` to `path()`.** This task is now Content-Type/Content-Length `header()` only.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/routing/src/Http/Request.php` (`header()` lines 128-135 — currently `$serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name)); return $this->server[$serverKey] ?? $default;`)
  - Tests: `/Users/markshust/Sites/marko/packages/routing/tests/` (Request tests)
- Patterns to follow:
  - `Request` is a `readonly class` constructed from a `$server` array; tests construct it directly with a server array (no globals needed).
  - For `header()`: compute the normalized name `strtoupper(str_replace('-', '_', $name))` and the `HTTP_<NAME>` key as today; if the `HTTP_` key is absent, AND the normalized name is exactly `CONTENT_TYPE` or `CONTENT_LENGTH`, also check the un-prefixed key (`$this->server['CONTENT_TYPE']` / `['CONTENT_LENGTH']`). The CGI fallback applies ONLY to those two names — a generic header named `X-Foo` must NOT silently read an un-prefixed `X_FOO` key. Keep the `?string` return and the `$default` parameter behavior: `$default` is returned only when NEITHER the `HTTP_` key NOR (for the two CGI headers) the un-prefixed key is present.
  - Cast the resolved value to string on return only if needed to satisfy the `?string` contract (server is `array<string, mixed>`); the existing code returns `$this->server[$serverKey] ?? $default` directly — keep that shape, just add the CGI-key fallback branch.
  - Do NOT touch `path()` (see SCOPE CORRECTION above) — leave it exactly as-is.
  - `headers()` (the bulk accessor) is out of scope; do not change its contract.

## Requirements (Test Descriptions)
- [ ] `it reads Content-Type from the CGI CONTENT_TYPE server key when HTTP_CONTENT_TYPE is absent`
- [ ] `it still reads Content-Type from HTTP_CONTENT_TYPE when present`
- [ ] `it reads Content-Length from the CGI CONTENT_LENGTH server key`
- [ ] `it does not read an un-prefixed key for a non-CGI header name`
- [ ] `it returns the default when neither header form is present`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
