# Task 003: Marko Response to PSR-7 Response Bridge

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Translate a Marko `Response` into a PSR-7 response for RoadRunner to emit. This is the other half of the worker boundary, and the half where cookies would silently vanish if done naively.

## Context
- The worker MUST NOT call `Response::send()` — it uses `header()`/`echo`, which are no-ops or wrong under the CLI SAPI that RoadRunner workers run on.
- Read `statusCode()` and `body()` directly.
- **Depends on #150 — seam verified.** `.claude/plans/response-decoration/003-header-line-emission.md` names the method **`Response::headerLines(): array`**, returning a `list<string>` of complete header lines: regular headers first as `Name: value`, then one `Set-Cookie` line per cookie in insertion order, with no SAPI calls. That plan's task 003 explicitly states this seam exists for the #151 bridge. Consume `headerLines()` by that exact name.
- **`headerLines()` returns pre-formatted strings, not pairs.** PSR-7 `withHeader()`/`withAddedHeader()` take a name and value, so the bridge must split each line on the first `: `. Multiple `Set-Cookie` lines must go through `withAddedHeader()`, never `withHeader()`, or every cookie but the last is dropped. This is the single most important thing to get right in this task.
- Cookies are a SEPARATE collection from headers on `Response` (a #150 decision), so a bridge that only reads `headers()` would drop every cookie, including the session cookie.
- **`StreamingResponse` detection.** `Marko\Sse\StreamingResponse` extends `Marko\Routing\Http\Response`, so an `instanceof` check works — but `marko/sse` may not be installed in a consuming app. Use `class_exists()`-guarded detection or match on the class name string so the bridge does not hard-depend on `marko/sse` at runtime. Task 001 adds `marko/sse` to this package's `require-dev` so the test can construct a real one.
- This per-request failure is the **real** guard against streaming responses. Task 007's boot-time refusal is a courtesy warning that can be opted out of; this one cannot. Fail loudly rather than emitting a silently empty body.

## Requirements (Test Descriptions)
- [x] `it maps the status code to the psr7 response`
- [x] `it maps the body to the psr7 response`
- [x] `it maps regular headers to the psr7 response`
- [x] `it emits a distinct set cookie header for each cookie on the response`
- [x] `it preserves multiple cookies rather than collapsing them`
- [x] `it preserves a header value containing a colon`
- [x] `it throws when handed a streaming response`
- [x] `it does not require the sse package to be installed`

## Acceptance Criteria
- All requirements have passing tests
- `Response::send()` is never called by the bridge
- The bridge consumes `Response::headerLines()` and does not reimplement `Set-Cookie` serialization
- Code follows code standards

## Implementation Notes

- `Marko\Roadrunner\Http\Psr7ResponseBridge` (`packages/roadrunner/src/Http/Psr7ResponseBridge.php`), `readonly class`, single public method `bridge(Response $response): ResponseInterface`. Builds a `Nyholm\Psr7\Response` from `statusCode()`/`body()`, then iterates `Response::headerLines()`, splitting each line on the first `': '` via `explode(': ', $line, 2)` (limit 2, so a colon inside a header value such as `Location: https://example.test/path` is preserved intact). `Set-Cookie` lines go through `withAddedHeader()`; every other header goes through `withHeader()` — this is what keeps multiple cookies from collapsing to the last one.
- `Response::send()` is never called; only `statusCode()`, `body()` and `headerLines()` are read. No `Set-Cookie` serialization is reimplemented — `Cookie::toSetCookieString()` (via `headerLines()`) is the sole source of the cookie string.
- **`StreamingResponse` detection without a hard `marko/sse` dependency**: the target class name is a private string constant, `'Marko\Sse\StreamingResponse'` (a plain string literal, not `Marko\Sse\StreamingResponse::class`, and no `use` import of the `Sse` namespace anywhere in the bridge), injected into the constructor as `string $streamingResponseClass` with that constant as its default (constructor-injection pattern, mirrors `Marko\PageCache\Boot\IdentityBridgeValidator`). `isStreamingResponse()` does `class_exists($this->streamingResponseClass) && is_a($response, $this->streamingResponseClass)` — the `class_exists()` guard means an app without `marko/sse` installed never triggers a class-not-found error. Detection throws `Marko\Roadrunner\Exceptions\StreamingResponseException` (extends `MarkoException`, static factory `unsupported(string $responseClass)`).
- The "does not require the sse package to be installed" test proves the no-hard-dependency claim by constructing the bridge with `streamingResponseClass: 'Marko\Sse\NotInstalledStreamingResponse'` (a class name that does not exist anywhere) and asserting a plain `Response` still bridges correctly — this exercises the exact `class_exists()`-false branch that a consuming app without `marko/sse` would hit, without needing to actually uninstall the require-dev package from the test run.
- Two requirements passed immediately when their test was written, because the minimal implementation for an earlier requirement already covered them: "emits a distinct set cookie header for each cookie" (a single-cookie case already worked under plain `withHeader()`, before the `withAddedHeader()` fix landed for requirement 5) and "does not require the sse package to be installed" (the constructor-injected, `class_exists()`-guarded detection built for requirement 7 already satisfied it). Noted per TDD rules rather than retrofitting artificial red states.
- Verification: `./vendor/bin/pest packages/roadrunner/tests/Http/Psr7ResponseBridgeTest.php --parallel` → 8 passed (10 assertions); `packages/roadrunner/tests/ComposerConfigurationTest.php` still green (unaffected). `phpstan analyse packages/roadrunner/src/Http/Psr7ResponseBridge.php packages/roadrunner/src/Exceptions/StreamingResponseException.php --level=6` → no errors. `phpcs` and `php-cs-fixer --diff` on both new source files and the test file → clean, no fixes needed.
- Two other files under `packages/roadrunner/` (`tests/Http/Psr7RequestBridgeTest.php` and `src/GuardRails/UnsafePackageChecker.php`) were observed mid-edit during this task — evidence of a concurrent agent working task 002/007 in the same working tree. Neither was touched; the one pre-existing PHPStan finding in `GuardRails/UnsafePackageChecker.php` belongs to that other task, not this one, and this task's own PHPStan run (scoped to its two files) is clean.
