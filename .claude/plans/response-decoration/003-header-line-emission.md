# Task 003: Header Line Emission and Cookie-Aware send()

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Extract response header emission into a testable method that returns the complete set of outbound header lines including one `Set-Cookie` per cookie, and reduce `send()` to a thin loop over it. `header()` is unobservable under the CLI SAPI, so emission logic cannot be tested while it lives inside `send()`.

## Context
- Modify: `packages/routing/src/Http/Response.php` (`send()` at line 69)
- Modify: `packages/sse/src/StreamingResponse.php` (overrides `send()` and duplicates the header loop at lines 35-40 — it must consume the same method)
- Tests: `packages/routing/tests/Http/ResponseTest.php`
- This seam is also what the future RoadRunner bridge (#151) will consume to map a Marko response onto a PSR-7 response, so keep it free of any SAPI calls.
- `send()` keeps its existing `headers_sent()` guard and `http_response_code()` call; only the header-line construction moves.
- The extracted method reads `headers()` and `cookies()` (the accessor defined in task 002) and returns a `list<string>` of complete header lines. Name it `headerLines(): array` — task 006's tests assert against it by name.
- Emit regular headers first, then one `Set-Cookie` line per cookie, in insertion order.

## Requirements (Test Descriptions)
- [x] `it returns regular headers as name colon value lines`
- [x] `it returns a distinct set-cookie line for each cookie`
- [x] `it returns only regular header lines when the response has no cookies`
- [x] `it preserves cookie order in the emitted lines`
- [x] `it emits the same header lines for a streaming response subclass`

## Acceptance Criteria
- All requirements have passing tests
- No SAPI functions (`header()`, `setcookie()`) are called by the extracted method
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Added `Response::headerLines(): array` (returns `list<string>`) that emits regular headers as `"Name: value"` lines followed by one `"Set-Cookie: ..."` line per cookie (via `Cookie::toSetCookieString()`), in insertion order. Contains no SAPI calls.
- `Response::send()` and `StreamingResponse::send()` were reduced to loop `header($line)` over `headerLines()` instead of duplicating the header-construction logic.
- Requirements 3, 4, and 5 passed immediately once requirements 1-2 were implemented (order preservation and no-cookie behavior fall out naturally from the simple two-loop implementation; `headerLines()` is inherited unchanged by `StreamingResponse` since it is not overridden). Noted as expected, not over-implementation, since each test asserts a genuinely distinct requirement.
- The streaming-response test (`it emits the same header lines for a streaming response subclass`) was added to `packages/sse/tests/StreamingResponseTest.php` rather than `packages/routing/tests/Http/ResponseTest.php`, because `packages/routing` does not depend on `packages/sse` (dependency direction is the reverse) and `StreamingResponse` lives in the `sse` package.
- Verified: `composer test` (6932 passed), `composer phpstan` (0 errors), `./vendor/bin/phpcs` on touched files (0 errors), `php-cs-fixer fix` on touched files (only a pre-existing brace-placement fix unrelated to this change).

