# Task 009: SmtpConfig Class

**Status**: completed
**Depends on**: 007
**Retry count**: 0

## Description
Create the SmtpConfig class for SMTP-specific configuration.

## Context
- Extracts SMTP settings from MailConfig
- Host, port, encryption (tls/ssl/null), username, password
- Timeout and auth_mode (login/plain/null)
- Provides typed accessors for all values

## Requirements (Test Descriptions)
- [ ] `SmtpConfig extracts host from mail config`
- [ ] `SmtpConfig extracts port from mail config`
- [ ] `SmtpConfig extracts encryption setting`
- [ ] `SmtpConfig extracts username and password`
- [ ] `SmtpConfig extracts timeout setting`
- [ ] `SmtpConfig extracts auth_mode setting`
- [ ] `SmtpConfig provides default values for optional settings`

## Implementation Notes
