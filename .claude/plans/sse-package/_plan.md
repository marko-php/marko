# Plan: marko/sse — Server-Sent Events Package

## Created
2026-02-24

## Status
completed

## Objective
Add Server-Sent Events (SSE) support to Marko by creating a `marko/sse` package with `SseEvent` (value object), `SseStream` (generator-based stream), and `StreamingResponse` (extends `Response` for SSE endpoints). Zero changes to `marko/routing`.

## Scope

### In Scope
- `SseEvent` readonly value object — formats SSE frames per the SSE spec
- `SseStream` readonly IteratorAggregate — polls a data provider, yields formatted frames and heartbeats
- `StreamingResponse` readonly class extending `Marko\Routing\Http\Response` — sets SSE headers, overrides `send()` to stream
- `SseException` extending `MarkoException`
- Package scaffolding: `composer.json`, `module.php`, `tests/Pest.php`
- Root `composer.json` autoload entries
- `README.md`

### Out of Scope
- Changes to `marko/routing` (StreamingResponse extends Response — polymorphism handles everything)
- Integration with `marko/notification` (app-level concern)
- Client-side JavaScript (browser-native `EventSource` handles this)
- Database/queue integration (data providers are app-level closures)
- Authentication middleware (existing middleware works unchanged)

## Success Criteria
- [ ] `SseEvent::format()` produces spec-compliant SSE frames
- [ ] `SseStream` yields events from data provider and heartbeats when idle
- [ ] `StreamingResponse` extends `Response` with SSE headers
- [ ] All tests passing with `./vendor/bin/pest packages/sse/tests/ --parallel`
- [ ] Code follows project standards (linter passes)
- [ ] README.md follows Package README Standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding (composer.json, module.php, Pest.php, exception, root autoload) | - | completed |
| 002 | SseEvent value object with format() | 001 | completed |
| 003 | SseStream IteratorAggregate with data provider polling and heartbeats | 001, 002 | completed |
| 004 | StreamingResponse extending Response | 001, 002, 003 | completed |
| 005 | Package README.md | 001, 002, 003, 004 | completed |

## Architecture Notes

### Design Decisions

**StreamingResponse extends Response** — The existing `Response` is a `readonly class`. `StreamingResponse` extends it (also `readonly`), calls `parent::__construct()` with SSE headers and empty body, and overrides `send()` to stream. The Router's `wrapResult()` already handles `$result instanceof Response`, middleware pipeline returns `Response` — everything works via polymorphism. Zero changes to `marko/routing`.

**SseStream as IteratorAggregate** — Rather than embedding I/O concerns (sleep, flush, connection_aborted) in SseStream, it's a generator that yields string chunks. `StreamingResponse::send()` handles the I/O loop (echo, flush, sleep, connection checking). This makes SseStream fully testable — tests iterate the generator with `timeout: 0` for instant verification.

**Generator flow:**
```
SseStream::getIterator()
  └─ do {
       call dataProvider → get SseEvent[]
       foreach event → yield event.format()
       if heartbeat interval elapsed → yield ": keepalive\n\n"
       if timeout reached → return
       sleep(pollInterval)
     } while (true)
```

**Testing strategy:**
- `SseEvent::format()` — pure function, exhaustively testable
- `SseStream` — use `timeout: 0` so generator runs exactly one tick with no sleep. Use `heartbeatInterval: 0` to test heartbeat in same single tick.
- `StreamingResponse` — test construction (headers, status code, extends Response). The `send()` method requires HTTP context and isn't unit-testable.

### Key Classes

| Class | Type | Testability |
|-------|------|-------------|
| `SseEvent` | readonly value object | Fully testable (pure `format()`) |
| `SseStream` | readonly IteratorAggregate | Testable via generator iteration with timeout:0 |
| `StreamingResponse` | readonly extends Response | Construction testable, `send()` requires HTTP context |
| `SseException` | exception | N/A (marker class) |

## Risks & Mitigations
- **Output buffering conflicts in tests**: SseStream is a pure generator — no ob_* calls. Those live in StreamingResponse::send() which isn't unit-tested.
- **sleep() in generator**: With `timeout: 0`, the generator exits before reaching `sleep()`. Multi-tick tests use `pollInterval: 0` (sleep(0) is a no-op).
- **readonly class extension**: PHP 8.2+ allows readonly-extends-readonly. StreamingResponse adds one new readonly property (`SseStream $stream`).
