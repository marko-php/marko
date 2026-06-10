# Task 019: errors-simple does not destroy the response on recoverable warnings and surfaces non-fatal errors in web SAPI

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
Two defects in the simple error handler:

1. **Recoverable warnings tear down the in-progress response.** `handleError()` (lines 101-127) only special-cases `Severity::Deprecated` and `Severity::Notice`; every other level — including `E_WARNING` (which maps to a non-fatal severity) and any unmapped level — falls through to `handleException()` → `handle()`, which clears ALL output buffers (`clearOutputBuffers()`, 64-70), sets a 500 status, prints a full HTML 500 page, and then `return true` so execution CONTINUES. A single recoverable `E_WARNING` during a request therefore destroys the half-rendered response and, because execution continues, can stack additional 500 pages. Recoverable warnings must be reported/logged WITHOUT replacing the response.

2. **Non-fatal errors are silently dropped in web SAPI.** `handleNonFatal()` (87-99) opens with `if (!$this->environment->isCli()) { return; }`, so notices/deprecations (and, after fix 1, warnings) are silently discarded under a web SAPI — a silent failure inside the "loud errors" driver. Non-fatal errors must be surfaced in web SAPI (e.g. reported via the logging/report seam), not dropped.

## Context
- Related files:
  - `packages/errors-simple/src/SimpleErrorHandler.php`:
    - `handle()` 45-62 — `clearOutputBuffers()` then 500 page (web) / text (cli); this is the FULL-PAGE path and must stay reserved for genuinely fatal/uncaught throwables
    - `clearOutputBuffers()` 64-70 — destroys all output buffers
    - `handleException()` 80-85 — builds `Severity::Error` report and calls `handle()`
    - `handleNonFatal()` 87-99 — early `return` under non-CLI; writes colored line to STDERR otherwise
    - `handleError()` 101-127 — `error_reporting` mask check (108-110); Deprecated/Notice → `handleNonFatal` + return (117-122); ELSE → `handleException()` + return true (124-126)  ← the destructive fall-through
    - `handleShutdown()` 141-161 — fatal-types-only path; must remain the route for true fatals (E_ERROR/E_PARSE/E_CORE_ERROR/E_COMPILE_ERROR)
  - `packages/errors/src/...` — `ErrorReport`, `Severity` (use `Severity::fromErrorLevel()` and the existing severity enum; warnings are non-fatal recoverable)
- Patterns to follow:
  - Route recoverable non-fatal levels (Deprecated, Notice, Warning, and other non-fatal mappings) to a non-destructive report path that does NOT call `clearOutputBuffers()` and does NOT emit a 500 page. Reserve `handle()`/`handleException()`'s full-page teardown for uncaught exceptions and shutdown-detected fatals.
  - `handleNonFatal()` must surface non-fatal errors in BOTH SAPIs. In CLI keep the STDERR line; in web SAPI report through the report/logging seam rather than early-returning. Do not echo into the response body in web SAPI (that would corrupt the in-progress response) — surface it where it is observable without mutating the response.
  - Loud errors: the goal is the OPPOSITE of silent — non-fatal errors must end up reported. Keep `return true`/`return false` semantics for `handleError()` consistent with PHP's handler contract (respect the `error_reporting()` mask short-circuit at 108-110).
  - Decide the non-fatal severity set explicitly via `Severity` (don't hardcode raw `E_*` bitmasks in the branch beyond the existing fatal-shutdown set).

### Coordination note
Tier 2 Task 002 touches errors-**ADVANCED** and references `SimpleErrorHandler` as a model. THIS task fixes errors-**SIMPLE**'s own bugs. Coordinate so the two don't diverge on how non-fatal vs fatal is classified, but they edit different packages — no shared-file rebase, only a consistency check on the severity routing.

### Verification note (read at planning time)
Confirmed against source: `handleError()` 101-127 only special-cases Deprecated/Notice and otherwise calls `handleException()` (destructive 500 + continue). `handleNonFatal()` 87-99 early-returns under `!isCli()`. The "E_WARNING destroys response" and "web notices dropped" findings both hold. (Original cited `handleNonFatal ~87-99` and the destructive path `~101-127` — exact match.)

## Requirements (Test Descriptions)
- [ ] `it does not clear output buffers when handling a recoverable warning`
- [ ] `it does not replace the response with a 500 page on a recoverable warning`
- [ ] `it reports a non-fatal error in web SAPI instead of discarding it`
- [ ] `it still renders a 500 page for an uncaught exception`
- [ ] `it still handles a fatal error on shutdown`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
