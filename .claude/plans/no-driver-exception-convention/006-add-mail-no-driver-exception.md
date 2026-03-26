# Task 006: Add NoDriverException to Mail Package

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create a `NoDriverException` in the mail package following the standard pattern. The existing `MailException::noTransport()` has a hardcoded suggestion — that method should be updated or replaced by this new class.

## Context
- Related files:
  - `packages/mail/src/Exception/MailException.php` (has hardcoded `composer require marko/mail-smtp` suggestion)
  - `packages/mail/tests/Unit/Exception/MailExceptionTest.php`
  - `packages/mail/tests/Integration/MailIntegrationTest.php`
- Base exception: `MessageException` extends `MarkoException`, so `NoDriverException` should extend `MarkoException`
- Driver packages: `marko/mail-log`, `marko/mail-smtp`
- No module.php exists for this package
- Note: mail exceptions are in `Exception/` (singular), not `Exceptions/` (plural) — check actual directory structure

## Requirements (Test Descriptions)
- [ ] `it has DRIVER_PACKAGES constant listing marko/mail-log and marko/mail-smtp`
- [ ] `it provides suggestion with composer require commands for all driver packages`
- [ ] `it includes context about resolving mail interfaces`
- [ ] `it extends MarkoException`

## Acceptance Criteria
- All requirements have passing tests
- Follows the standard NoDriverException pattern
- Placed in `packages/mail/src/Exceptions/NoDriverException.php` (namespace `Marko\Mail\Exceptions`)

## Implementation Notes
The mail package has TWO exception directories: `src/Exception/` (singular, contains `MailException`, `MessageException`, `TransportException` in namespace `Marko\Mail\Exception`) and `src/Exceptions/` (plural, contains a different `MessageException` in namespace `Marko\Mail\Exceptions`). The `NoDriverException` **must** go in `packages/mail/src/Exceptions/NoDriverException.php` with namespace `Marko\Mail\Exceptions` to match the container convention which looks for `Marko\{Package}\Exceptions\NoDriverException`. It should extend `MarkoException` directly (not `MailException`, which is in the `Marko\Mail\Exception` singular namespace).
