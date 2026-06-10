# Task 009: F4 — Reject CRLF in Address name and Message custom headers

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
Stop mail header (CRLF) injection at the source value objects in `marko/mail`. Reject (loud exception) any carriage return or line feed in `Address::$name` (constructor) and in custom header name AND value (`Message::header()`). Add a `MessageException::headerInjection()` factory. The email address is already `FILTER_VALIDATE_EMAIL`'d; the display name and custom headers are the unvalidated injection vectors.

## Context
- Related files:
  - `packages/mail/src/Address.php` (constructor validates email only; `$name` raw; `toString()` ~22)
  - `packages/mail/src/Message.php` (`header()` ~159 stores arbitrary name/value into `$headers`)
  - `packages/mail/src/Exceptions/MessageException.php` (add `headerInjection()` factory next to `invalidEmailAddress()`)
  - `packages/mail/tests/` (Pest)
- Patterns to follow:
  - Existing `MessageException::invalidEmailAddress()` three-part factory and the `@throws MessageException` annotation style already on `Address::__construct` / `Message::*`.
  - Reject CR (`\r`) and LF (`\n`) anywhere in the string; do NOT silently strip.

## Requirements (Test Descriptions)
- [ ] `it rejects a carriage return in the Address display name`
- [ ] `it rejects a line feed in the Address display name`
- [ ] `it still constructs an Address with a legitimate display name`
- [ ] `it rejects a carriage return or line feed in a custom header name`
- [ ] `it rejects a carriage return or line feed in a custom header value`
- [ ] `it still stores a legitimate custom header`
- [ ] `it throws MessageException with a helpful suggestion when header injection is detected`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
