# Task 003: SSE `event`/`id` CR/LF sanitization

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
`SseEvent::format()` correctly line-splits `data`, but interpolates `event` and `id` raw into `event: ...\n` / `id: ...\n`. A CR or LF in either field injects arbitrary SSE fields into the stream. Reject CR/LF in `event` and `id` loudly at construction (or in `format()`), matching the care taken on `data`.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/sse/src/SseEvent.php` (`event`/`id` interpolation ~27-37)
  - `/Users/markshust/Sites/marko/packages/sse/src/Exceptions/SseException.php` (add `invalidField()` factory; extends `Marko\Core\Exceptions\MarkoException`)
  - Tests: `/Users/markshust/Sites/marko/packages/sse/tests/SseEventTest.php`
- Patterns to follow:
  - `SseEvent` is a `readonly class`; prefer validating in the constructor so an invalid event can never exist (throw `SseException::invalidField(...)`).
  - Loud-error factory with `message`/`context`/`suggestion`, consistent with existing `SseException::ambiguousSource()`/`noSource()`.
  - Reject (do not silently strip) — the audit prefers loud failure; CR is `\r`, LF is `\n`. Check `str_contains($value, "\n") || str_contains($value, "\r")`.
  - `id` is `string|int|null`; only string ids can contain CR/LF. Guard with `is_string($this->id)` BEFORE calling `str_contains` — passing an int to `str_contains` under `strict_types=1` raises a `TypeError`, so an int id must skip the CRLF check entirely (it is inherently safe).
  - `event` is `?string` — null skips the check; only a non-null string is validated.
  - `data` line-splitting behavior must remain unchanged. Note: array `data` is JSON-encoded (CRLF becomes the escaped `\n` sequence, already safe) and string `data` is split into multiple `data:` lines by design — neither is in scope; only `event`/`id` interpolation is fixed.
  - Validate in the constructor (the class is `readonly`), so an `SseEvent` with a CRLF-bearing `event`/`id` can never be constructed. Add `@throws SseException` to the constructor PHPDoc.

## Requirements (Test Descriptions)
- [x] `it throws SseException when event contains a line feed`
- [x] `it throws SseException when event contains a carriage return`
- [x] `it throws SseException when a string id contains a line feed`
- [x] `it allows a normal event and id without CRLF`
- [x] `it still splits multi-line data into multiple data fields`
- [x] `it allows an integer id unchanged`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Added `SseException::invalidField(string $field, string $value): self` static factory with three-part message/context/suggestion pattern matching existing factories.
- Added CR/LF validation in `SseEvent` constructor: checks `event` (non-null string only) and `id` (string only — int ids skip the check entirely to avoid TypeError under strict_types).
- Added `@throws SseException` PHPDoc to the constructor.
- `SseException.php` needed `json_encode()` in the context message — no `@throws JsonException` needed as it uses no flags that throw.
- Data splitting behavior is unchanged; only `event` and `id` fields are validated.
- All 6 requirements were covered by the single implementation in the first test cycle (CR and LF are checked together, id and event validated in the same constructor body).
