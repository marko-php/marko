# Task 019: Clean Up Hardcoded Suggestions from Other Exception Classes

**Status**: pending
**Depends on**: 002, 003, 004, 005, 006, 007, 008, 009, 010, 011, 012, 013, 014, 015, 016, 017, 018
**Retry count**: 0

## Description
Remove hardcoded driver installation suggestions from exception classes that are no longer the primary source of these messages. The `NoDriverException` in each package now owns driver suggestions. Other exception methods that had inline suggestions should either be removed (if unused in runtime) or updated to defer to `NoDriverException`.

## Context
- Related files:
  - `packages/mail/src/Exception/MailException.php` — has `'Install a mail driver package: composer require marko/mail-smtp'`
  - `packages/queue/src/Exceptions/QueueException.php` — has `'Install a queue driver: composer require marko/queue-sync or marko/queue-database'`
  - `packages/notification/src/Exceptions/NotificationException.php` — has `'Install a queue driver: composer require marko/queue-sync or marko/queue-database'`
  - `packages/filesystem/src/Discovery/DriverRegistry.php` — has `'For local storage: composer require marko/filesystem-local'` (this is about an unknown driver name, not no driver installed -- leave alone or update independently)
  - `packages/database/src/Exceptions/DatabaseException.php` — has `DRIVER_PACKAGES` constant and `noDriverInstalled(string $driver)` — evaluate if still needed
  - Tests for all of the above
- Patterns to follow: the new `NoDriverException` pattern from prior tasks

## Requirements (Test Descriptions)
- [ ] `MailException::noDriverInstalled() method is removed` (only this method -- `configFileNotFound()` must remain on the class)
- [ ] `QueueException::noDriverInstalled() method is removed` (only this method -- `configFileNotFound()` must remain on the class)
- [ ] `NotificationException::noQueueAvailable() is NOT removed` (it is called from runtime code in `NotificationSender.php` line 62; its suggestion text about queue drivers may be updated but the method must stay)
- [ ] `DatabaseException::noDriverInstalled(string $driver) is kept` (it serves a different purpose -- specific driver not installed vs. no driver at all; rename its `DRIVER_PACKAGES` constant to `KNOWN_DRIVERS` to avoid confusion with NoDriverException::DRIVER_PACKAGES)
- [ ] `all related tests are updated to reflect the changes`

## Acceptance Criteria
- All requirements have passing tests
- No duplicate driver suggestions exist across exception classes (except `NotificationException::noQueueAvailable()` which is a cross-package concern about queue drivers, not notification drivers)
- Each package has exactly one place for "no driver installed" suggestions: `NoDriverException`
- `MailException` class still exists with `configFileNotFound()` method
- `QueueException` class still exists with `configFileNotFound()` method
- `NotificationException::noQueueAvailable()` still exists and is callable from `NotificationSender`
- `DatabaseException::noDriverInstalled(string $driver)` still exists with renamed constant `KNOWN_DRIVERS`

## Implementation Notes
Runtime code analysis results:
- `MailException::noDriverInstalled()` -- NOT called from runtime code, only tests. Safe to remove.
- `MailException::configFileNotFound()` -- called from `MailConfig.php`. Do NOT remove.
- `QueueException::noDriverInstalled()` -- NOT called from runtime code, only tests. Safe to remove.
- `QueueException::configFileNotFound()` -- exists on the class. Do NOT remove.
- `NotificationException::noQueueAvailable()` -- CALLED from `NotificationSender.php` line 62. Do NOT remove. May update suggestion text but method must stay.
- `DatabaseException::noDriverInstalled(string $driver)` -- NOT called from runtime code currently, but serves a distinct purpose (specific driver missing vs. no driver at all). Keep the method, rename constant `DRIVER_PACKAGES` to `KNOWN_DRIVERS`.
- `DriverRegistry::get()` in filesystem -- has hardcoded suggestion about an unknown driver name, not about no drivers installed. This is a different concern; leave it alone or update text to be more generic.
