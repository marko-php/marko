# Task 008: Routing Request Content-Type header + path decode

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`Request::header()` maps a header name only to the `HTTP_*` server key, so `header('Content-Type')` (and `Content-Length`) misses the CGI/SAPI variants `CONTENT_TYPE`/`CONTENT_LENGTH`, which PHP stores WITHOUT the `HTTP_` prefix. Separately, `path()` returns the raw URI without `rawurldecode`, so percent-encoded path segments are not decoded. Fix `header()` to fall back to the un-prefixed CGI key for Content-Type/Content-Length, and `path()` to decode the path.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/routing/src/Http/Request.php` (`path()` ~48-54; `header()` ~89-96)
  - Tests: `/Users/markshust/Sites/marko/packages/routing/tests/` (Request tests)
- Patterns to follow:
  - `Request` is a `readonly class` constructed from a `$server` array; tests construct it directly with a server array (no globals needed).
  - For `header()`: compute `HTTP_<NAME>` as today; if absent, for the two CGI-special headers (`CONTENT_TYPE`, `CONTENT_LENGTH`) also check the un-prefixed key. The CGI fallback should only apply to those two names (compare the normalized name, e.g. `CONTENT_TYPE`/`CONTENT_LENGTH`), not arbitrary headers — a generic header named `X-Foo` must NOT silently read a `X_FOO` un-prefixed key. Keep the `?string` return and the `$default` parameter behavior: the `$default` is returned only when NEITHER the `HTTP_` key NOR the CGI key is present.
  - For `path()`: `$this->server['REQUEST_URI']` is typed `mixed` (server is `array<string, mixed>`); cast to string before string ops so `rawurldecode()`/`strpos()` receive a string and never raise a `TypeError`. After stripping the query string, apply `rawurldecode(...)` to the path before returning. Preserve the `?`-stripping logic and the `/` default (when `REQUEST_URI` is absent, return `/` — do not run `rawurldecode` on a missing value).
  - No Tier 3 collision: `tier3-medium-fixes/` is empty; this task owns `path()` outright.
  - `headers()` (the bulk accessor) is out of scope unless trivially consistent; do not change its contract.

## Requirements (Test Descriptions)
- [ ] `it reads Content-Type from the CGI CONTENT_TYPE server key when HTTP_CONTENT_TYPE is absent`
- [ ] `it still reads Content-Type from HTTP_CONTENT_TYPE when present`
- [ ] `it reads Content-Length from the CGI CONTENT_LENGTH server key`
- [ ] `it returns the default when neither header form is present`
- [ ] `it decodes a percent-encoded path segment in path()`
- [ ] `it strips the query string before decoding the path`
- [ ] `it returns the root path when REQUEST_URI is absent`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
