# Task 010: FakeSession null-key `has()` parity

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
`FakeSession::has()` uses `isset($this->data[$key])`, which reports a key whose stored value is `null` as absent. Production `Session::has()` uses `array_key_exists()`, so a stored `null` reports present. This divergence makes the fake unfaithful for null values. Align `FakeSession::has()` to use `array_key_exists()` so it matches production semantics.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/testing/src/Fake/FakeSession.php` (`has()` ~46-50)
  - Reference (production behavior): `/Users/markshust/Sites/marko/packages/session/src/Session.php` (`has()` uses `array_key_exists`)
  - Tests: `/Users/markshust/Sites/marko/packages/testing/tests/` (FakeSession tests)
- Patterns to follow:
  - `FakeSession` is a mixed-mutability class (mutable `$data`, `private(set)` flags). The change is one line: `isset` → `array_key_exists`.
  - Do not change `get()` (which intentionally returns the default for both missing and null — `??` is correct for a getter with a default). Only `has()` must change.
  - Test the contract, not the implementation: store `null`, assert `has()` is true; assert `has()` is false for a never-set key.

## Requirements (Test Descriptions)
- [x] `it reports a key with a stored null value as present`
- [x] `it reports a never-set key as absent`
- [x] `it reports a key with a non-null value as present`
- [x] `it reports a removed key as absent`
- [x] `it matches production Session has semantics for null values`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Changed `FakeSession::has()` from `isset($this->data[$key])` to `array_key_exists($key, $this->data)` in `packages/testing/src/Fake/FakeSession.php` line 50.
- This is a single-line fix that aligns null semantics with production `Session::has()`.
- `get()` was intentionally left unchanged — the `??` operator is correct for a getter with a default, returning the default for both missing and null values.
- Added 4 targeted tests to `packages/testing/tests/Unit/Fake/FakeSessionTest.php` covering null-stored keys, never-set keys, non-null keys, removed keys, and a combined production-semantics parity test.
