# Task 002: Implement Email Formatting and Logging

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Implement the logging logic: format Message objects into structured log context and call `LoggerInterface::info()` with email metadata. Log bodies at debug level to avoid bloating logs.

## Context
- Related files: `packages/mail-log/src/LogMailer.php`, `packages/mail/src/Message.php`, `packages/mail/src/Address.php`, `packages/log/src/Contracts/LoggerInterface.php`
- Use INFO level for "Email sent" with metadata context
- Use DEBUG level for full body content (optional, when debug logging enabled)
- Context array follows PSR-3 conventions

## Requirements (Test Descriptions)
- [ ] `it logs email sent message at info level`
- [ ] `it includes from address in log context`
- [ ] `it includes to addresses in log context`
- [ ] `it includes cc addresses in log context when present`
- [ ] `it includes bcc addresses in log context when present`
- [ ] `it includes subject in log context`
- [ ] `it includes has_html flag in log context`
- [ ] `it includes has_text flag in log context`
- [ ] `it includes attachment count in log context`
- [ ] `it logs text body at debug level`
- [ ] `it logs html body at debug level`
- [ ] `it logs raw email content for sendRaw`
- [ ] `it includes attachment metadata without binary content`

## Acceptance Criteria
- `send()` logs at INFO level with structured context
- Body content logged at DEBUG level (not INFO)
- Attachments show name, size, and type only
- All requirements have passing tests

## Implementation Notes
(Left blank - filled in by programmer during implementation)
