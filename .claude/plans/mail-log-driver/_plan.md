# Plan: Mail Log Driver Package

## Created
2026-01-29

## Status
completed

## Objective
Create `marko/mail-log` package that implements `MailerInterface` by logging emails via the existing `LoggerInterface`, providing a zero-configuration dev/testing solution.

## Scope

### In Scope
- `LogMailer` class implementing `MailerInterface`
- Uses existing `LoggerInterface` to log emails
- Human-readable log format for email content
- Module bindings for DI registration
- Comprehensive test coverage
- Update blog's `composer.json` to suggest the driver

### Out of Scope
- In-memory mode for testing (future `mail-array` package)
- Custom log channel (uses default logger)
- HTML rendering/prettifying

## Success Criteria
- [ ] `LogMailer::send()` logs message via LoggerInterface
- [ ] `LogMailer::sendRaw()` logs raw content via LoggerInterface
- [ ] Log entries are human-readable with all message details
- [ ] Package depends on `marko/log` (not `log-file` - that's the app's choice)
- [ ] All tests passing
- [ ] Blog composer.json suggests mail-log driver

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Create package structure and LogMailer class | - | completed |
| 002 | Implement email formatting for logging | 001 | completed |
| 003 | Add module.php bindings and update blog suggestions | 002 | completed |

## Architecture Notes

**Dependency:** `marko/mail-log` depends on `marko/log` (the interface package), not `marko/log-file`. The application chooses which log driver to use.

**Log Output Example:**
```
[2026-01-29 14:32:15] mail.INFO: Email sent {"from":"hello@example.com","to":["user@test.com"],"subject":"Welcome!","has_html":true,"has_text":true,"attachments":0}
```

For detailed view, could also log the body as debug level.

**File Structure:**
```
packages/mail-log/
├── composer.json
├── module.php
├── src/
│   └── LogMailer.php
└── tests/
    ├── Pest.php
    └── Unit/
        └── LogMailerTest.php
```

## Risks & Mitigations
- Large email bodies in logs: Log metadata at INFO level, full body at DEBUG level only
- Attachments: Log metadata only (name, size, type), never binary content
