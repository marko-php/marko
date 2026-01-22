# Task 020: Integration Tests

**Status**: completed
**Depends on**: 013
**Retry count**: 0

## Description
Integration tests for mail package and mail-smtp package working together.

## Context
- Test module loading and DI bindings
- Test MailConfig loading from config files
- Test SmtpConfig extraction
- Test factory creation
- Test no driver installed error

## Requirements (Test Descriptions)
- [ ] `MailConfig loads from config file`
- [ ] `SmtpConfig extracts SMTP settings from MailConfig`
- [ ] `SmtpMailerFactory creates configured mailer`
- [ ] `Module bindings resolve correctly`
- [ ] `Missing driver throws MailException`

## Implementation Notes
