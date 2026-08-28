# Task 002: Response Decoration API

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Give `Response` a decoration API (`withHeader()`, `withHeaders()`, `withStatus()`, `withCookie()`) that returns modified copies while preserving the concrete subclass, and add a cookie collection alongside the existing headers. This is the change that fixes the `StreamingResponse` downgrade at its root.

## Context
- Modify: `packages/routing/src/Http/Response.php`
- Modify: `packages/sse/src/StreamingResponse.php` (must also drop the `readonly` class modifier so `clone` works through the hierarchy)
- Tests: `packages/routing/tests/Http/ResponseTest.php`

**Verified on PHP 8.5.1 — read this before implementing.** `clone $this with { ... }` does not exist in 8.5.1 (parse error). Modifying a readonly property on a clone fails both by direct assignment and via `ReflectionProperty::setValue`. The only approach that works is: **plain class (no `readonly` class modifier), private non-readonly properties, `clone $this` then assign on the clone.**

- MUST use `clone $this`, never `new static(...)`. `StreamingResponse::__construct(SseStream $stream, int $statusCode)` has a different signature from its parent, so `new static(...)` cannot reconstruct it. `clone` copies all subclass state automatically — this is the entire mechanism behind the SSE fix.
- Return type on `with*()` methods must be `static`, not `self`.
- Immutability is now enforced by API design rather than the `readonly` keyword: properties stay private and no setters are added. Every `with*()` needs a test asserting the original instance is unchanged.
- Cookies are a SEPARATE collection (`list<Cookie>`), NOT multi-valued headers. `headers()` must keep its `array<string, string>` return type so every existing `->headers()` call site stays source-compatible (eight in production code, ~80 across the test suites).
- The 3-arg constructor and `json()` / `html()` / `redirect()` must remain source-compatible.

### Required public surface (other tasks build against this — do not rename)

```php
public function withHeader(string $name, string $value): static;
public function withHeaders(array $headers): static;   // array<string, string>, merged over existing
public function withStatus(int $statusCode): static;
public function withCookie(Cookie $cookie): static;
public function cookies(): array;                       // list<Cookie>
```

- **`withStatus()` is not optional.** `InertiaMiddleware.php:55` rebuilds the response specifically to change 302 → 303 for PUT/PATCH/DELETE. Without `withStatus()`, task 004 is hard-blocked on that branch.
- **`withHeaders()` is not optional either.** `SecurityHeadersMiddleware`, both `CorsMiddleware`s and `RateLimitMiddleware` all apply a *map* of headers via `array_merge`. A bulk method keeps task 004 a mechanical one-line change instead of a hand-unrolled chain.
- **`cookies()` is the accessor tasks 003 and 005 consume.** Task 003 needs it to build `Set-Cookie` lines; task 005 needs it to refuse caching. Name it exactly as above.
- **Duplicate-cookie semantics: replace, don't append.** `withCookie()` with a cookie matching an existing one on (name, path, domain) REPLACES it. This mirrors `withHeader()` and avoids emitting two competing `Set-Cookie` lines for the same name. Cookies differing in name, path, or domain accumulate.

### Do NOT convert the named constructors

`json()`, `html()` and `redirect()` stay `static ...: self` using `new self(...)`. Do **not** "improve" them to `new static(...)` for consistency with the `with*()` methods — `StreamingResponse`'s constructor cannot accept `(body:, statusCode:, headers:)`, so `StreamingResponse::json()` would fatal at runtime. That signature mismatch is precisely why `clone` is required. Late-static-bound named constructors are a separate follow-up, out of scope here.

## Requirements (Test Descriptions)
- [x] `it returns a new instance from withHeader leaving the original unchanged`
- [x] `it merges the new header into the existing headers`
- [x] `it merges a map of headers with withHeaders`
- [x] `it returns a new instance from withStatus leaving the original unchanged`
- [x] `it preserves the concrete subclass when decorating with a header`
- [x] `it preserves the concrete subclass when decorating with a status`
- [x] `it preserves subclass state such as the streaming payload when decorating`
- [x] `it still streams from a decorated streaming response`
- [x] `it does not clobber the sse headers when adding a header to a streaming response`
- [x] `it returns a new instance from withCookie leaving the original unchanged`
- [x] `it accumulates cookies that differ in name path or domain`
- [x] `it replaces a cookie matching an existing name path and domain`
- [x] `it keeps cookies out of the headers collection`

## Acceptance Criteria
- All requirements have passing tests
- `Response::json()`, `html()`, `redirect()` and the 3-arg constructor still work unchanged, and still use `new self()`
- `packages/sse/src/StreamingResponse.php` drops the `readonly` class modifier and its existing tests stay green
- Code follows code standards
- No decrease in test coverage

## Implementation Notes

- `Response` converted from `readonly class` to a plain `class` with a class-level docblock explaining why (clone-then-assign is required; readonly blocks it on PHP 8.5.1). Added `withHeader()`, `withHeaders()`, `withStatus()`, `withCookie()` (all returning `static`, all `clone $this` then assign on the clone) plus a private `list<Cookie> $cookies` property and `cookies(): array` accessor.
- `withCookie()` uses `array_find_key()` to locate a cookie matching on (name, path, domain) and replaces it in place; otherwise appends.
- `Cookie` (still `readonly class`, unchanged otherwise) gained narrow public accessors `name()`, `path()`, `domain()` — the minimum needed for `withCookie()`'s replace-matching logic. Added dedicated tests for these accessors in `CookieTest.php`, including a defaults-to-null case.
- `StreamingResponse` lost the `readonly` class modifier only; its constructor and `send()` are unchanged. `clone` now naturally carries over the private `$stream` property.
- Most requirements after the first (`withHeader` unchanged-original test) passed immediately on writing the test — the single structural change (clone-based `with*()` methods) needed for requirement 1 already implemented the full behavior for `withHeaders`, `withStatus`, `withCookie`, subclass preservation, and header non-clobbering. This was expected and noted per requirement rather than treated as a problem.
- `it still streams from a decorated streaming response` could not use `ob_start()`/`ob_get_clean()` to capture `send()`'s output: `StreamingResponse::send()` runs `while (ob_get_level() > 0) { ob_end_flush(); }` before echoing stream chunks, which forcibly closes any buffer the test itself started (including PHPUnit's), so chunks are echoed with no active buffer to capture them in-process. Test spawns a real PHP subprocess via `proc_open()` (using the project's `vendor/autoload.php`) and asserts on its actual stdout instead.
- Full suite: `composer test` → 6927 passed, 0 failed. `composer phpstan` → 0 errors project-wide. `phpcs` clean on all touched files after one `phpcbf` auto-fix (multi-line method signature) on `Response::withHeader()`.
