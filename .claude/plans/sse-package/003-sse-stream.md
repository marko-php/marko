# Task 003: SseStream IteratorAggregate

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Create `SseStream`, a readonly class implementing `IteratorAggregate` that manages the SSE data polling loop. It accepts a data provider callable, polls it each tick, yields formatted SSE frame strings from `SseEvent::format()`, and yields heartbeat comments when the connection is idle. The generator approach keeps I/O concerns (echo, flush, sleep, connection checking) out of SseStream — those belong in `StreamingResponse::send()`.

## Context
- Related files:
  - `packages/sse/src/SseStream.php` — create this file
  - `packages/sse/tests/SseStreamTest.php` — create this file
  - `packages/sse/src/SseEvent.php` — used by data provider return values
- Testing strategy:
  - `timeout: 0` makes the generator run exactly ONE tick then exit (no `sleep()` reached)
  - `heartbeatInterval: 0` forces heartbeat on first tick when no events
  - `pollInterval: 0` with `timeout: 1` for multi-tick tests (sleep(0) is a no-op)
- Generator loop logic:
  ```
  do {
    events = dataProvider()
    foreach events → yield event.format()
    if heartbeat interval elapsed and no events yielded → yield ": keepalive\n\n"
    if timeout reached → return
    sleep(pollInterval)
  } while (true)
  ```
- Patterns to follow:
  - `readonly class` (all constructor properties are immutable, loop vars are local)
  - `implements IteratorAggregate` with `getIterator(): Generator`
  - `@throws JsonException` documented (propagated from SseEvent::format())

## Requirements (Test Descriptions)
- [ ] `it yields formatted events from data provider`
- [ ] `it yields heartbeat comment when no events and heartbeat interval elapsed`
- [ ] `it stops iteration after timeout`
- [ ] `it yields events from multiple data provider calls across ticks`
- [ ] `it does not yield heartbeat when events were sent within interval`
- [ ] `it yields no output when data provider returns empty and heartbeat not due`

## Acceptance Criteria
- All requirements have passing tests
- `SseStream` is a `readonly class` implementing `IteratorAggregate`
- Constructor takes: `Closure $dataProvider`, `int $heartbeatInterval = 15`, `int $timeout = 300`, `int $pollInterval = 1`
- Generator yields only `string` values (formatted SSE frames or heartbeat comments)
- `@throws JsonException` documented on `getIterator()`
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
