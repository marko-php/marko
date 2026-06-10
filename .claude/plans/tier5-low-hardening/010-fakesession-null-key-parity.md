# Task 010: FakeSession null-key `has()` parity

**Status**: pending
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
- [ ] `it reports a key with a stored null value as present`
- [ ] `it reports a never-set key as absent`
- [ ] `it reports a key with a non-null value as present`
- [ ] `it reports a removed key as absent`
- [ ] `it matches production Session has semantics for null values`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
