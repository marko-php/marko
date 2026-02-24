# Task 002: SseEvent Value Object

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the `SseEvent` readonly value object that represents a single SSE frame. It holds the event data, optional type/id/retry fields, and provides a `format()` method that produces spec-compliant SSE frame text.

## Context
- Related files:
  - `packages/sse/src/SseEvent.php` — create this file
  - `packages/sse/tests/SseEventTest.php` — create this file
- SSE frame spec:
  - Each field on its own line: `field: value\n`
  - Frame terminated by double newline `\n\n`
  - Multi-line `data` values split into multiple `data:` lines
  - Array data JSON-encoded before formatting
  - Field order: event, id, retry, data (by convention)
- Patterns to follow:
  - `readonly class` (all properties are immutable)
  - Constructor property promotion with `public` visibility (value object)
  - `declare(strict_types=1)` at top
  - `@throws JsonException` on `format()` since `json_encode` can throw

## Requirements (Test Descriptions)
- [ ] `it formats event with all fields (event, id, retry, data)`
- [ ] `it formats event with only string data`
- [ ] `it JSON-encodes array data`
- [ ] `it splits multi-line data into multiple data lines`
- [ ] `it includes retry field in milliseconds`
- [ ] `it omits null fields from output`
- [ ] `it terminates frame with double newline`

## Acceptance Criteria
- All requirements have passing tests
- `SseEvent` is a `readonly class` with constructor promotion
- `format()` returns spec-compliant SSE frame string
- `@throws JsonException` documented on `format()`
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
