# Task 011: Roll out known-drivers pattern — marko/mail

**Status**: pending
**Depends on**: 001, 004
**Retry count**: 0

## Description
Apply the pilot pattern to `marko/mail`. Two drivers: `mail-log`, `mail-smtp`. Both bind `MailerInterface` and are mutually exclusive.

## Context
- Interface: `Marko\Mail\MailerInterface`
- Drivers: `marko/mail-log`, `marko/mail-smtp`
- Recommended-first ordering: `mail-smtp` (production-relevant default; mail-log is dev/test-only)
- Confirm both have `module.php` binding `MailerInterface`

**Description text for known-drivers.php:**
- `marko/mail-smtp` → `'SMTP mailer driver (recommended for production)'`
- `marko/mail-log` → `'Log-only mailer driver (writes emails to LoggerInterface; intended for development and testing)'`

## Sub-steps
1. Create `packages/mail/known-drivers.php`
2. Refactor `packages/mail/src/Exceptions/NoDriverException.php`. Update existing `packages/mail/tests/Unit/Exceptions/NoDriverExceptionTest.php` to match the new output format.
3. Add `packages/mail/tests/KnownDriversValidationTest.php`
4. Verify `marko/testing` is in `packages/mail/composer.json` `require-dev`; add it if missing

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing both mail drivers`
- [ ] `it lists marko/mail-smtp first as the recommended driver`
- [ ] `mail NoDriverException reads from known-drivers.php and includes docs URLs`

## Acceptance Criteria
- `packages/mail/known-drivers.php` exists
- `NoDriverException` refactored
- Validation test passes
- Existing mail tests still pass
- Code follows code standards
