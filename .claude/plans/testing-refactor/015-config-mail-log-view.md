# Task 015: Config refactor — mail, log, view packages

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Replace ConfigRepositoryInterface anonymous stubs in mail, mail-smtp, log, and view test files with FakeConfigRepository. Add `marko/testing` to require-dev for each package.

## Context
- Related files:
  - `packages/mail/tests/Unit/Config/MailConfigTest.php` — ConfigRepositoryInterface (line 13)
  - `packages/mail/tests/Integration/MailIntegrationTest.php` — ConfigRepositoryInterface (line 26)
  - `packages/mail-smtp/tests/SmtpConfigTest.php` — ConfigRepositoryInterface (line 13)
  - `packages/log/tests/Unit/Config/LogConfigTest.php` — ConfigRepositoryInterface (line 14)
  - `packages/view/tests/ViewConfigTest.php` — ConfigRepositoryInterface (line 12)
  - `packages/view/tests/ModuleTemplateResolverTest.php` — ConfigRepositoryInterface (line 17)
  - 4 composer.json files to update (mail, mail-smtp, log, view)

## Requirements (Test Descriptions)
- [ ] `it uses FakeConfigRepository in MailConfigTest`
- [ ] `it uses FakeConfigRepository in MailIntegrationTest`
- [ ] `it uses FakeConfigRepository in SmtpConfigTest`
- [ ] `it uses FakeConfigRepository in LogConfigTest`
- [ ] `it uses FakeConfigRepository in ViewConfigTest`
- [ ] `it uses FakeConfigRepository in ModuleTemplateResolverTest`
- [ ] `it preserves all existing test assertions and behaviors`

## Acceptance Criteria
- All existing mail, mail-smtp, log, view package tests pass unchanged
- Config stubs removed from all 6 files
- `marko/testing` added to require-dev in all 4 composer.json files
- Run: `./vendor/bin/pest packages/mail/tests/ packages/mail-smtp/tests/ packages/log/tests/ packages/view/tests/ --parallel`
