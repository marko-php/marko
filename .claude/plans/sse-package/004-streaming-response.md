# Task 004: StreamingResponse

**Status**: completed
**Depends on**: 001, 002, 003
**Retry count**: 0

## Description
Create `StreamingResponse`, a readonly class extending `Marko\Routing\Http\Response`. It sets SSE-specific headers in its constructor and overrides `send()` to disable output buffering, remove the time limit, iterate over the `SseStream` generator, and handle I/O (echo, flush, connection abort detection, sleep between ticks). This class is the bridge between the pure `SseStream` generator and the HTTP layer.

## Context
- Related files:
  - `packages/sse/src/StreamingResponse.php` — create this file
  - `packages/sse/tests/StreamingResponseTest.php` — create this file
  - `packages/routing/src/Http/Response.php` — parent class (readonly class)
  - `packages/routing/src/Router.php` — `wrapResult()` does `$result instanceof Response` check
- Key design points:
  - `readonly class StreamingResponse extends Response` — PHP 8.2+ allows readonly-extends-readonly
  - Constructor calls `parent::__construct(body: '', statusCode: $statusCode, headers: [SSE headers])`
  - Adds one new property: `private SseStream $stream`
  - SSE headers: `Content-Type: text/event-stream`, `Cache-Control: no-cache`, `Connection: keep-alive`, `X-Accel-Buffering: no`
  - `send()` override handles: output buffering cleanup, `set_time_limit(0)`, foreach over stream generator with `echo`/`flush()`, `connection_aborted()` check, `sleep()` is handled inside the generator
  - The `send()` method cannot be meaningfully unit-tested (requires HTTP context). Test construction only.
- Router integration: works automatically via `$result instanceof Response` in `Router::wrapResult()`
- Patterns to follow:
  - `readonly class` extending `readonly class Response`
  - `declare(strict_types=1)` at top
  - `#[\Override]` attribute on `send()` method
  - `@throws JsonException` on `send()` (propagated from SseStream iteration)

## Requirements (Test Descriptions)
- [ ] `it extends Response`
- [ ] `it sets Content-Type header to text/event-stream`
- [ ] `it sets Cache-Control header to no-cache`
- [ ] `it sets Connection header to keep-alive`
- [ ] `it sets X-Accel-Buffering header to no`
- [ ] `it has 200 status code by default`
- [ ] `it accepts custom status code`
- [ ] `it has empty body`

## Acceptance Criteria
- All requirements have passing tests
- `StreamingResponse` is a `readonly class` extending `Response`
- Constructor takes `SseStream $stream` and optional `int $statusCode = 200`
- `send()` method is overridden with `#[\Override]` attribute
- All four SSE headers set correctly
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
